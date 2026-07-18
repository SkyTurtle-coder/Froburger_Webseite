document.addEventListener("trix-before-initialize", () => {
  if (!window.Trix) {
    return;
  }

  if (window.Trix.config.blockAttributes.heading1) {
    window.Trix.config.blockAttributes.heading1.tagName = "h2";
  }

  window.Trix.config.toolbar.getDefaultHTML = () => `
    <div class="trix-button-row">
      <span class="trix-button-group trix-button-group--text-tools">
        <button type="button" class="trix-button trix-button--icon trix-button--icon-bold" data-trix-attribute="bold" title="Fett" tabindex="-1">Fett</button>
        <button type="button" class="trix-button trix-button--icon trix-button--icon-italic" data-trix-attribute="italic" title="Kursiv" tabindex="-1">Kursiv</button>
        <button type="button" class="trix-button trix-button--icon trix-button--icon-link" data-trix-attribute="href" title="Link" tabindex="-1">Link</button>
      </span>
      <span class="trix-button-group trix-button-group--block-tools">
        <button type="button" class="trix-button trix-button--icon trix-button--icon-heading-1" data-trix-attribute="heading1" title="Zwischenueberschrift" tabindex="-1">Zwischenueberschrift</button>
        <button type="button" class="trix-button trix-button--icon trix-button--icon-quote" data-trix-attribute="quote" title="Zitat" tabindex="-1">Zitat</button>
        <button type="button" class="trix-button trix-button--icon trix-button--icon-bullet-list" data-trix-attribute="bullet" title="Aufzaehlung" tabindex="-1">Aufzaehlung</button>
        <button type="button" class="trix-button trix-button--icon trix-button--icon-number-list" data-trix-attribute="number" title="Nummerierte Liste" tabindex="-1">Nummerierte Liste</button>
      </span>
      <span class="trix-button-group trix-button-group--history-tools">
        <button type="button" class="trix-button trix-button--icon trix-button--icon-undo" data-trix-action="undo" title="Rueckgaengig" tabindex="-1">Rueckgaengig</button>
        <button type="button" class="trix-button trix-button--icon trix-button--icon-redo" data-trix-action="redo" title="Wiederholen" tabindex="-1">Wiederholen</button>
      </span>
    </div>
    <div class="trix-dialogs" data-trix-dialogs>
      <div class="trix-dialog trix-dialog--link" data-trix-dialog="href">
        <div class="trix-dialog__link-fields">
          <input type="url" name="href" class="trix-input trix-input--dialog" placeholder="https:// oder /pfad/" aria-label="Link">
          <div class="trix-button-group">
            <input type="button" class="trix-button trix-button--dialog" value="Link setzen" data-trix-method="setAttribute">
            <input type="button" class="trix-button trix-button--dialog" value="Link entfernen" data-trix-method="removeAttribute">
          </div>
        </div>
      </div>
    </div>
  `;
});

document.addEventListener("trix-file-accept", (event) => {
  event.preventDefault();
});

function syncTeaserCounter() {
  const field = document.querySelector("[data-teaser-counter='true']");
  const counter = document.querySelector("[data-teaser-count]");
  if (!field || !counter) {
    return;
  }

  const update = () => {
    counter.textContent = String(field.value.trim().length);
  };

  field.addEventListener("input", update);
  update();
}

document.addEventListener("DOMContentLoaded", () => {
  syncTeaserCounter();
});
