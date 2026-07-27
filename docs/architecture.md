# Architecture

## High-Level Overview

The project is a classic **decoupled API + static frontend** setup — there's no shared process, no server-side rendering, no build tooling on either side.

```
┌─────────────────────────┐         ┌──────────────────────────┐
│  frontend/ (plain HTML) │  fetch  │  backend/ (Laravel API)  │
│  served by any static   │ ──────► │  SQLite database         │
│  file server, port 5501 │  JSON   │  port 8000               │
└─────────────────────────┘         └──────────────────────────┘
``` 

The frontend never talks to the database directly and holds no business logic beyond form validation feedback and display formatting — every real rule (stock checks, compatibility checks, pricing, order status transitions) lives in the backend.

## Backend

### Layering

The backend follows a strict **Controller → Service → Model** layering:

- **Controllers** (`app/Http/Controllers/Api/*`) only orchestrate: validate the request (via Form Requests), call a Service, wrap the result in a Resource. They never contain business rules directly.
- **Services** (`app/Services/*`) hold all business logic — stock deduction, order number generation, address default-flag juggling, build slot management, compatibility checking. Anything with an actual rule to enforce lives here, not in the controller.
- **Models** (`app/Models/*`) are just Eloquent models with relationships and scopes — no business logic beyond simple query scopes (e.g. `Product::active()`).

Supporting layers:

- **Form Requests** (`app/Http/Requests/*`) — all input validation, grouped per resource and per action (Store/Update request classes are separate since update rules are often looser).
- **Resources** (`app/Http/Resources/*`) — every API response is shaped through a Resource class, so the JSON contract is explicit and decoupled from the raw database columns.

### The Compatibility Engine

This is the part of the backend that's specific to this project's whole reason for existing. `CompatibilityService` (`app/Services/CompatibilityService.php`) runs a build's items through a list of independent **Checkers** (`app/Services/Compatibility/*Checker.php`), one per component pair:

- `CpuMotherboardChecker` — socket match
- `RamMotherboardChecker` — RAM type + slot count
- `CoolerCpuChecker` — cooler socket support (comma-separated list on one column)
- `GpuPsuChecker` — PSU wattage vs. estimated draw (20% headroom)
- `GpuCaseChecker` — GPU length vs. case's max supported length
- `StorageMotherboardChecker` — storage interface (SATA/NVMe/M.2) support

Each checker returns `true` (compatible), `false` (incompatible), or `null` (not enough data to check — e.g. one of the two components isn't present yet, or a spec field is missing). A build's overall `compatibility_status` is `incompatible` if any checker returns `false`, `compatible` if at least one checker actually ran and passed, and `incomplete` if nothing could be checked yet. This runs automatically after every add/remove/quantity-change on a build — there's no separate "check now" button.

There is no `compatibility_rules` database table — compatibility is pure code, not data, by deliberate design decision (see `docs/business-rules.md`).

### Wide Specification Table

Rather than a separate table per product category or an EAV (entity-attribute-value) model, all product specs live in one wide table: `product_specifications`. Every category's fields are just more nullable columns on the same row (a CPU product uses `socket`/`cores`/`threads`, a monitor uses `screen_size`/`refresh_rate`, and so on) — a given product only ever fills in the columns relevant to its own category, the rest stay `null`. On top of the fixed columns, there's also a `custom_specifications` JSON column for admin-defined free-form key/value pairs that don't fit any predefined field, covering any category the fixed schema doesn't anticipate.

### Storage

Uploaded images (product photos, banners, brand logos) are stored via Laravel's `Storage::disk('public')`, under `storage/app/public/{products,banners,brands}`, and served through the `storage/` symlink created by `php artisan storage:link`. A brand's logo can alternatively be a raw external URL pasted in directly instead of an upload — the resource layer detects which case it is (checks for an `http(s)://` prefix) before deciding whether to resolve it through the disk or return it as-is.

### Auth

Laravel Sanctum, token-based (not session cookies) — every authenticated request sends `Authorization: Bearer {token}`. Two guard levels: `auth:sanctum` (any logged-in user) and `auth:sanctum` + a custom `admin` middleware (checks `role === 'admin'`). A handful of routes resolve the user manually via `$request->user('sanctum')` without the middleware, specifically so the same endpoint can serve both guests and logged-in users differently (e.g. showing inactive products only to an admin browsing the public product list).

## Frontend

### No Build Step, By Design

Every page is a standalone `.html` file with inline `<script>` at the bottom. Shared behavior lives in small JS files under `frontend/js/`, loaded via plain `<script src="...">` tags in a fixed order on every page that needs them:

| File | Responsibility |
|---|---|
| `api.js` | The single `apiRequest()` wrapper around `fetch` — attaches the auth token, handles JSON vs. FormData bodies, normalizes errors into `ApiError` |
| `auth.js` | Reads/writes the token and user object in `localStorage`, plus `Auth.requireAuth()` / `Auth.requireAdmin()` client-side redirects |
| `nav.js` | Renders the shared navbar (search, categories dropdown, cart badge, profile menu) on every page |
| `format.js` | Shared formatting helpers (`formatPrice`, `escapeHtml`, `getProductImageUrl`, `getDiscountPercent`) |
| `product-card.js` | The one shared product-card renderer used by the homepage, products listing, and related-products section |
| `product-filters.js` | The faceted-search sidebar on the products listing page (spec-based filters, self-excluding facet counts) |
| `custom-select.js` | Progressively enhances every native `<select>` on the site into a themed dropdown, without touching any call site that reads/sets `.value` |
| `toast.js` / `confirm-dialog.js` | Custom-styled replacements for `alert()` / `confirm()`, both promise-based |
| `cart-drawer.js` | The slide-out cart drawer, shared across every page |
| `form-helpers.js` | Generic form utilities: clearing/displaying validation errors, button loading states |
| `build-slots.js` | The abstract list of build slots (`cpu`, `motherboard`, `ram`, ...) shared between the build page and the build-view/checkout pages |

### Pages

Root-level pages (`frontend/*.html`) are the customer-facing storefront: homepage, product listing with filters, product detail, cart, checkout, build-a-PC (with its part picker and compatibility-aware filtering), build view/checkout, compare, orders, wishlist, profile, login/register.

`frontend/admin/*.html` is the admin panel: dashboard with live stats, products (with category-aware spec fields and per-product benchmark management), categories, brands, banners, benchmark targets, reviews moderation, and order management with filters.

### Category-Aware Admin Forms

The product admin form (`admin/product-edit.html`) shows only the specification fields relevant to the product's category (e.g. a GPU shows VRAM/length/video ports, a PSU shows wattage/efficiency/modularity) instead of one giant list of every possible field. This mapping is keyed by **category ID**, not name or slug — both of those are editable from the Categories admin page and would silently break the mapping if used as the key, since an ID never changes once a category is created.

### Progressive Enhancement Pattern

A recurring pattern across the frontend: native browser elements (`<select>`, `<input type="file">`) are visually re-skinned via a wrapper + hidden native element, while the native element stays the single source of truth for reads/writes/events. This keeps every existing call site working unchanged (`select.value = x`, `.addEventListener('change', ...)`, `form.reset()`) while the visible UI matches the site's dark theme instead of the browser's native styling.
