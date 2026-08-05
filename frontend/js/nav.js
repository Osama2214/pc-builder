function renderNavbar() {
  const placeholder = document.getElementById("navbar-placeholder");
  if (!placeholder) return;

  const user = Auth.getUser();
  const loggedIn = Auth.isLoggedIn() && user;

  // Root-relative paths on purpose: this navbar is rendered from both the site
  // root (e.g. products.html) and one level deep (admin/*.html), so relative
  // paths like "products.html" would resolve to "admin/products.html" there.
  const authHtml = renderThemeToggle() +
    (loggedIn
      ? renderCartIcon() + renderProfileMenu(user)
      : renderGuestActions());


  const memberLinks = loggedIn ? `<a href="/build.html">Build a PC</a>` : "";

  placeholder.innerHTML = `
    <nav class="navbar">
      <div class="container navbar-inner">
        <a href="/index.html" class="brand">PC Builder</a>
        ${renderNavbarSearch()}
        <div class="nav-actions">${authHtml}</div>
      </div>
      <div class="container navbar-links-row">
        ${renderCategoriesMenu()}
        <div class="nav-links">
          <a href="/products.html">Products</a>
          <a href="/compare.html">Compare</a>
          ${memberLinks}
        </div>
      </div>
    </nav>
  `;

  wireNavbarSearch();
  wireCategoriesMenu();
  wireThemeToggle();

  if (loggedIn) {
    wireProfileMenu();
    const cartTrigger = document.getElementById("cart-icon-trigger");
    if (cartTrigger) cartTrigger.addEventListener("click", () => openCartDrawer());
    refreshCartBadge();
  }
}

function renderNavbarSearch() {
  return `
    <form class="navbar-search" id="navbar-search-form" role="search">
      <button type="submit" class="navbar-search-icon" aria-label="Search">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
      </button>
      <div class="navbar-search-field">
        <input type="search" id="navbar-search-input" aria-label="Search products" autocomplete="off" />
        <span class="navbar-search-placeholder" id="navbar-search-placeholder" aria-hidden="true">
          <span id="navbar-search-placeholder-text"></span><span class="navbar-search-caret">|</span>
        </span>
      </div>
    </form>
  `;
}
function renderThemeToggle() {
  return `
    <button
      id="theme-toggle"
      class="theme-toggle-switch"
      type="button"
      aria-label="Toggle theme"
      title="Toggle theme"
    >
      <span class="theme-switch-track">
        <span class="theme-switch-icon theme-switch-icon-sun">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="5"></circle>
            <line x1="12" y1="1" x2="12" y2="3"></line>
            <line x1="12" y1="21" x2="12" y2="23"></line>
            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
            <line x1="1" y1="12" x2="3" y2="12"></line>
            <line x1="21" y1="12" x2="23" y2="12"></line>
            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
          </svg>
        </span>
        <span class="theme-switch-icon theme-switch-icon-moon">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
          </svg>
        </span>
        <span class="theme-switch-thumb"></span>
      </span>
    </button>
  `;
}

function wireNavbarSearch() {
  const form = document.getElementById("navbar-search-form");
  const input = document.getElementById("navbar-search-input");
  if (!form || !input) return;

  const currentParams = new URLSearchParams(window.location.search);
  if (window.location.pathname.endsWith("/products.html") && currentParams.get("search")) {
    input.value = currentParams.get("search");
  }

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    const query = input.value.trim();
    window.location.href = query ? `/products.html?search=${encodeURIComponent(query)}` : "/products.html";
  });

  runPlaceholderTypewriter(input);
  loadPlaceholderWords();
}

let placeholderWords = ["Search for anything"];

async function loadPlaceholderWords() {
  try {
    const [products, brands] = await Promise.all([
      apiRequest("/products", { auth: false }),
      apiRequest("/brands", { auth: false }),
    ]);

    const names = [...products.data.map((p) => p.name), ...brands.data.map((b) => b.name)].filter(Boolean);

    for (let i = names.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [names[i], names[j]] = [names[j], names[i]];
    }

    if (names.length) placeholderWords = names;
  } catch (error) {
    // Keep the default placeholder if products/brands can't be loaded.
  }
}

function runPlaceholderTypewriter(input) {
  const overlay = document.getElementById("navbar-search-placeholder");
  const textEl = document.getElementById("navbar-search-placeholder-text");
  if (!overlay || !textEl) return;

  let wordIndex = 0;

  function typeWord(word, pos, typing) {
    if (!document.body.contains(input)) return;

    if (input.value) {
      overlay.classList.add("hidden");
      setTimeout(() => typeWord(word, pos, typing), 400);
      return;
    }

    overlay.classList.remove("hidden");
    textEl.textContent = word.slice(0, pos);

    if (typing) {
      if (pos < word.length) {
        setTimeout(() => typeWord(word, pos + 1, true), 90);
      } else {
        setTimeout(() => typeWord(word, pos, false), 1400);
      }
    } else if (pos > 0) {
      setTimeout(() => typeWord(word, pos - 1, false), 40);
    } else {
      wordIndex++;
      setTimeout(() => typeWord(placeholderWords[wordIndex % placeholderWords.length], 0, true), 300);
    }
  }

  typeWord(placeholderWords[0], 0, true);
}

function renderCategoriesMenu() {
  return `
    <div class="categories-menu" id="categories-menu">
      <button type="button" class="categories-trigger" id="categories-trigger">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="3" y1="6" x2="21" y2="6"></line>
          <line x1="3" y1="12" x2="21" y2="12"></line>
          <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
        <span>Categories</span>
        <svg class="categories-trigger-chevron" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 12 15 18 9"></polyline>
        </svg>
      </button>
      <div class="categories-dropdown hidden" id="categories-dropdown">
        <div class="categories-dropdown-list" id="categories-dropdown-list">
          <div class="state-message">Loading...</div>
        </div>
        <div class="categories-dropdown-panel hidden" id="categories-dropdown-panel">
          <h4><a href="/products.html" id="categories-dropdown-panel-title" class="categories-dropdown-panel-title-link"></a></h4>
          <div id="categories-dropdown-panel-body"></div>
        </div>
      </div>
    </div>
  `;
}

function wireCategoriesMenu() {
  const menu = document.getElementById("categories-menu");
  const trigger = document.getElementById("categories-trigger");
  const dropdown = document.getElementById("categories-dropdown");
  if (!menu || !trigger || !dropdown) return;

  trigger.addEventListener("click", (event) => {
    event.stopPropagation();
    const isOpen = !dropdown.classList.contains("hidden");
    dropdown.classList.toggle("hidden", isOpen);
    menu.classList.toggle("open", !isOpen);
  });

  document.addEventListener("click", (event) => {
    if (!menu.contains(event.target)) {
      dropdown.classList.add("hidden");
      menu.classList.remove("open");
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      dropdown.classList.add("hidden");
      menu.classList.remove("open");
    }
  });

  loadCategoriesMenu();
}

async function loadCategoriesMenu() {
  const listEl = document.getElementById("categories-dropdown-list");
  if (!listEl) return;

  try {
    const result = await apiRequest("/categories", { auth: false });

    if (!result.data.length) {
      listEl.innerHTML = `<div class="state-message">No categories yet.</div>`;
      return;
    }

    listEl.innerHTML = result.data
      .map(
        (category) => `
          <div class="category-item-wrapper" data-category-id="${category.id}">
            <div class="categories-dropdown-item" data-category-id="${category.id}" data-category-name="${escapeHtml(category.name)}">
              <a href="/products.html?category_id=${category.id}" class="category-item-link">
                ${escapeHtml(category.name)}
              </a>
              <button type="button" class="category-expand-btn" aria-label="Toggle ${escapeHtml(category.name)}">
                <svg class="categories-dropdown-chevron" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
              </button>
            </div>
            <div class="category-sub-accordion hidden" id="category-sub-${category.id}">
              <div class="category-sub-content">
                <div class="state-message">Loading...</div>
              </div>
            </div>
          </div>
        `
      )
      .join("");

    const wrappers = listEl.querySelectorAll(".category-item-wrapper");
    wrappers.forEach((wrapper) => {
      const itemEl = wrapper.querySelector(".categories-dropdown-item");
      const categoryId = wrapper.dataset.categoryId;

      itemEl.addEventListener("mouseenter", () => showCategoryPanel(itemEl));
      itemEl.addEventListener("focusin", () => showCategoryPanel(itemEl));

      itemEl.addEventListener("click", (e) => {
        if (window.innerWidth <= 700) {
          e.preventDefault();
          e.stopPropagation();
          toggleMobileAccordion(wrapper, categoryId);
        }
      });
    });

    const firstItem = listEl.querySelector(".categories-dropdown-item");
    if (firstItem) showCategoryPanel(firstItem);
  } catch (error) {
    listEl.innerHTML = `<div class="state-message">Could not load categories.</div>`;
  }
}

async function toggleMobileAccordion(wrapper, categoryId) {
  const accordion = wrapper.querySelector(".category-sub-accordion");
  if (!accordion) return;

  const isExpanded = wrapper.classList.contains("expanded");

  const listEl = document.getElementById("categories-dropdown-list");
  if (listEl) {
    listEl.querySelectorAll(".category-item-wrapper.expanded").forEach((other) => {
      if (other !== wrapper) {
        other.classList.remove("expanded");
        const otherAccordion = other.querySelector(".category-sub-accordion");
        if (otherAccordion) otherAccordion.classList.add("hidden");
      }
    });
  }

  if (isExpanded) {
    wrapper.classList.remove("expanded");
    accordion.classList.add("hidden");
  } else {
    wrapper.classList.add("expanded");
    accordion.classList.remove("hidden");

    const subContent = wrapper.querySelector(".category-sub-content");
    const itemEl = wrapper.querySelector(".categories-dropdown-item");
    const categoryName = itemEl ? itemEl.dataset.categoryName : "";

    if (categoryBrandsCache[categoryId]) {
      renderMobileSubContent(subContent, categoryId, categoryName, categoryBrandsCache[categoryId]);
    } else {
      subContent.innerHTML = `<div class="state-message">Loading...</div>`;
      try {
        const result = await apiRequest(`/products?category_id=${categoryId}`, { auth: false });
        const brandsMap = new Map();
        result.data.forEach((product) => {
          if (product.brand) brandsMap.set(product.brand.id, product.brand.name);
        });
        const brands = Array.from(brandsMap, ([id, name]) => ({ id, name }));
        categoryBrandsCache[categoryId] = brands;
        renderMobileSubContent(subContent, categoryId, categoryName, brands);
      } catch (err) {
        subContent.innerHTML = `<div class="state-message">Could not load.</div>`;
      }
    }
  }
}

function renderMobileSubContent(subContent, categoryId, categoryName, brands) {
  if (!subContent) return;
  let html = `<a href="/products.html?category_id=${categoryId}" class="category-sub-link category-sub-link-all">View all ${escapeHtml(categoryName)}</a>`;
  if (brands.length) {
    html += brands
      .map(
        (b) =>
          `<a href="/products.html?category_id=${categoryId}&brand_id=${b.id}" class="category-sub-link">${escapeHtml(b.name)}</a>`
      )
      .join("");
  }
  subContent.innerHTML = html;
}

const categoryBrandsCache = {};

function showCategoryPanel(item) {
  if (window.innerWidth <= 700) return;
  const listEl = document.getElementById("categories-dropdown-list");
  const panel = document.getElementById("categories-dropdown-panel");
  const titleEl = document.getElementById("categories-dropdown-panel-title");
  const bodyEl = document.getElementById("categories-dropdown-panel-body");
  if (!listEl || !panel || !titleEl || !bodyEl) return;

  listEl.querySelectorAll(".categories-dropdown-item").forEach((el) => el.classList.toggle("active", el === item));

  const categoryId = item.dataset.categoryId;
  const categoryName = item.dataset.categoryName;

  panel.classList.remove("hidden");
  titleEl.textContent = categoryName;
  titleEl.href = `/products.html?category_id=${categoryId}`;

  if (categoryBrandsCache[categoryId]) {
    renderCategoryPanelBody(bodyEl, categoryId, categoryBrandsCache[categoryId]);
    return;
  }

  bodyEl.innerHTML = `<p class="state-message">Loading...</p>`;

  apiRequest(`/products?category_id=${categoryId}`, { auth: false })
    .then((result) => {
      const brandsMap = new Map();
      result.data.forEach((product) => {
        if (product.brand) brandsMap.set(product.brand.id, product.brand.name);
      });
      const brands = Array.from(brandsMap, ([id, name]) => ({ id, name }));
      categoryBrandsCache[categoryId] = brands;

      // Only render if the user hasn't already hovered to a different category.
      if (titleEl.textContent === categoryName) renderCategoryPanelBody(bodyEl, categoryId, brands);
    })
    .catch(() => {
      if (titleEl.textContent === categoryName) bodyEl.innerHTML = `<p class="state-message">Could not load.</p>`;
    });
}

function renderCategoryPanelBody(bodyEl, categoryId, brands) {
  if (!brands.length) {
    bodyEl.innerHTML = `<p class="state-message">No products yet.</p>`;
    return;
  }

  bodyEl.innerHTML = brands
    .map((brand) => `<a href="/products.html?category_id=${categoryId}&brand_id=${brand.id}">${escapeHtml(brand.name)}</a>`)
    .join("");
}

function renderCartIcon() {
  return `
    <button type="button" class="nav-icon-link" id="cart-icon-trigger" aria-label="Cart" style="position:relative; background:none; border:none; cursor:pointer;">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="9" cy="21" r="1"></circle>
        <circle cx="20" cy="21" r="1"></circle>
        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
      </svg>
      <span class="cart-badge hidden" id="cart-badge">0</span>
    </button>
  `;
}

function renderGuestActions() {
  return `<a href="/login.html">Login</a> <a href="/register.html" class="btn btn-auto">Register</a>`;
}

function renderProfileMenu(user) {
  const initial = (user.name || "?").trim().charAt(0).toUpperCase();

  const adminLink = user.role === "admin" ? `<a href="/admin/index.html">Admin Dashboard</a>` : "";

  return `
    <div class="profile-menu" id="profile-menu">
      <button type="button" class="profile-trigger" id="profile-trigger">
        <span class="profile-avatar">${escapeHtml(initial)}</span>
      </button>
      <div class="profile-dropdown hidden" id="profile-dropdown">
        <div class="profile-dropdown-name">${escapeHtml(user.name)}</div>
        <a href="/profile.html">My Profile</a>
        <a href="/my-builds.html">My Builds</a>
        <a href="/wishlist.html">Wishlist</a>
        <a href="/orders.html">My Orders</a>
        ${adminLink ? `<div class="profile-dropdown-divider"></div>${adminLink}` : ""}
        <div class="profile-dropdown-divider"></div>
        <button type="button" class="profile-dropdown-logout" id="logout-btn">Logout</button>
      </div>
    </div>
  `;
}

function wireProfileMenu() {
  const menu = document.getElementById("profile-menu");
  const trigger = document.getElementById("profile-trigger");
  const dropdown = document.getElementById("profile-dropdown");
  const logoutBtn = document.getElementById("logout-btn");

  trigger.addEventListener("click", (event) => {
    event.stopPropagation();
    const isOpen = !dropdown.classList.contains("hidden");
    dropdown.classList.toggle("hidden", isOpen);
    menu.classList.toggle("open", !isOpen);
  });

  document.addEventListener("click", (event) => {
    if (!menu.contains(event.target)) {
      dropdown.classList.add("hidden");
      menu.classList.remove("open");
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      dropdown.classList.add("hidden");
      menu.classList.remove("open");
    }
  });

  logoutBtn.addEventListener("click", handleLogout);
}

async function handleLogout() {
  try {
    await Api.logout();
  } catch (e) {
    // Even if the request fails (e.g. token already expired), clear locally.
  } finally {
    Auth.clearSession();
    window.location.href = "/login.html";
  }
}
const THEME_KEY = "theme";

function applyTheme(theme) {
  document.documentElement.setAttribute("data-theme", theme);

  const btn = document.getElementById("theme-toggle");
  if (btn) {
    btn.setAttribute("aria-label", `Switch to ${theme === "dark" ? "light" : "dark"} mode`);
    btn.setAttribute("title", `Switch to ${theme === "dark" ? "light" : "dark"} mode`);
  }
}

function loadTheme() {
  const saved = localStorage.getItem(THEME_KEY) || "dark";
  applyTheme(saved);
}

function wireThemeToggle() {
  loadTheme();

  const btn = document.getElementById("theme-toggle");
  if (!btn) return;

  btn.addEventListener("click", () => {
    const current = document.documentElement.getAttribute("data-theme") || "dark";

    const next = current === "dark" ? "light" : "dark";

    localStorage.setItem(THEME_KEY, next);

    applyTheme(next);
  });
}

document.addEventListener("DOMContentLoaded", renderNavbar);
