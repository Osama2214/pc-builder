// Custom toast notification system — replaces native alert() with a dark-theme-matching
// message that slides in from the side and auto-dismisses, instead of a blocking OS dialog.
function showToast(message, type) {
  let container = document.getElementById("toast-container");
  if (!container) {
    container = document.createElement("div");
    container.id = "toast-container";
    container.className = "toast-container";
    document.body.appendChild(container);
  }

  const toast = document.createElement("div");
  toast.className = `toast toast-${type || "info"}`;
  toast.innerHTML = `
    <span class="toast-message"></span>
    <button type="button" class="toast-close" aria-label="Dismiss">&times;</button>
  `;
  toast.querySelector(".toast-message").textContent = message;
  container.appendChild(toast);

  requestAnimationFrame(() => toast.classList.add("toast-show"));

  function dismiss() {
    if (!toast.isConnected) return;
    toast.classList.remove("toast-show");
    // transitionend won't fire if the transition never actually ran (e.g. prefers-reduced-motion,
    // or the element never became visible) — fall back to a timed removal either way.
    toast.addEventListener("transitionend", () => toast.remove(), { once: true });
    setTimeout(() => toast.remove(), 250);
  }

  toast.querySelector(".toast-close").addEventListener("click", dismiss);
  setTimeout(dismiss, 4000);
}
