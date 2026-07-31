# PC Builder — Business Rules

The reference for every rule a Service class must enforce. Any rule listed here belongs in the Service layer — never left to the Controller or the Frontend to enforce on its own.

---

## 1. Products

- A product with `is_active = false` never appears in Browse/Search for guests or regular users, and can't be added to a Cart or a Build — but an admin viewing the same endpoints still sees it (used for the admin Products list and the product edit page).
- A product with `stock = 0` still shows up (marked "Out of Stock") but can't be added to a cart.
- `sale_price`, when set, must be less than `price` — enforced at validation time on both create and update.
- Deleting a product is a **soft delete** (not a hard delete), specifically so historical `order_items` referencing it stay intact and readable.
- Specification fields live on a single wide `product_specifications` table shared across every category (not one table per category, not EAV). A product only fills in the columns relevant to its own category; the rest stay `null`.
- Which spec fields the **admin form** shows for a given product is decided by the product's **category ID**, not its name or slug — both of the latter are editable from the Categories page and would silently break the field mapping if used as the key.
- Anything not covered by the fixed spec columns can be recorded as a free-form key/value pair in `custom_specifications` (JSON), on any category, including ones with no predefined field mapping yet.

## 2. Cart

- A product that's inactive or out of stock cannot be added to the cart.
- Adding a product already in the cart increases its `quantity` instead of inserting a new row (enforced by a `cart_id` + `product_id` unique constraint).
- The requested quantity in the cart must never exceed `stock` — checked both when adding/updating and again at checkout time, since stock can change between the two moments.

## 3. Orders

- An order cannot be placed if the requested quantity of any item exceeds the stock available at execution time (re-checked at checkout, not just at add-to-cart time).
- Placing an order deducts `stock` immediately (prevents overselling between concurrent checkouts — done inside a DB transaction with row locking).
- Cancelling an order (`cancelled`) restores the stock for every line item.
- An admin can also move an order status *out of* `cancelled` back into another state — this re-deducts stock, and fails if the stock is no longer available by then.
- `order_items.price` is recorded at the moment of purchase and never updated afterward, even if the product's price changes later (historical pricing).
- Every order must be linked to a valid `address_id` belonging to the same user who placed it.
- An order can optionally be linked to a `build_id` (nullable) — set only when the order came from a "buy this build" checkout rather than the regular cart, so admins/customers can tell the two purchase paths apart.

## 4. Reviews

- The user must be logged in to submit a review.
- One review per user per product (enforced by a unique constraint) — submitting again updates the existing review instead of creating a second one.
- A review is always `is_approved = false` by default (on both creation and any edit) until an admin approves it; only approved reviews show on the product page.
- **Deferred to a future version:** requiring a verified purchase before a review can be submitted. Not implemented in the current version — any logged-in user can review any product.

## 5. Wishlist

- A product can only appear once per user in the wishlist (unique constraint on `user_id` + `product_id`).

## 6. Build System

- A build holds **exactly one item** in each of: CPU, Motherboard, GPU, Cooler, Case, PSU (single-instance slots) — picking a new component for an already-filled slot replaces the old one rather than adding alongside it.
- A build can hold **more than one item** in RAM and Storage (multi-instance slots) — via `quantity` on the same product, or separate rows for different products in the same slot.
- Every add/remove/quantity-change on any slot triggers an immediate recalculation of: `total_price`, `estimated_power`, and `compatibility_status`.
- A build's status is automatically `draft` unless every essential slot is filled (at minimum: CPU + Motherboard + RAM + PSU + Storage), in which case it becomes `complete`.
- A build only ever becomes `purchased` after a real order covering all of its components is completed through the build checkout flow — never just by filling every slot.
- Sharing a build (`share_token`) only resolves publicly while `is_public = true`. If a build is later made private again, its share link returns 404 even if someone still has the URL — `is_public` is the single source of truth, checked on every request to the shared-view endpoint, not just at token-generation time.

## 7. Compatibility Engine

- All compatibility logic lives in `CompatibilityService` plus one independent Checker class per component pair — there is no `compatibility_rules` database table.
- The check runs automatically after every change to any slot, not only when the user explicitly finishes the build.
- If an essential component is still missing (e.g. no motherboard picked yet), checks between the *other* present components still run normally, and the missing component's checks are simply skipped — a missing component is never treated as an incompatibility.
- The overall result (`compatibility_status`) is one of: `compatible`, `incompatible`, `incomplete`.
- Currently implemented checkers: CPU↔Motherboard (socket), RAM↔Motherboard (RAM type + slot count), Cooler↔CPU (socket support list), GPU↔PSU (wattage vs. estimated draw with headroom), GPU↔Case (GPU length vs. case's max supported length), Storage↔Motherboard (interface support).

## 8. Users & Roles

- `role` is either `user` or `admin` in the current version (no separate Vendor/Moderator roles).
- Only an admin can perform CRUD on: Products, Categories, Brands, Benchmarks, Benchmark Targets, Banners — and can approve reviews and manage order fulfillment.
- A guest may browse/search/view products, categories, and brands freely; any mutating action (Cart, Wishlist, Build, Order, Review) requires being logged in first.

## 9. Addresses

- A user may have multiple addresses, but exactly one `is_default = true` at any given time — creating a new default address automatically un-defaults the previous one. The very first address a user ever adds is always forced to default, even if not explicitly requested.
- Deleting the current default address automatically promotes another remaining address (if any) to default, so a user is never left with zero default addresses while they still have at least one saved.
- An address that's referenced by an existing order cannot be deleted.

## 10. Benchmarks

- Benchmark data is entered by an admin only (not pulled live from any external source in the current version).
- A benchmark result needs `fps` if its target's `type` is `game`, or `score` (with an optional `unit`) if the target's `type` is `software` — enforced on creation.
- A `benchmark_target` cannot be deleted while it still has benchmark records attached to it.

## 11. AI Chat Assistant

- The assistant only knows about products actually present in the catalog it's given (the active product list, sent fresh on every request) — it must never recommend, price, or invent anything outside of it, and must say plainly when something isn't carried.
- Mutating actions (add to cart, create a build) require a real logged-in user; a guest asking for one of those gets told to log in instead — the same `add_to_cart`/`create_build` tools are never invoked for a guest.
- A build the assistant proposes must pass the same compatibility checks as the manual build page (socket, RAM type, PSU wattage, GPU/case clearance) before being presented, re-verified as the last step even if a part was swapped to fit budget.

---

## Resolved Decisions

1. **Verified purchase on reviews:** ❌ Not in the current scope. Deferred (would need a check against a completed order containing the product).
2. **Build sharing:** `share_token` only resolves when `is_public = true` — confirmed and implemented exactly this way.
3. **Guest checkout:** ❌ Disallowed. An account is required (avoids guest-order/session-cart merge complexity).
4. **Soft deletes:** `products` only. `orders` and `users` are hard-delete-capable (though nothing in the app currently deletes a user or order outright).
5. **API design:** Fully RESTful (`GET /api/products`, `POST /api/products`, ...), not action-based URLs.
6. **Authentication:** Laravel Sanctum from the start, even with the current plain HTML/JS frontend — keeps the door open for a SPA or mobile client later with zero backend changes.
7. **Compatibility logic placement:** Entirely inside `CompatibilityService` and its Checkers. Controllers call the Service; the Service reads Models. No compatibility logic is allowed inside a Controller.
8. **Category-specific admin fields:** Keyed by category **ID** in the frontend (not name/slug), specifically because both of the latter are editable from the Categories admin page and would otherwise silently disconnect a category from its intended spec fields (or the build page's part-picker slot mapping) the moment someone renames it.
