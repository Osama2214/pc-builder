function renderProductCard(product) {
  const hasSale = product.sale_price && Number(product.sale_price) < Number(product.price);
  const priceHtml = hasSale
    ? `<span class="price">${formatPrice(product.sale_price)}</span>
       <span class="price-strike">${formatPrice(product.price)}</span>`
    : `<span class="price">${formatPrice(product.price)}</span>`;

  const stockBadge = product.in_stock
    ? `<span class="badge badge-success">In stock</span>`
    : `<span class="badge badge-danger">Out of stock</span>`;

  const discountPercent = getDiscountPercent(product);
  const discountBadge = discountPercent ? `<span class="discount-badge">-${discountPercent}%</span>` : "";

  const thumbHtml = getProductThumbHtml(product);

  return `
    <div class="product-card">
      <a class="product-card-link" href="product.html?id=${product.id}">
        <div class="product-thumb">${discountBadge}${thumbHtml}</div>
        <div class="product-card-body">
          <div class="product-card-meta">${escapeHtml(product.brand?.name ?? "")}</div>
          <div class="product-card-name">${escapeHtml(product.name)}</div>
          <div>${stockBadge}</div>
          <div class="product-card-price-row">${priceHtml}</div>
        </div>
      </a>
    </div>
  `;
}
