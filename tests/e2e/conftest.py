from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path

import pytest
from playwright.sync_api import Browser, BrowserContext, Page, Playwright, sync_playwright

VIEWPORTS = {
    "desktop": {"width": 1440, "height": 1100},
    "tablet": {"width": 1024, "height": 900},
    "mobile": {"width": 390, "height": 844},
}


@dataclass
class BrowserPageSession:
    page: Page
    context: BrowserContext
    browser: Browser
    playwright: Playwright
    console_errors: list[str]
    page_errors: list[str]
    tmp_path: Path
    index: int

    def close(self):
        try:
            if self.console_errors:
                (self.tmp_path / f"console-errors-{self.index}.txt").write_text(
                    "\n".join(self.console_errors),
                    encoding="utf-8",
                )
            if self.page_errors:
                (self.tmp_path / f"page-errors-{self.index}.txt").write_text(
                    "\n".join(self.page_errors),
                    encoding="utf-8",
                )
        finally:
            self.context.close()
            self.browser.close()
            self.playwright.stop()

        assert not self.console_errors, (
            f"Unexpected browser console errors: {self.console_errors}"
        )
        assert not self.page_errors, f"Unexpected page errors: {self.page_errors}"


@pytest.fixture
def browser_page_factory(tmp_path, transactional_db):
    index = 0

    def create(viewport_name: str = "desktop"):
        nonlocal index
        index += 1
        playwright = sync_playwright().start()
        browser = playwright.chromium.launch()
        viewport = VIEWPORTS[viewport_name]
        context = browser.new_context(
            viewport=viewport,
            accept_downloads=True,
            locale="de-CH",
            timezone_id="Europe/Zurich",
        )
        page = context.new_page()
        console_errors: list[str] = []
        page_errors: list[str] = []

        page.on(
            "console",
            lambda message: console_errors.append(message.text)
            if message.type == "error"
            else None,
        )
        page.on("pageerror", lambda error: page_errors.append(str(error)))
        return BrowserPageSession(
            page=page,
            context=context,
            browser=browser,
            playwright=playwright,
            console_errors=console_errors,
            page_errors=page_errors,
            tmp_path=tmp_path,
            index=index,
        )

    return create
