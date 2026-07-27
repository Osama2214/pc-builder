// Custom confirm dialog — replaces native confirm() with a dark-theme-matching modal.
// Usage: if (!(await showConfirm("Delete this?"))) return;
function showConfirm(message, options) {
  options = options || {};

  return new Promise((resolve) => {
    let overlay = document.getElementById("confirm-dialog-overlay");
    if (!overlay) {
      overlay = document.createElement("div");
      overlay.id = "confirm-dialog-overlay";
      overlay.className = "modal-overlay hidden";
      overlay.innerHTML = `
        <div class="confirm-dialog-box">
          <p class="confirm-dialog-message" id="confirm-dialog-message"></p>
          <div class="confirm-dialog-actions">
            <button type="button" class="btn btn-secondary btn-auto" id="confirm-dialog-cancel"></button>
            <button type="button" class="btn btn-auto btn-danger" id="confirm-dialog-ok"></button>
          </div>
        </div>
      `;
      document.body.appendChild(overlay);
    }

    const messageEl = document.getElementById("confirm-dialog-message");
    const okBtn = document.getElementById("confirm-dialog-ok");
    const cancelBtn = document.getElementById("confirm-dialog-cancel");

    messageEl.textContent = message;
    okBtn.textContent = options.confirmText || "OK";
    cancelBtn.textContent = options.cancelText || "Cancel";

    overlay.classList.remove("hidden");
    okBtn.focus();

    function cleanup(result) {
      overlay.classList.add("hidden");
      okBtn.removeEventListener("click", onOk);
      cancelBtn.removeEventListener("click", onCancel);
      overlay.removeEventListener("click", onOverlayClick);
      document.removeEventListener("keydown", onKeydown);
      resolve(result);
    }

    function onOk() {
      cleanup(true);
    }

    function onCancel() {
      cleanup(false);
    }

    function onOverlayClick(event) {
      if (event.target === overlay) cleanup(false);
    }

    function onKeydown(event) {
      if (event.key === "Escape") cleanup(false);
    }

    okBtn.addEventListener("click", onOk);
    cancelBtn.addEventListener("click", onCancel);
    overlay.addEventListener("click", onOverlayClick);
    document.addEventListener("keydown", onKeydown);
  });
}
