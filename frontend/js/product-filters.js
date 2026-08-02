// Kept in sync with SPEC_LABELS in product.html — both are the display names for the
// same product_specifications columns, just consumed by two different pages.
const SPEC_FIELD_LABELS = {
  socket: "Socket", chipset: "Chipset", form_factor: "Form Factor", cores: "Cores",
  threads: "Threads", clock_speed: "Base Clock", boost_clock: "Boost Clock",
  cpu_generation: "CPU Generation", architecture: "Architecture",
  integrated_graphics: "Integrated Graphics", cache_size: "Total Cache",
  l1_cache: "L1 Cache", l2_cache: "L2 Cache", l3_cache: "L3 Cache",
  pcie_version: "PCIe Version", pcie_slots: "PCIe Slots", ram_type: "RAM Type",
  memory_type: "VRAM Type", memory_size: "Memory Size", max_memory: "Max Memory",
  memory_slots: "Memory Slots", m2_slots: "M.2 Slots", sata_ports: "SATA Ports",
  wifi: "Wi-Fi", length_mm: "Length (mm)", video_ports: "Video Ports",
  ram_speed: "Speed (MHz)", cas_latency: "CAS Latency", kit_config: "Kit Configuration",
  capacity: "Capacity", storage_type: "Storage Type", storage_interface: "Interface",
  read_speed: "Read Speed (MB/s)", write_speed: "Write Speed (MB/s)",
  power_draw: "Power Draw (W)", wattage: "Wattage (W)", efficiency_rating: "Efficiency Rating",
  modular_type: "Modularity", max_gpu_length: "Max GPU Length (mm)",
  cooler_type: "Cooler Type", fan_size: "Fan Size", max_tdp: "Max TDP Supported (W)",
  screen_size: "Screen Size", resolution: "Resolution", refresh_rate: "Refresh Rate",
  panel_type: "Panel Type", response_time: "Response Time",
};

const PAGE_SIZE = 12;

const grid = document.getElementById("product-grid");
const stateMessage = document.getElementById("products-state");
const paginationEl = document.getElementById("pagination");
const sidebarBody = document.getElementById("filters-sidebar-body");

const initialParams = new URLSearchParams(window.location.search);
const activeSearch = initialParams.get("search") || "";
const activeCategoryId = initialParams.get("category_id") || "";
const initialBrandId = initialParams.get("brand_id") || "";

let candidatePool = [];
let specFieldNames = [];
let currentPage = 1;
let visibleProducts = [];

const filters = {
  inStock: false,
  outOfStock: false,
  priceMin: null,
  priceMax: null,
  brandIds: new Set(initialBrandId ? [Number(initialBrandId)] : []),
  specs: {},
};

function humanizeSpecField(field) {
  return SPEC_FIELD_LABELS[field] || field;
}

function countActiveFilters() {
  let count = 0;
  if (filters.inStock) count++;
  if (filters.outOfStock) count++;
  if (filters.priceMin !== null) count++;
  if (filters.priceMax !== null) count++;
  count += filters.brandIds.size;
  Object.values(filters.specs).forEach((set) => (count += set.size));
  return count;
}

// Matches a product against every active filter EXCEPT the one named by `excludeKey`.
// Used to compute "what would this option's count be if I picked it" (self-excluding facets),
// the standard faceted-search pattern so counts stay honest as other filters stack up.
function matchesFiltersExcept(product, excludeKey) {
  if (excludeKey !== "availability") {
    if (filters.inStock && !filters.outOfStock && !product.in_stock) return false;
    if (filters.outOfStock && !filters.inStock && product.in_stock) return false;
  }

  if (excludeKey !== "price") {
    if (filters.priceMin !== null && Number(product.price) < filters.priceMin) return false;
    if (filters.priceMax !== null && Number(product.price) > filters.priceMax) return false;
  }

  if (excludeKey !== "brand") {
    if (filters.brandIds.size && (!product.brand || !filters.brandIds.has(product.brand.id))) return false;
  }

  for (const [field, values] of Object.entries(filters.specs)) {
    if (field === excludeKey) continue;
    if (!values.size) continue;
    const value = product.specification ? product.specification[field] : null;
    if (value === null || value === undefined || !values.has(String(value))) return false;
  }

  return true;
}

function productMatchesFilters(product) {
  return matchesFiltersExcept(product, null);
}

async function loadCandidatePool() {
  stateMessage.classList.add("hidden");
  grid.innerHTML = productSkeletonGridHtml(PAGE_SIZE);
  paginationEl.classList.add("hidden");

  const params = new URLSearchParams();
  if (activeSearch) params.set("search", activeSearch);
  if (activeCategoryId) params.set("category_id", activeCategoryId);
  // This page fetches once and does all filtering/pagination client-side for instant
  // faceted search — it needs the whole matching set, not just the API's default page.
  params.set("per_page", "500");

  try {
    const result = await apiRequest(`/products?${params.toString()}`, { auth: false });
    candidatePool = result.data;

    // Spec facets (Socket, Wattage, Screen Size...) only make sense within one category —
    // unioning them across every category at once was turning the sidebar into 40+ sections.
    // Only build them once the candidate pool is already scoped to a single category.
    const fieldSet = new Set();
    if (activeCategoryId) {
      candidatePool.forEach((p) => {
        if (!p.specification) return;
        Object.entries(p.specification).forEach(([field, value]) => {
          // custom_specifications is an array of admin-defined {key, value} pairs, not a
          // single filterable value like every other column here — it can't be faceted the
          // same way, so it's excluded rather than showing up as a broken "field".
          if (field === "custom_specifications") return;
          if (value !== null && value !== undefined && value !== "") fieldSet.add(field);
        });
      });
    }
    specFieldNames = Array.from(fieldSet).sort((a, b) => humanizeSpecField(a).localeCompare(humanizeSpecField(b)));

    buildSidebar();
    applyFilters(1);
  } catch (error) {
    grid.innerHTML = "";
    stateMessage.classList.remove("hidden");
    stateMessage.textContent = error.message || "Could not load products.";
  }
}

function applyFilters(page = 1) {
  currentPage = page;
  visibleProducts = candidatePool.filter(productMatchesFilters);

  const searchBtn = document.getElementById("filters-search-btn");
  const activeCount = countActiveFilters();
  if (searchBtn) searchBtn.textContent = activeCount ? `Search (${activeCount} active)` : "Search";

  if (visibleProducts.length === 0) {
    grid.innerHTML = "";
    paginationEl.classList.add("hidden");
    stateMessage.classList.remove("hidden");
    stateMessage.innerHTML = emptyStateHtml("No products found.");
    return;
  }

  stateMessage.classList.add("hidden");
  renderPage();
}

function renderPage() {
  const start = (currentPage - 1) * PAGE_SIZE;
  const pageItems = visibleProducts.slice(start, start + PAGE_SIZE);
  grid.innerHTML = pageItems.map(renderProductCard).join("");
  renderPagination();
}

function renderPagination() {
  const lastPage = Math.ceil(visibleProducts.length / PAGE_SIZE);
  if (lastPage <= 1) {
    paginationEl.classList.add("hidden");
    return;
  }

  paginationEl.classList.remove("hidden");
  paginationEl.innerHTML = `
    <button type="button" id="prev-page" ${currentPage <= 1 ? "disabled" : ""}>Prev</button>
    <span class="pagination-info">Page ${currentPage} of ${lastPage}</span>
    <button type="button" id="next-page" ${currentPage >= lastPage ? "disabled" : ""}>Next</button>
  `;

  document.getElementById("prev-page").addEventListener("click", () => {
    currentPage--;
    renderPage();
    window.scrollTo({ top: 0, behavior: "smooth" });
  });
  document.getElementById("next-page").addEventListener("click", () => {
    currentPage++;
    renderPage();
    window.scrollTo({ top: 0, behavior: "smooth" });
  });
}

function buildSidebar() {
  const inStockCount = candidatePool.filter((p) => matchesFiltersExcept(p, "availability") && p.in_stock).length;
  const outOfStockCount = candidatePool.filter((p) => matchesFiltersExcept(p, "availability") && !p.in_stock).length;

  const brandPool = candidatePool.filter((p) => matchesFiltersExcept(p, "brand"));
  const brandCounts = new Map();
  brandPool.forEach((p) => {
    if (!p.brand) return;
    const entry = brandCounts.get(p.brand.id) || { id: p.brand.id, name: p.brand.name, count: 0 };
    entry.count++;
    brandCounts.set(p.brand.id, entry);
  });

  // Union of all values ever seen per spec field (across the whole candidate pool), so the
  // option list itself stays fixed — only counts (computed below, self-excluding) change live.
  const specValueUnion = {};
  candidatePool.forEach((p) => {
    if (!p.specification) return;
    specFieldNames.forEach((field) => {
      const value = p.specification[field];
      if (value === null || value === undefined || value === "") return;
      if (!specValueUnion[field]) specValueUnion[field] = new Set();
      specValueUnion[field].add(String(value));
    });
  });

  let html = "";

  if (activeCategoryId) {
    const categoryName = candidatePool[0]?.category?.name;
    html += `
      <div class="filter-section">
        <div class="filter-section-header"><span>Category</span></div>
        <div class="filter-section-body">
          <span class="filter-current-category">${escapeHtml(categoryName || "Selected category")}</span>
        </div>
      </div>
    `;
  }

  html += renderFilterSection(
    "availability",
    "Availability",
    true,
    `
      <label class="filter-checkbox">
        <input type="checkbox" id="filter-in-stock" ${filters.inStock ? "checked" : ""} />
        <span>In Stock</span>
        <span class="filter-count">${inStockCount}</span>
      </label>
      <label class="filter-checkbox">
        <input type="checkbox" id="filter-out-of-stock" ${filters.outOfStock ? "checked" : ""} />
        <span>Out of Stock</span>
        <span class="filter-count">${outOfStockCount}</span>
      </label>
    `
  );

  html += renderFilterSection(
    "price",
    "Price",
    true,
    `
      <div class="filter-price-inputs">
        <input type="number" id="filter-price-min" placeholder="Min" min="0" value="${filters.priceMin ?? ""}" />
        <input type="number" id="filter-price-max" placeholder="Max" min="0" value="${filters.priceMax ?? ""}" />
      </div>
    `
  );

  const brands = Array.from(brandCounts.values()).sort((a, b) => a.name.localeCompare(b.name));
  html += renderFilterSection(
    "brand",
    "Brand",
    true,
    brands
      .map(
        (brand) => `
          <label class="filter-checkbox">
            <input type="checkbox" class="filter-brand-checkbox" value="${brand.id}" ${filters.brandIds.has(brand.id) ? "checked" : ""} />
            <span>${escapeHtml(brand.name)}</span>
            <span class="filter-count">${brand.count}</span>
          </label>
        `
      )
      .join("") || `<p class="state-message">No brands.</p>`
  );

  specFieldNames.forEach((field) => {
    const selected = filters.specs[field] || new Set();
    const specPool = candidatePool.filter((p) => matchesFiltersExcept(p, field));
    const counts = new Map();
    specPool.forEach((p) => {
      const value = p.specification ? p.specification[field] : null;
      if (value === null || value === undefined || value === "") return;
      const key = String(value);
      counts.set(key, (counts.get(key) || 0) + 1);
    });

    const values = Array.from(specValueUnion[field] || []).sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));

    html += renderFilterSection(
      `spec-${field}`,
      humanizeSpecField(field),
      false,
      values
        .map(
          (value) => `
            <label class="filter-checkbox">
              <input type="checkbox" class="filter-spec-checkbox" data-field="${field}" value="${escapeHtml(value)}" ${selected.has(value) ? "checked" : ""} />
              <span>${escapeHtml(value)}</span>
              <span class="filter-count">${counts.get(value) || 0}</span>
            </label>
          `
        )
        .join("")
    );
  });

  sidebarBody.innerHTML = html;
  wireSidebar();
  updateDisabledStates();
}

function renderFilterSection(key, label, expanded, bodyHtml) {
  return `
    <div class="filter-section" data-section="${key}">
      <button type="button" class="filter-section-header">
        <span>${escapeHtml(label)}</span>
        <span class="filter-section-toggle">${expanded ? "−" : "+"}</span>
      </button>
      <div class="filter-section-body ${expanded ? "" : "hidden"}">${bodyHtml}</div>
    </div>
  `;
}

// Live-updates every facet's counts (and disables now-impossible options) without touching
// checked/expanded state, so typing in Price or checking a box never loses focus or resets the accordion.
function refreshCounts() {
  const inStockCount = candidatePool.filter((p) => matchesFiltersExcept(p, "availability") && p.in_stock).length;
  const outOfStockCount = candidatePool.filter((p) => matchesFiltersExcept(p, "availability") && !p.in_stock).length;
  setCheckboxCount(document.getElementById("filter-in-stock"), inStockCount);
  setCheckboxCount(document.getElementById("filter-out-of-stock"), outOfStockCount);

  const brandPool = candidatePool.filter((p) => matchesFiltersExcept(p, "brand"));
  const brandCounts = new Map();
  brandPool.forEach((p) => {
    if (p.brand) brandCounts.set(p.brand.id, (brandCounts.get(p.brand.id) || 0) + 1);
  });
  sidebarBody.querySelectorAll(".filter-brand-checkbox").forEach((checkbox) => {
    setCheckboxCount(checkbox, brandCounts.get(Number(checkbox.value)) || 0);
  });

  specFieldNames.forEach((field) => {
    const specPool = candidatePool.filter((p) => matchesFiltersExcept(p, field));
    const counts = new Map();
    specPool.forEach((p) => {
      const value = p.specification ? p.specification[field] : null;
      if (value === null || value === undefined || value === "") return;
      const key = String(value);
      counts.set(key, (counts.get(key) || 0) + 1);
    });

    sidebarBody.querySelectorAll(`.filter-spec-checkbox[data-field="${field}"]`).forEach((checkbox) => {
      setCheckboxCount(checkbox, counts.get(checkbox.value) || 0);
    });
  });

  const searchBtn = document.getElementById("filters-search-btn");
  const activeCount = countActiveFilters();
  if (searchBtn) searchBtn.textContent = activeCount ? `Search (${activeCount} active)` : "Search";
}

function setCheckboxCount(checkbox, count) {
  if (!checkbox) return;
  const label = checkbox.closest(".filter-checkbox");
  const countEl = label?.querySelector(".filter-count");
  if (countEl) countEl.textContent = count;

  const disabled = count === 0 && !checkbox.checked;
  checkbox.disabled = disabled;
  if (label) label.classList.toggle("filter-checkbox-disabled", disabled);
}

function updateDisabledStates() {
  refreshCounts();
}

function wireSidebar() {
  sidebarBody.querySelectorAll(".filter-section-header").forEach((header) => {
    header.addEventListener("click", () => {
      const body = header.nextElementSibling;
      const toggle = header.querySelector(".filter-section-toggle");
      const isHidden = body.classList.toggle("hidden");
      toggle.textContent = isHidden ? "+" : "−";
    });
  });

  const inStockEl = document.getElementById("filter-in-stock");
  const outOfStockEl = document.getElementById("filter-out-of-stock");
  if (inStockEl) {
    inStockEl.addEventListener("change", () => {
      filters.inStock = inStockEl.checked;
      refreshCounts();
    });
  }
  if (outOfStockEl) {
    outOfStockEl.addEventListener("change", () => {
      filters.outOfStock = outOfStockEl.checked;
      refreshCounts();
    });
  }

  const priceMinEl = document.getElementById("filter-price-min");
  const priceMaxEl = document.getElementById("filter-price-max");
  if (priceMinEl) {
    priceMinEl.addEventListener("input", () => {
      filters.priceMin = priceMinEl.value === "" ? null : Number(priceMinEl.value);
      refreshCounts();
    });
  }
  if (priceMaxEl) {
    priceMaxEl.addEventListener("input", () => {
      filters.priceMax = priceMaxEl.value === "" ? null : Number(priceMaxEl.value);
      refreshCounts();
    });
  }

  sidebarBody.querySelectorAll(".filter-brand-checkbox").forEach((checkbox) => {
    checkbox.addEventListener("change", () => {
      const id = Number(checkbox.value);
      if (checkbox.checked) filters.brandIds.add(id);
      else filters.brandIds.delete(id);
      refreshCounts();
    });
  });

  sidebarBody.querySelectorAll(".filter-spec-checkbox").forEach((checkbox) => {
    checkbox.addEventListener("change", () => {
      const field = checkbox.dataset.field;
      if (!filters.specs[field]) filters.specs[field] = new Set();
      if (checkbox.checked) filters.specs[field].add(checkbox.value);
      else filters.specs[field].delete(checkbox.value);
      refreshCounts();
    });
  });
}

document.getElementById("filters-search-btn").addEventListener("click", () => applyFilters(1));

document.getElementById("filters-reset").addEventListener("click", (event) => {
  event.preventDefault();
  filters.inStock = false;
  filters.outOfStock = false;
  filters.priceMin = null;
  filters.priceMax = null;
  filters.brandIds = new Set();
  filters.specs = {};
  buildSidebar();
  applyFilters(1);
});

loadCandidatePool();
