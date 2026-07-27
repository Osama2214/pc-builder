# Project Scope

This is a summary of what the project set out to be. For what actually exists today in full detail, see [features.md](features.md) (functionality) and [business-rules.md](business-rules.md) (the rules behind it) — both are kept current; this file describes the original intent.

## Core Idea

Not a generic e-commerce store — a platform built around **helping a user assemble a compatible PC build**, where buying individual components is a supporting feature rather than the main point.

## User Types

- **Guest** — browse, search, and view products/categories/brands freely. No mutating action (cart, wishlist, build, order, review) without an account.
- **Registered user** — everything a guest can do, plus: cart, wishlist, saved addresses, order history, building and purchasing PC configurations, submitting reviews.
- **Admin** — full catalog management (products, categories, brands, banners), benchmark data entry, review moderation, and order fulfillment.

## Must-Have Areas

1. **Storefront** — browsing, search, filtering, product detail, cart, checkout, order history.
2. **Build System** — a dedicated page to assemble a PC part by part, with live compatibility checking between components (not deferred to "check at the end").
3. **Compatibility Engine** — a real rules engine (socket matching, RAM support, PSU headroom, GPU/case clearance, storage interface support), not a lookup table of pre-approved combinations.
4. **Admin Panel** — a working back office for every entity above, not just database access via a raw admin tool.
5. **Benchmarks & Compare** — the ability to attach real performance data to a product and use it to estimate a whole build's expected performance, plus side-by-side comparison of products or builds.

## Explicitly Out of Scope (for now)

- Guest checkout (an account is always required to purchase).
- Verified-purchase requirement on reviews.
- Any role beyond `user` / `admin` (no vendor/moderator tier).
- Live/external benchmark data sources — all benchmark numbers are admin-entered.
- A build page slot for peripherals like monitors — monitors exist as a browsable product category, but aren't part of the Build a PC slot list.
