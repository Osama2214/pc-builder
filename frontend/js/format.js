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
