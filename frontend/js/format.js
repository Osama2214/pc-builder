function formatPrice(value) {
  const amount = Number(value) || 0;
  return new Intl.NumberFormat("en-EG", {
    style: "currency",
    currency: "EGP",
    maximumFractionDigits: 2,
  }).format(amount);
}

// Always derived from price vs sale_price — never a manually-entered value — so it can
// never drift out of sync if either price is edited.
function getDiscountPercent(product) {
  if (!product) return null;
  const price = Number(product.price);
  const salePrice = Number(product.sale_price);
  if (!salePrice || !price || salePrice >= price) return null;
  return Math.round(((price - salePrice) / price) * 100);
}

function escapeHtml(value) {
  const div = document.createElement("div");
  div.textContent = value ?? "";
  return div.innerHTML;
}

// product.thumbnail is a separate, rarely-set field — the real product photos
// live in product.images (uploaded via the admin panel). Prefer the one marked
// primary, then any image, then the legacy thumbnail field, then nothing.
function getProductImageUrl(product) {
  if (!product) return null;
  const images = product.images;
  if (Array.isArray(images) && images.length > 0) {
    const primary = images.find((img) => img.is_primary);
    return (primary || images[0]).url;
  }
  return product.thumbnail || null;
}

// A single tasteful "no photo yet" placeholder — used everywhere a product thumbnail
// would otherwise just be the bare words "No image" against an empty background.
const PRODUCT_IMAGE_PLACEHOLDER_HTML = `
  <div class="product-image-placeholder">
    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
      <rect x="3" y="3" width="18" height="18" rx="2"></rect>
      <circle cx="8.5" cy="8.5" r="1.5"></circle>
      <path d="M21 15l-5-5L5 21"></path>
    </svg>
    <span>No image yet</span>
  </div>
`;

// Returns ready-to-insert HTML: either the real <img>, or the shared placeholder —
// so every page shows the same polished placeholder instead of each rolling its own.
function getProductThumbHtml(product, extraAttrs = "") {
  const imageUrl = getProductImageUrl(product);
  return imageUrl
    ? `<img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(product?.name ?? "")}" ${extraAttrs} />`
    : PRODUCT_IMAGE_PLACEHOLDER_HTML;
}
