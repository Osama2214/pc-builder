function setCartBadge(count) {
  const badge = document.getElementById("cart-badge");
  if (!badge) return;
  if (count > 0) {
    badge.textContent = count > 99 ? "99+" : count;
    badge.classList.remove("hidden");
  } else {
    badge.classList.add("hidden");
  }
}

async function refreshCartBadge() {
  try {
    const result = await apiRequest("/cart");
    setCartBadge(result.data.items.length);
  } catch (error) {
    // Not logged in or request failed; leave badge as-is.
  }
}

function injectCartDrawer() {
  if (document.getElementById("cart-drawer-overlay")) return;

  const wrapper = document.createElement("div");
  wrapper.innerHTML = `
    <div class="cart-drawer-overlay hidden" id="cart-drawer-overlay">
      <div class="cart-drawer" id="cart-drawer">
        <div class="cart-drawer-header">
          <div>
            <h3 style="margin:0;">Your Cart</h3>
            <div class="cart-drawer-count" id="cart-drawer-count">0 items</div>
          </div>
          <button type="button" class="modal-close" id="cart-drawer-close">&times;</button>
        </div>
        <div class="cart-drawer-body" id="cart-drawer-body">
          <p class="state-message">Loading...</p>
        </div>
        <div class="cart-drawer-footer">
          <div class="summary-row total">
            <span>Total</span>
            <span id="cart-drawer-total">${formatPrice(0)}</span>
          </div>
          <a href="/checkout.html" class="btn" id="cart-drawer-checkout">Checkout</a>
        </div>
      </div>
    </div>
  `;
  document.body.appendChild(wrapper.firstElementChild);

  document.getElementById("cart-drawer-close").addEventListener("click", closeCartDrawer);
  document.getElementById("cart-drawer-overlay").addEventListener("click", (event) => {
    if (event.target.id === "cart-drawer-overlay") closeCartDrawer();
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") closeCartDrawer();
  });
}

function openCartDrawer() {
  injectCartDrawer();
  document.getElementById("cart-drawer-overlay").classList.remove("hidden");
  requestAnimationFrame(() => document.getElementById("cart-drawer").classList.add("open"));
  loadCartDrawer();
}

function closeCartDrawer() {
  const drawer = document.getElementById("cart-drawer");
  const overlay = document.getElementById("cart-drawer-overlay");
  if (!drawer || !overlay) return;
  drawer.classList.remove("open");
  setTimeout(() => overlay.classList.add("hidden"), 200);
}

async function loadCartDrawer() {
  const bodyEl = document.getElementById("cart-drawer-body");
  const countEl = document.getElementById("cart-drawer-count");
  const totalEl = document.getElementById("cart-drawer-total");
  const checkoutBtn = document.getElementById("cart-drawer-checkout");

  try {
    const result = await apiRequest("/cart");
    const cart = result.data;

    countEl.textContent = `${cart.items.length} item${cart.items.length === 1 ? "" : "s"}`;
    totalEl.textContent = formatPrice(cart.total);
    setCartBadge(cart.items.length);

    if (cart.items.length === 0) {
      bodyEl.innerHTML = emptyStateHtml("Your cart is empty.");
      checkoutBtn.classList.add("hidden");
      return;
    }

    checkoutBtn.classList.remove("hidden");
    bodyEl.innerHTML = cart.items.map(renderCartDrawerItem).join("");

    cart.items.forEach((item) => {
      document.getElementById(`drawer-inc-${item.id}`).addEventListener("click", () => changeDrawerQuantity(item, item.quantity + 1));
      document.getElementById(`drawer-dec-${item.id}`).addEventListener("click", () => changeDrawerQuantity(item, item.quantity - 1));
      document.getElementById(`drawer-remove-${item.id}`).addEventListener("click", () => removeDrawerItem(item.id));
    });
  } catch (error) {
    bodyEl.innerHTML = `<p class="state-message">${escapeHtml(error.message || "Could not load your cart.")}</p>`;
  }
}

function renderCartDrawerItem(item) {
  const thumb = getProductThumbHtml(item.product);

  return `
    <div class="cart-item">
      <div class="cart-item-thumb">${thumb}</div>
      <div class="cart-item-info">
        <div class="cart-item-name">${escapeHtml(item.product.name)}</div>
        <div class="cart-item-price">${formatPrice(item.unit_price)} each</div>
        <div class="qty-stepper">
          <button type="button" id="drawer-dec-${item.id}" ${item.quantity <= 1 ? "disabled" : ""}>−</button>
          <span>${item.quantity}</span>
          <button type="button" id="drawer-inc-${item.id}" ${item.quantity >= item.product.stock ? "disabled" : ""}>+</button>
        </div>
      </div>
      <button type="button" class="cart-item-remove" id="drawer-remove-${item.id}">Remove</button>
    </div>
  `;
}

async function changeDrawerQuantity(item, quantity) {
  try {
    await apiRequest(`/cart/items/${item.id}`, { method: "PATCH", body: { quantity } });
    loadCartDrawer();
  } catch (error) {
    showToast(error.message || "Could not update quantity.", "danger");
  }
}

async function removeDrawerItem(itemId) {
  try {
    await apiRequest(`/cart/items/${itemId}`, { method: "DELETE" });
    loadCartDrawer();
  } catch (error) {
    showToast(error.message || "Could not remove item.", "danger");
  }
}
