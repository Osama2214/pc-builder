# API Reference

Base URL: `http://127.0.0.1:8000/api` (see `frontend/js/api.js`'s `API_BASE_URL`).

Auth: Bearer token via Laravel Sanctum, sent as `Authorization: Bearer {token}`. Three access levels are used below:

- **Public** — no token needed.
- **Auth** — any logged-in user (`auth:sanctum` middleware).
- **Admin** — logged-in user with `role = admin` (`auth:sanctum` + custom `admin` middleware).

A few endpoints are marked **Public\*** — they don't require a token, but behave differently if one is sent (e.g. an admin token reveals inactive products that guests can't see).

## Auth

| Method | Path | Access | Description |
|---|---|---|---|
| POST | `/register` | Public | Create an account (always `role: user`), returns a token |
| POST | `/login` | Public | Returns a token on valid credentials |
| GET | `/user` | Auth | Current logged-in user |
| POST | `/logout` | Auth | Revokes the current token |

## Categories

| Method | Path | Access | Description |
|---|---|---|---|
| GET | `/categories` | Public | List all categories |
| GET | `/categories/{id}` | Public | Single category |
| POST | `/categories` | Admin | Create |
| PUT/PATCH | `/categories/{id}` | Admin | Update |
| DELETE | `/categories/{id}` | Admin | Delete — rejected (409) if the category still has products |

## Brands

| Method | Path | Access | Description |
|---|---|---|---|
| GET | `/brands` | Public | List all brands |
| GET | `/brands/{id}` | Public | Single brand |
| POST | `/brands` | Admin | Create — `logo` accepts either an uploaded image file or a `logo_url` string field |
| POST | `/brands/{id}` (with `_method=PUT` spoofed) | Admin | Update — same logo handling; a new file replaces and deletes the old one |
| DELETE | `/brands/{id}` | Admin | Delete — rejected (409) if the brand still has products; also deletes its uploaded logo file |

## Products

| Method | Path | Access | Description |
|---|---|---|---|
| GET | `/products` | Public\* | List/search products. Query params: `search`, `category_id`, `brand_id`, `is_active` (admin only, filters active/inactive), `stock_status` (`in_stock`/`low_stock`/`out_of_stock`) |
| GET | `/products/{id}` | Public\* | Single product with specification, images, category, brand — 404 if inactive and viewer isn't an admin |
| POST | `/products` | Admin | Create, with an optional `specifications` object nested in the body |
| PUT/PATCH | `/products/{id}` | Admin | Update |
| DELETE | `/products/{id}` | Admin | Soft delete |
| POST | `/products/{id}/images` | Admin | Upload one or more images (`images[]` in multipart body) |
| DELETE | `/products/{id}/images/{imageId}` | Admin | Delete one image |
| PATCH | `/products/{id}/images/{imageId}/primary` | Admin | Set as the primary image |

## Reviews

| Method | Path | Access | Description |
|---|---|---|---|
| GET | `/products/{id}/reviews` | Public | Approved reviews only, paginated |
| POST | `/products/{id}/reviews` | Auth | Create or edit (one review per user per product) — always resets to unapproved |
| DELETE | `/reviews/{id}` | Auth | Owner or admin |
| GET | `/reviews/pending` | Admin | All unapproved reviews |
| PATCH | `/reviews/{id}/approve` | Admin | Approve |

## Cart

| Method | Path | Access | Description |
|---|---|---|---|
| GET | `/cart` | Auth | Current user's cart with items |
| POST | `/cart/items` | Auth | Add a product (merges into existing quantity if already present) |
| PATCH | `/cart/items/{itemId}` | Auth | Update quantity |
| DELETE | `/cart/items/{itemId}` | Auth | Remove item |

## Wishlist

| Method | Path | Access | Description |
|---|---|---|---|
| GET | `/wishlist` | Auth | Current user's wishlist |
| POST | `/wishlist` | Auth | Add a product |
| DELETE | `/wishlist/{productId}` | Auth | Remove |

## Addresses

| Method | Path | Access | Description |
|---|---|---|---|
| GET | `/addresses` | Auth | Current user's addresses |
| POST | `/addresses` | Auth | Create — the first address a user adds is always forced to default |
| PATCH | `/addresses/{id}` | Auth | Update — promoting to default unsets any other default |
| DELETE | `/addresses/{id}` | Auth | Delete — rejected (409) if used by an existing order; auto-promotes another address to default if this one was it |

## Orders

| Method | Path | Access | Description |
|---|---|---|---|
| GET | `/orders` | Auth | Current user's orders |
| POST | `/orders` | Auth | Checkout the current cart into a new order |
| GET | `/orders/{id}` | Auth | Owner or admin |
| POST | `/orders/{id}/cancel` | Auth | Owner or admin — only from `pending`/`processing`; restores stock |
| GET | `/admin/orders` | Admin | All orders. Query params: `search` (order number or customer name/email), `status`, `payment_status` |
| PATCH | `/orders/{id}/status` | Admin | Free-form status change — adjusts stock automatically when moving into/out of `cancelled` |
| PATCH | `/orders/{id}/payment-status` | Admin | Payment status only |

## Builds

| Method | Path | Access | Description |
|---|---|---|---|
| GET | `/builds` | Auth | Current user's builds |
| POST | `/builds` | Auth | Create a new (empty) build |
| GET | `/builds/{id}` | Auth | Owner or admin |
| PATCH | `/builds/{id}` | Auth | Owner — e.g. rename |
| DELETE | `/builds/{id}` | Auth | Owner |
| POST | `/builds/{id}/items` | Auth | Add/replace a component in a slot |
| PATCH | `/builds/{id}/items/{itemId}` | Auth | Change quantity (RAM/storage slots only) |
| DELETE | `/builds/{id}/items/{itemId}` | Auth | Remove a component |
| POST | `/builds/{id}/share` | Auth | Toggle `is_public` (and generate a `share_token` if turning it on) |
| POST | `/builds/{id}/checkout` | Auth | Turn a `complete` build into an order in one step (bypasses the cart) |
| GET | `/builds/shared/{token}` | Public | View a build via its share link — 404 unless `is_public` is true |
| GET | `/builds/{id}/predict` | Public\* | Estimate performance on a benchmark target, based on the build's GPU (for game targets) or CPU (for software targets) benchmark record |

## Benchmark Targets

| Method | Path | Access | Description |
|---|---|---|---|
| GET | `/benchmark-targets` | Public | List all (games/software catalog) |
| GET | `/benchmark-targets/{id}` | Public | Single target |
| POST | `/benchmark-targets` | Admin | Create |
| PUT/PATCH | `/benchmark-targets/{id}` | Admin | Update |
| DELETE | `/benchmark-targets/{id}` | Admin | Rejected (409) if it still has benchmark records attached |

## Benchmarks (product ↔ target link)

| Method | Path | Access | Description |
|---|---|---|---|
| GET | `/products/{id}/benchmarks` | Public | All benchmark results recorded for a product |
| POST | `/products/{id}/benchmarks` | Admin | Attach a result — `fps` required for `game` targets, `score` required for `software` targets |
| PATCH | `/benchmarks/{id}` | Admin | Update a result |
| DELETE | `/benchmarks/{id}` | Admin | Remove |

## Compare

| Method | Path | Access | Description |
|---|---|---|---|
| GET | `/compare/products?ids=1,2,3` | Public\* | Compare 2–5 products side by side |
| GET | `/compare/builds?ids=1,2` | Public\* | Compare 2–5 builds — only public builds or the requester's own are returned |

## Banners

| Method | Path | Access | Description |
|---|---|---|---|
| GET | `/banners` | Public\* | Active banners only for guests; admins see inactive ones too |
| POST | `/banners` | Admin | Upload a new banner image |
| PATCH | `/banners/{id}` | Admin | Update (e.g. toggle active) |
| DELETE | `/banners/{id}` | Admin | Delete — also removes the uploaded image file |

## AI Chat

| Method | Path | Access | Description |
|---|---|---|---|
| POST | `/ai/chat` | Public\* | Chat with the AI assistant (`throttle:ai-chat`) |

Request body:

```json
{
  "message": "Build me a gaming PC for 30000 EGP",
  "history": [{ "role": "user", "text": "..." }, { "role": "assistant", "text": "..." }]
}
```

`message` is required (max 2000 chars); `history` is optional (max 20 entries, each `role` is `user` or `assistant`).

Response (`AiChatService`, wrapped in `{ "data": ... }`):

```json
{
  "reply": "Here's a build that fits your budget...",
  "action": null
}
```

`action` is `null` for a plain answer, or `{ "type": "add_to_cart" | "create_build", "result": {...} }` when the assistant actually mutated data (only possible for a logged-in user — a guest gets a reply telling them to log in instead). The assistant answers strictly from the live product catalog passed in its system prompt — it never invents products or prices, and it re-verifies part compatibility (socket, RAM type, PSU wattage, GPU/case clearance) before presenting any build.

## Admin Dashboard

| Method | Path | Access | Description |
|---|---|---|---|
| GET | `/admin/stats` | Admin | Aggregate counts: product stock health, order statuses + revenue, pending reviews, category/brand/customer counts, 5 most recent orders |

## Error Format

Validation and business-rule errors return a consistent shape:

```json
{
  "message": "Human-readable summary",
  "errors": {
    "field_name": ["Specific message"]
  }
}
```

`errors` is only present for 422 validation failures. Business-rule rejections that aren't per-field (e.g. "cannot delete a category with products") return a plain `message` with a 409 status.
