# PC Builder — Project Status Report

**Status:** Fully built and functional — backend API, admin panel, and customer-facing storefront are all implemented and working end-to-end.

> This replaces an earlier version of this report written before any code existed (originally dated during the planning phase). Every item that report listed as "not started" has since been built; this version reflects the actual current state of the codebase.

---

## 1. Backend

- **Database:** 20 migrations covering users, categories, brands, benchmark targets, addresses, products, product specifications (extended well past its original column set to cover CPU/motherboard/GPU/RAM/storage/PSU/cooler/case/monitor fields, plus a free-form JSON field for anything else), product images, benchmarks, carts, cart items, orders, order items, wishlists, reviews, builds, build items, banners, and Sanctum's personal access tokens.
- **Models:** every table has an Eloquent model with its relationships and relevant query scopes.
- **Auth:** Laravel Sanctum, token-based, with register/login/logout/me endpoints and an `admin` middleware gate for admin-only routes.
- **Core CRUD:** Categories, Brands, Products (with image upload and per-category specifications), Benchmark Targets, Banners — all with admin-only write access and public read access.
- **Cart / Wishlist / Orders / Reviews / Addresses:** fully implemented with their business rules (stock checks, default-address juggling, historical order pricing, review approval flow).
- **Build System + Compatibility Engine:** `BuildService` handles slot management and automatic recalculation; `CompatibilityService` runs six independent checkers (CPU↔Motherboard, RAM↔Motherboard, Cooler↔CPU, GPU↔PSU, GPU↔Case, Storage↔Motherboard) after every build change. Build sharing (public link) and a full build checkout flow (separate from the regular cart) are both working.
- **Benchmarks + Compare:** admins can attach FPS/score results to any product against a shared catalog of benchmark targets; a build's expected performance on a given target can be estimated from its GPU or CPU's own recorded benchmark. Product and build comparison endpoints support 2–5 items side by side.
- **Admin Dashboard:** a dedicated stats endpoint (revenue, order/product/review counts, recent orders) backing a real admin dashboard.
- **AI Chat Assistant:** `POST /api/ai/chat` (`AiChatController` + `AiChatService`), backed by an LLM via OpenRouter. Answers strictly from the actual product catalog (no invented products/prices), checks part compatibility the same way the build page does, and can call tools to add items to the cart or assemble/save a build directly from the conversation.

## 2. Frontend

- **Storefront pages:** homepage, product listing (with a dynamic, faceted-search filter sidebar), product detail (image gallery + lightbox, spec table, warranty badge, reviews, comparison, benchmarks), cart, checkout, order history/detail, wishlist, profile, login/register.
- **Build a PC:** a full build page with a category-locked, compatibility-aware part picker, live price/power/compatibility recalculation, RAM/storage multi-item support, sharing, and a dedicated build checkout page.
- **Admin panel:** dashboard, products (with category-aware spec fields, custom free-form fields, image management, and per-product benchmark entries), categories, brands (with logo upload or external URL), banners, benchmark targets, reviews moderation, and order management with search/status filters and inline activate/deactivate toggles.
- **Shared infrastructure:** a single `apiRequest()` wrapper, custom-styled replacements for native `alert()`/`confirm()`/`<select>`/file inputs (matching the site's dark theme instead of default browser chrome), a shared cart drawer, a shared navbar with a dynamically populated categories menu, and a floating AI chat widget (`js/ai-chat.js`) present on every page.

## 3. Deployed

Live on free tiers: Render (backend, Docker) + Vercel (frontend) + Neon (Postgres) + Cloudflare R2 (uploaded images). See [docs/deploy-free.md](docs/deploy-free.md) and the links in [README.md](README.md).

## 4. Not Currently Planned

- Guest checkout, verified-purchase reviews, additional user roles — all deliberately out of scope (see [docs/business-rules.md](docs/business-rules.md) and [docs/requirements.md](docs/requirements.md)).
- Automated test suite (PHPUnit is available in the project but no tests have been written).

## 5. Where to Look

| Area | Reference |
|---|---|
| How to run it locally | [docs/setup.md](docs/setup.md) |
| How the live deployment works | [docs/deploy-free.md](docs/deploy-free.md) |
| How it's built | [docs/architecture.md](docs/architecture.md) |
| Every database table | [docs/erd.md](docs/erd.md) |
| Every API endpoint | [docs/api.md](docs/api.md) |
| Every feature, in detail | [docs/features.md](docs/features.md) |
| The rules behind the features | [docs/business-rules.md](docs/business-rules.md) |
