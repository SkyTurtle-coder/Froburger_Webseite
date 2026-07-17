document.addEventListener("DOMContentLoaded", () => {
  const body = document.body;
  const menuToggle = document.querySelector(".menu-toggle");
  const navWrap = document.querySelector(".nav-wrap");
  const navItems = Array.from(document.querySelectorAll(".nav-item"));
  const dropdownButtons = navItems
    .map((item) => item.querySelector(".nav-dropdown-button"))
    .filter(Boolean);

  const setMenuState = (isOpen) => {
    if (!menuToggle || !navWrap) {
      return;
    }

    menuToggle.setAttribute("aria-expanded", String(isOpen));
    menuToggle.setAttribute("aria-label", isOpen ? "Navigation schliessen" : "Navigation öffnen");
    navWrap.classList.toggle("is-open", isOpen);
    body.classList.toggle("menu-open", isOpen);
  };

  const setDropdownState = (item, isOpen) => {
    const button = item.querySelector(".nav-dropdown-button");

    if (!button) {
      return;
    }

    item.classList.toggle("is-open", isOpen);
    button.setAttribute("aria-expanded", String(isOpen));
  };

  const closeAllDropdowns = (options = {}) => {
    const { except = null, focusButton = false } = options;

    navItems.forEach((item) => {
      if (item === except) {
        return;
      }

      const wasOpen = item.classList.contains("is-open");
      setDropdownState(item, false);

      if (focusButton && wasOpen) {
        item.querySelector(".nav-dropdown-button")?.focus();
      }
    });
  };

  if (menuToggle && navWrap) {
    setMenuState(false);

    menuToggle.addEventListener("click", () => {
      const isOpen = menuToggle.getAttribute("aria-expanded") === "true";

      if (isOpen) {
        closeAllDropdowns();
      }

      setMenuState(!isOpen);
    });

    navWrap.querySelectorAll("a").forEach((link) => {
      link.addEventListener("click", () => {
        closeAllDropdowns();
        setMenuState(false);
      });
    });

    window.addEventListener("resize", () => {
      if (window.innerWidth > 768) {
        setMenuState(false);
      }
    });
  }

  dropdownButtons.forEach((button) => {
    const item = button.closest(".nav-item");
    const dropdown = item?.querySelector(".dropdown");

    if (!item || !dropdown) {
      return;
    }

    const focusFirstLink = () => {
      dropdown.querySelector("a")?.focus();
    };

    button.addEventListener("click", () => {
      const isOpen = item.classList.contains("is-open");
      closeAllDropdowns({ except: item });
      setDropdownState(item, !isOpen);
    });

    button.addEventListener("keydown", (event) => {
      if (event.key === "ArrowDown") {
        event.preventDefault();
        closeAllDropdowns({ except: item });
        setDropdownState(item, true);
        focusFirstLink();
      }

      if (event.key === "Escape") {
        event.preventDefault();
        setDropdownState(item, false);
      }
    });

    dropdown.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        event.preventDefault();
        setDropdownState(item, false);
        button.focus();
      }
    });

    item.addEventListener("focusout", () => {
      window.requestAnimationFrame(() => {
        if (!item.contains(document.activeElement)) {
          setDropdownState(item, false);
        }
      });
    });
  });

  document.addEventListener("click", (event) => {
    if (!(event.target instanceof Element)) {
      return;
    }

    if (!event.target.closest(".nav-item")) {
      closeAllDropdowns();
    }
  });

  const escapeIcs = (value = "") =>
    value
      .replace(/\\/g, "\\\\")
      .replace(/\r?\n/g, "\\n")
      .replace(/,/g, "\\,")
      .replace(/;/g, "\\;");

  const formatIcsDate = (value) => value.replace(/[-:]/g, "").replace(".000", "");

  const slugify = (value = "") =>
    value
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/^-+|-+$/g, "");

  document.querySelectorAll(".calendar-btn").forEach((button) => {
    button.addEventListener("click", () => {
      const { title = "anlass", start = "", end = "", location = "", description = "" } = button.dataset;
      const ics = [
        "BEGIN:VCALENDAR",
        "VERSION:2.0",
        "PRODID:-//AV Froburger//Anlasskalender//DE",
        "CALSCALE:GREGORIAN",
        "BEGIN:VEVENT",
        `UID:${Date.now()}@avfroburger.ch`,
        `DTSTAMP:${formatIcsDate(new Date().toISOString())}`,
        `DTSTART:${formatIcsDate(start)}`,
        `DTEND:${formatIcsDate(end)}`,
        `SUMMARY:${escapeIcs(title)}`,
        `LOCATION:${escapeIcs(location)}`,
        `DESCRIPTION:${escapeIcs(description)}`,
        "END:VEVENT",
        "END:VCALENDAR"
      ].join("\r\n");

      const blob = new Blob([ics], { type: "text/calendar;charset=utf-8" });
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = `${slugify(title) || "anlass"}.ics`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    });
  });

  const calendarList = document.querySelector(".calendar-list");
  const calendarMonth = document.querySelector(".calendar-month");
  const monthGrid = calendarMonth?.querySelector(".month-grid");
  const monthHeading = calendarMonth?.querySelector(".month-heading h3");
  const monthStatus = calendarMonth?.querySelector(".month-status");
  const monthButtons = Array.from(document.querySelectorAll(".view-btn[data-view]"));
  const monthSwitcher = document.querySelector(".view-switch");
  const mobileCalendarQuery = window.matchMedia("(max-width: 700px)");

  const escapeHtml = (value = "") =>
    value
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#39;");

  if (calendarList && calendarMonth && monthGrid && monthHeading) {
    const monthNames = [
      "Januar",
      "Februar",
      "März",
      "April",
      "Mai",
      "Juni",
      "Juli",
      "August",
      "September",
      "Oktober",
      "November",
      "Dezember"
    ];
    const weekdayNames = ["Mo", "Di", "Mi", "Do", "Fr", "Sa", "So"];
    const calendarEvents = Array.from(document.querySelectorAll(".calendar-event"))
      .map((event, index) => {
        const dateElement = event.querySelector(".calendar-date");
        const title = event.querySelector(".calendar-copy h3")?.textContent?.trim() || `Anlass ${index + 1}`;
        const summary = event.querySelector(".calendar-copy p")?.textContent?.trim() || "";
        const metaLines = Array.from(event.querySelectorAll(".calendar-meta span")).map((entry) => entry.textContent.trim());
        const button = event.querySelector(".calendar-btn");
        const isoDate = dateElement?.getAttribute("datetime") || button?.dataset.start?.slice(0, 4) + "-" + button?.dataset.start?.slice(4, 6) + "-" + button?.dataset.start?.slice(6, 8);

        if (!isoDate) {
          return null;
        }

        const [year, month, day] = isoDate.split("-").map(Number);

        return {
          day,
          isoDate,
          location: metaLines[1] || button?.dataset.location || "",
          month,
          summary,
          time: metaLines[0] || "",
          title,
          year
        };
      })
      .filter(Boolean);

    const firstEvent = calendarEvents[0];
    let currentYear = firstEvent ? firstEvent.year : new Date().getFullYear();
    let currentMonth = firstEvent ? firstEvent.month : new Date().getMonth() + 1;

    const setView = (view) => {
      const forceList = mobileCalendarQuery.matches;
      const showMonth = !forceList && view === "month";

      calendarList.hidden = showMonth;
      calendarMonth.hidden = !showMonth;

      monthButtons.forEach((button) => {
        const isActive = !forceList && button.dataset.view === view;
        const isListFallback = forceList && button.dataset.view === "list";

        button.classList.toggle("active", isActive || isListFallback);
        button.setAttribute("aria-pressed", String(isActive || isListFallback));

        if (button.dataset.view === "month") {
          button.disabled = forceList;
        }
      });

      monthSwitcher?.classList.toggle("is-mobile-list", forceList);
    };

    const renderMonth = (year, month) => {
      const monthEvents = calendarEvents.filter((event) => event.year === year && event.month === month);
      const eventsByDay = new Map();

      monthEvents.forEach((event) => {
        const dayEvents = eventsByDay.get(event.day) || [];
        dayEvents.push(event);
        eventsByDay.set(event.day, dayEvents);
      });

      const firstWeekday = (new Date(year, month - 1, 1).getDay() + 6) % 7;
      const daysInMonth = new Date(year, month, 0).getDate();
      const daysInPreviousMonth = new Date(year, month - 1, 0).getDate();
      const monthName = monthNames[month - 1];
      const trailingDays = (7 - ((firstWeekday + daysInMonth) % 7)) % 7;
      let html = weekdayNames.map((weekday) => `<div class="weekday">${weekday}</div>`).join("");

      for (let index = firstWeekday - 1; index >= 0; index -= 1) {
        html += `<div class="day muted" aria-hidden="true"><span>${daysInPreviousMonth - index}</span></div>`;
      }

      for (let day = 1; day <= daysInMonth; day += 1) {
        const dayEvents = eventsByDay.get(day) || [];
        const className = dayEvents.length ? "day has-event" : "day";
        const eventsHtml = dayEvents.length
          ? `<ul class="day-events">${dayEvents
              .map((event) => `<li>${escapeHtml(event.title)}</li>`)
              .join("")}</ul>`
          : "";

        html += `<div class="${className}"><span>${day}</span>${eventsHtml}</div>`;
      }

      for (let day = 1; day <= trailingDays; day += 1) {
        html += `<div class="day muted" aria-hidden="true"><span>${day}</span></div>`;
      }

      monthHeading.textContent = `${monthName} ${year}`;
      calendarMonth.setAttribute("aria-label", `Monatsansicht ${monthName} ${year}`);
      monthGrid.innerHTML = html;

      if (monthStatus) {
        const label = monthEvents.length === 1 ? "Anlass" : "Anlässe";
        monthStatus.textContent = monthEvents.length
          ? `${monthEvents.length} ${label} in diesem Monat.`
          : "Keine Anlässe in diesem Monat.";
      }
    };

    monthButtons.forEach((button) => {
      button.addEventListener("click", () => {
        if (button.dataset.view === "month" && mobileCalendarQuery.matches) {
          setView("list");
          return;
        }

        setView(button.dataset.view);
      });
    });

    calendarMonth.querySelector('[aria-label="Vorheriger Monat"]')?.addEventListener("click", () => {
      currentMonth -= 1;

      if (currentMonth < 1) {
        currentMonth = 12;
        currentYear -= 1;
      }

      renderMonth(currentYear, currentMonth);
    });

    calendarMonth.querySelector('[aria-label="Nächster Monat"]')?.addEventListener("click", () => {
      currentMonth += 1;

      if (currentMonth > 12) {
        currentMonth = 1;
        currentYear += 1;
      }

      renderMonth(currentYear, currentMonth);
    });

    mobileCalendarQuery.addEventListener("change", () => {
      if (mobileCalendarQuery.matches) {
        setView("list");
      }
    });

    renderMonth(currentYear, currentMonth);
    setView("list");
  }
});
