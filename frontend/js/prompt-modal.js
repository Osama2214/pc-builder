// Custom prompt dialog — replaces native prompt() with a dark-theme-matching modal.
// Usage: const name = await showPromptModal({ title: "New brand", label: "Name" });
//        if (!name) return; // cancelled
function showPromptModal(options) {
  options = options || {};

  return new Promise((resolve) => {
    let overlay = document.getElementById("prompt-modal-overlay");
    if (!overlay) {
      overlay = document.createElement("div");
      overlay.id = "prompt-modal-overlay";
      overlay.className = "modal-overlay hidden";
      overlay.innerHTML = `
        <div class="confirm-dialog-box">
          <h3 id="prompt-modal-title" style="margin-top: 0;"></h3>
          <div class="field">
            <label id="prompt-modal-label" for="prompt-modal-input"></label>
            <input type="text" id="prompt-modal-input" autocomplete="off" />
            <div class="field-error hidden" id="prompt-modal-error"></div>
          </div>
          <div class="confirm-dialog-actions">
            <button type="button" class="btn btn-secondary btn-auto" id="prompt-modal-cancel">Cancel</button>
            <button type="button" class="btn btn-auto" id="prompt-modal-ok">Add</button>
          </div>
        </div>
      `;
      document.body.appendChild(overlay);
    }

    const titleEl = document.getElementById("prompt-modal-title");
    const labelEl = document.getElementById("prompt-modal-label");
    const inputEl = document.getElementById("prompt-modal-input");
    const errorEl = document.getElementById("prompt-modal-error");
    const okBtn = document.getElementById("prompt-modal-ok");
    const cancelBtn = document.getElementById("prompt-modal-cancel");

    titleEl.textContent = options.title || "";
    labelEl.textContent = options.label || "Name";
    okBtn.textContent = options.confirmText || "Add";
    inputEl.value = "";
    inputEl.placeholder = options.placeholder || "";
    inputEl.classList.remove("invalid");
    errorEl.classList.add("hidden");
    errorEl.textContent = "";

    overlay.classList.remove("hidden");
    inputEl.focus();

    function cleanup(result) {
      overlay.classList.add("hidden");
      okBtn.removeEventListener("click", onOk);
      cancelBtn.removeEventListener("click", onCancel);
      overlay.removeEventListener("click", onOverlayClick);
      document.removeEventListener("keydown", onKeydown);
      inputEl.removeEventListener("keydown", onInputKeydown);
      resolve(result);
    }

    function onOk() {
      const value = inputEl.value.trim();
      if (!value) {
        errorEl.textContent = "This field is required.";
        errorEl.classList.remove("hidden");
        inputEl.classList.add("invalid");
        inputEl.focus();
        return;
      }
      cleanup(value);
    }

    function onCancel() {
      cleanup(null);
    }

    function onOverlayClick(event) {
      if (event.target === overlay) cleanup(null);
    }

    function onKeydown(event) {
      if (event.key === "Escape") cleanup(null);
    }

    function onInputKeydown(event) {
      if (event.key === "Enter") {
        event.preventDefault();
        onOk();
      }
    }

    okBtn.addEventListener("click", onOk);
    cancelBtn.addEventListener("click", onCancel);
    overlay.addEventListener("click", onOverlayClick);
    document.addEventListener("keydown", onKeydown);
    inputEl.addEventListener("keydown", onInputKeydown);
  });
}
