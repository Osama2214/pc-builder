# Features

## Customer-Facing

### Browsing & Search

- Homepage with a banner carousel, deals/offers section, and latest products.
- Full-text search (navbar search box) across product names.
- Products listing page with a faceted-search sidebar: availability, price range, brand, and **spec-based filters that are generated dynamically** from whatever specification fields the currently-listed products actually have values for (e.g. browsing GPUs surfaces a "VRAM" filter, browsing CPUs surfaces "Cores"/"Architecture"). Filter counts are self-excluding (checking one facet doesn't zero out the others) and update live as filters are toggled.
- Category navigation via a dropdown menu, populated dynamically from whatever categories exist (adding a new category from the admin panel makes it appear everywhere automatically, with zero frontend code changes needed).

### Product Detail Page

- Image gallery: a large main image with a thumbnail strip below it for products with more than one photo; clicking a thumbnail swaps it into the main spot. Clicking the main image opens it full-size in a lightbox (closable via the × button, clicking outside, or Escape).
- Specification table: first row is always the brand (shown as just the logo if one is uploaded, or the name as text otherwise), then a warranty badge (converted to "N years" when evenly divisible by 12, otherwise "N months") — displayed as a pill under the Buy Now button, not buried in the table — followed by every populated spec field with a human-readable label, plus any admin-defined custom fields. Only fields with an actual value show up; nothing renders as an empty placeholder.
- Sale price handling: shows a strike-through original price and an automatically computed discount percentage badge whenever `sale_price` is set and lower than `price` — no manually-entered discount number anywhere.
- Reviews tab: submit a rating + optional comment (login required), one review per user per product (editing re-submits for re-approval), only admin-approved reviews are shown publicly.
- Comparison tab: pre-seeded with the current product, add up to 5 products total for a side-by-side spec comparison.
- Benchmarks tab: only appears if the product has at least one recorded benchmark result; shows the target name (game/software), resolution/quality context, and the FPS or score result.
- Add to Cart / Add to Wishlist / Buy Now (skips the cart, goes straight to a single-product checkout).

### Cart & Checkout

- Persistent cart per user (not session-based) — quantities are validated against live stock both when adding and at checkout time.
- A slide-out cart drawer accessible from any page via the navbar cart icon.
- Checkout: pick or add a shipping address, choose a payment method, place the order. A cleanly designed confirmation screen shows the real order number, items, address, and total (no generic "thank you" placeholder).

### Build a PC

The centerpiece feature. A build page with one slot per component type: CPU, Motherboard, Memory (RAM), Storage, Power Supply, Graphics Card, CPU Cooler, Case.

- **Part picker**: clicking "+ Choose" on a slot opens a modal locked to that slot's exact category (a CPU picker only ever shows CPUs, never motherboards, even though they share spec fields like "socket").
- **Compatibility-aware filtering**: once a CPU is picked, the motherboard picker automatically filters to matching sockets (and vice versa); same pattern for cooler↔CPU. This mirrors the backend's compatibility checkers so the picker steers the user toward what will actually work, rather than showing everything and only flagging incompatibility after the fact.
- **Live recalculation**: total price, estimated power draw, and overall compatibility status (`compatible` / `incompatible` / `incomplete`) update after every add/remove — no manual "recalculate" step.
- **RAM and storage support multiple items** (different sticks/drives, or increasing quantity of the same one); every other slot holds exactly one item, and picking a replacement swaps it out.
- A build only becomes eligible for checkout once every essential slot (CPU, motherboard, RAM, PSU, storage) is filled.
- **Sharing**: toggle a build public to get a shareable link; the link only resolves while the build stays public.
- **Performance prediction**: pick a benchmark target (game or software) and get an estimate based on the build's GPU (for games) or CPU (for software) benchmark record, if one exists for that component.
- Buying a complete build creates a real order in one step, independent of whatever's currently in the regular cart.

### Account

- Register / login (Sanctum token stored client-side).
- Profile page, order history, saved/shared builds list, wishlist, saved addresses (with one default enforced automatically).
- "Buy Again" button on a past order re-adds all its items to the current cart in one click.

## Admin Panel

### Dashboard

Live stats pulled from real aggregate queries (not cached/stale numbers): total revenue (excluding cancelled orders), order counts by status, pending review count, product stock health (active/low-stock/out-of-stock), customer count, category/brand counts, and a list of the 5 most recent orders. Every management tile (Products, Orders, Reviews, etc.) carries a live badge count for anything needing attention.

### Products

- Add/Edit form with an image uploader (drag-in via a styled file picker, recommended-size hint shown, multiple images per product, one designated primary) that only becomes available after the product's basic info is first saved.
- **Category-aware specification fields**: switching the category dropdown live-swaps which spec fields appear, so adding a GPU never shows CPU-only fields like "Cores". This mapping is category-ID-based specifically so renaming a category from the Categories page can never silently break it.
- **Custom specifications**: a free-form add-any-field section, always available regardless of category, for anything the fixed field set doesn't anticipate — including brand-new categories that have no predefined mapping yet.
- **Benchmarks section**: attach performance results (FPS for games, score+unit for software) to the product, choosing from the shared Benchmark Targets catalog; the relevant result field (FPS vs. score) toggles automatically based on the selected target's type.
- List page with filters: search, category, brand, status (active/inactive), stock (in stock/low stock/out of stock), plus a one-click Activate/Deactivate toggle per row without needing to open the edit page.

### Categories, Brands, Benchmark Targets

Simple CRUD list+form pages, each with delete protection against orphaning references (can't delete a category/brand/benchmark-target that's still referenced by a product or benchmark record). Brands support either uploading a logo image or pasting an external URL — whichever is provided is used; leaving both blank while editing keeps the existing logo untouched.

### Banners

Manage the homepage carousel: upload an image, optional link URL, sort order, active/inactive toggle.

### Reviews

Queue of pending (unapproved) reviews with a one-click approve action; reviews only appear on the product page once approved here.

### Orders

Full order list with search (order number or customer name/email), status filter, and payment-status filter. Each order's detail page (shared with the customer-facing order view, but with extra admin-only controls) lets an admin change order status and payment status freely, with stock automatically adjusted when an order moves into or out of `cancelled`.
