// Progressively enhances every native <select> into a custom-styled dropdown that matches
// the site's dark theme. The native <select> is kept in the DOM (invisible, non-interactive)
// as the single source of truth, so every existing call site that reads/sets `.value`,
// listens for "change", or does `form.reset()` keeps working with zero changes elsewhere.
(function () {
  function enhanceSelect(select) {
    if (select.dataset.csEnhanced === "true" || select.multiple) return;
    select.dataset.csEnhanced = "true";

    const wrapper = document.createElement("div");
    wrapper.className = "cs-select";
    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);

    const trigger = document.createElement("button");
    trigger.type = "button";
    trigger.className = "cs-select-trigger";
    wrapper.appendChild(trigger);

    const panel = document.createElement("div");
    panel.className = "cs-select-panel hidden";
    wrapper.appendChild(panel);

    function syncTrigger() {
      const option = select.options[select.selectedIndex];
      trigger.textContent = option ? option.textContent : "";
      trigger.disabled = select.disabled;
      trigger.classList.toggle("cs-select-trigger-disabled", select.disabled);
    }

    function renderPanel() {
      panel.innerHTML = "";
      Array.from(select.options).forEach((option, index) => {
        const item = document.createElement("button");
        item.type = "button";
        item.className = "cs-select-option" + (index === select.selectedIndex ? " active" : "");
        item.textContent = option.textContent;
        item.addEventListener("click", () => {
          select.selectedIndex = index;
          select.dispatchEvent(new Event("change", { bubbles: true }));
          syncTrigger();
          closePanel();
        });
        panel.appendChild(item);
      });
    }

    function openPanel() {
      if (select.disabled) return;
      renderPanel();
      panel.classList.remove("hidden");
      trigger.classList.add("open");
    }

    function closePanel() {
      panel.classList.add("hidden");
      trigger.classList.remove("open");
    }

    trigger.addEventListener("click", (event) => {
      event.stopPropagation();
      if (panel.classList.contains("hidden")) openPanel();
      else closePanel();
    });

    document.addEventListener("click", (event) => {
      if (!wrapper.contains(event.target)) closePanel();
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") closePanel();
    });

    select.addEventListener("change", syncTrigger);

    // Options are often populated (or the selected value set) by page JS after an API call
    // resolves, well after this select was enhanced — keep the trigger honest either way.
    new MutationObserver(() => {
      syncTrigger();
      if (!panel.classList.contains("hidden")) renderPanel();
    }).observe(select, { childList: true, subtree: true, attributes: true, attributeFilter: ["disabled"] });

    // Programmatic `select.value = x` assignments (used throughout this codebase, e.g.
    // `categoryFilter.value = id`) do NOT fire a "change" event, so the MutationObserver
    // above can't catch them either — intercept the `value` setter itself to stay in sync.
    const valueDescriptor = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, "value");
    Object.defineProperty(select, "value", {
      get() {
        return valueDescriptor.get.call(select);
      },
      set(newValue) {
        valueDescriptor.set.call(select, newValue);
        syncTrigger();
        if (!panel.classList.contains("hidden")) renderPanel();
      },
      configurable: true,
    });

    syncTrigger();
  }

  function enhanceAll(root) {
    (root || document).querySelectorAll("select").forEach(enhanceSelect);
  }

  document.addEventListener("DOMContentLoaded", () => {
    enhanceAll(document);

    // Selects added later (e.g. a modal injected after the page loads) get enhanced too.
    new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
          if (node.nodeType !== 1) return;
          if (node.tagName === "SELECT") enhanceSelect(node);
          else enhanceAll(node);
        });
      });
    }).observe(document.body, { childList: true, subtree: true });
  });
})();
