# Database Schema

All tables live in a single database — SQLite locally, Postgres (Neon) in production, same schema either way via the same migrations. This document lists every table, its columns, and its relationships as they actually exist in the migrations today (not the original design — several tables have grown well past their initial column set as features were added).

## `users`

| Column | Type | Notes |
|---|---|---|
| id | bigint | PK |
| name | string | |
| email | string | unique |
| email_verified_at | timestamp | nullable |
| password | string | hashed |
| role | string | default `user`; `user` or `admin` |
| remember_token | string | |
| timestamps | | |

Also creates `password_reset_tokens` and `sessions` (Laravel defaults, not actively used since auth is token-based via Sanctum) and `personal_access_tokens` (Sanctum's token storage).

## `categories`

| Column | Type | Notes |
|---|---|---|
| id | bigint | PK — the stable identifier used everywhere a category needs to be referenced from frontend code, since `name`/`slug` are both editable |
| name | string | |
| slug | string | unique |
| icon | string | nullable, currently unused by any frontend page |
| timestamps | | |

## `brands`

| Column | Type | Notes |
|---|---|---|
| id | bigint | PK |
| name | string | |
| logo | string | nullable — either a relative path to an uploaded file (`brands/xxx.png`) or a raw external URL pasted in directly; `BrandResource` resolves the two cases differently |
| timestamps | | |

## `benchmark_targets`

The catalog of games/software that a product can have a benchmark result recorded against. Managed only through the admin "Benchmark Targets" page — a customer never sees this table's raw list, only the results attached to a specific product.

| Column | Type | Notes |
|---|---|---|
| id | bigint | PK |
| name | string | e.g. "Cyberpunk 2077" |
| type | string | `game` or `software` — determines whether `fps` or `score` is the relevant result field on a linked `benchmarks` row |
| image | string | nullable |
| created_at | timestamp | no `updated_at` |

## `addresses`

| Column | Type | Notes |
|---|---|---|
| id | bigint | PK |
| user_id | FK → users | cascade delete |
| city | string | |
| country | string | |
| street | string | |
| postal_code | string | nullable |
| phone | string | nullable |
| is_default | boolean | default false — exactly one `true` per user is enforced in `AddressService`, not by a DB constraint |
| timestamps | | |

## `products`

| Column | Type | Notes |
|---|---|---|
| id | bigint | PK |
| category_id | FK → categories | restrict delete |
| brand_id | FK → brands | restrict delete |
| created_by | FK → users | nullable, null on delete |
| sku | string | unique |
| name | string | |
| slug | string | unique |
| description | text | nullable |
| price | decimal(10,2) | |
| sale_price | decimal(10,2) | nullable, must be less than `price` |
| discount | decimal(5,2) | nullable, currently unused (discount % is computed client-side from price vs. sale_price instead) |
| stock | unsigned int | default 0 |
| thumbnail | string | nullable — legacy single-image field, superseded by `product_images` |
| weight | decimal(8,2) | nullable |
| warranty_months | unsigned int | nullable |
| is_active | boolean | default true — inactive products are hidden from guests/customers but still visible to admins |
| timestamps | + soft deletes | the only table with `SoftDeletes` in the whole project, specifically so historical `order_items` referencing a deleted product still resolve |

## `product_specifications`

One row per product, one column per possible spec field across every category. This table has grown considerably beyond its original design as more categories were added — it now covers CPU, motherboard, GPU, RAM, storage, PSU, cooler, case, and monitor fields all on the same row, plus a JSON escape hatch for anything else.

| Column | Type | Category it belongs to |
|---|---|---|
| socket | string | CPU, Motherboard, Cooler (comma-separated list of supported sockets) |
| chipset | string | Motherboard |
| form_factor | string | Motherboard, Storage, PSU, Case |
| cores / threads | unsigned int | CPU |
| clock_speed | string | CPU (base clock), GPU (base clock) |
| boost_clock | string | CPU, GPU |
| cpu_generation | string | CPU |
| architecture | string | CPU |
| integrated_graphics | string | CPU |
| cache_size | string | CPU (total cache) |
| l1_cache / l2_cache / l3_cache | string | CPU |
| pcie_version / pcie_slots | string / unsigned int | Motherboard, GPU |
| ram_type | string | Motherboard, RAM |
| memory_type | string | GPU (VRAM type) |
| memory_size | string | GPU (VRAM size), RAM (capacity) |
| max_memory / memory_slots | string / unsigned int | Motherboard |
| m2_slots / sata_ports | unsigned int | Motherboard |
| wifi | string | Motherboard |
| length_mm | unsigned int | GPU's own physical length |
| video_ports | string | GPU, Monitor |
| ram_speed | string | RAM |
| cas_latency | string | RAM |
| kit_config | string | RAM |
| capacity | string | Storage |
| storage_type | string | Storage (SSD/HDD) |
| storage_interface | string | Storage, Motherboard (SATA/NVMe/M.2 support) |
| read_speed / write_speed | unsigned int | Storage |
| power_draw | unsigned int | CPU, GPU (own power draw) |
| wattage | unsigned int | PSU (rated wattage) |
| efficiency_rating | string | PSU |
| modular_type | string | PSU |
| max_gpu_length | unsigned int | Case's GPU clearance limit |
| cooler_type | string | Cooler (Air/AIO) |
| fan_size | string | Cooler |
| max_tdp | unsigned int | Cooler (max CPU TDP supported) |
| screen_size / resolution / refresh_rate / panel_type / response_time | string | Monitor |
| custom_specifications | json | Free-form `[{key, value}, ...]` pairs the admin defines per product, for anything the fixed columns above don't cover |

Which of these columns the admin product form actually shows is decided client-side by category ID (see `frontend/admin/product-edit.html`'s `CATEGORY_SPEC_FIELDS` map) — the database itself places no restriction on which columns a given category's products may fill in.

## `product_images`

| Column | Type | Notes |
|---|---|---|
| id | bigint | PK |
| product_id | FK → products | cascade delete |
| image_path | string | relative path under the public disk |
| is_primary | boolean | default false — exactly one should be true per product (enforced by the controller, not a DB constraint) |
| created_at | timestamp | no `updated_at` |

## `benchmarks`

The actual link between a product and a benchmark target, with the measured result.

| Column | Type | Notes |
|---|---|---|
| id | bigint | PK |
| product_id | FK → products | |
| benchmark_target_id | FK → benchmark_targets | |
| resolution | string | nullable, e.g. "1440p" |
| quality | string | nullable, e.g. "Ultra" |
| fps | unsigned int | nullable — required if the target's `type` is `game` |
| score | unsigned int | nullable — required if the target's `type` is `software` |
| unit | string | nullable, e.g. "points", "seconds" — paired with `score` |
| created_at | timestamp | no `updated_at` |

## `carts` / `cart_items`

| Table | Column | Notes |
|---|---|---|
| carts | user_id | unique FK → users, cascade delete — one cart per user, created lazily on first use |
| cart_items | cart_id, product_id | FK, cascade delete; unique together — adding an already-present product increases `quantity` instead of inserting a new row |
| cart_items | quantity | unsigned int, default 1 |

## `orders` / `order_items`

| Table | Column | Notes |
|---|---|---|
| orders | order_number | unique, generated as `ORD-{yymmdd}-{6 random chars}` |
| orders | user_id | FK → users, restrict delete |
| orders | address_id | FK → addresses, restrict delete |
| orders | total_price | decimal(10,2) |
| orders | status | `pending` \| `processing` \| `shipped` \| `completed` \| `cancelled` |
| orders | payment_method | string, nullable |
| orders | payment_status | `pending` \| `paid` \| `failed` \| `refunded` |
| orders | notes | text, nullable |
| order_items | order_id | FK → orders, cascade delete |
| order_items | product_id | FK → products, restrict delete |
| order_items | build_id | FK → builds, nullable, null on delete — set when the order came from a build checkout rather than the regular cart |
| order_items | quantity | unsigned int |
| order_items | price | decimal(10,2) — the unit price **at the time of purchase**, never updated afterward even if the product's price changes later |

## `wishlists`

| Column | Type | Notes |
|---|---|---|
| user_id, product_id | FK | cascade delete; unique together |
| created_at | timestamp | no `updated_at` |

## `reviews`

| Column | Type | Notes |
|---|---|---|
| user_id, product_id | FK | cascade delete; unique together — a user can only have one review per product, editing it re-submits rather than creating a second row |
| rating | unsigned tinyint | |
| title | string | nullable |
| comment | text | nullable |
| is_approved | boolean | default false — every new review (or edit to an existing one) starts unapproved and only appears publicly once an admin approves it |

## `builds` / `build_items`

| Table | Column | Notes |
|---|---|---|
| builds | user_id | FK → users, cascade delete |
| builds | name | string, nullable |
| builds | total_price | decimal(10,2), default 0 — recomputed after every item change |
| builds | estimated_power | unsigned int, nullable — sum of components' `power_draw` |
| builds | compatibility_status | `compatible` \| `incompatible` \| `incomplete` |
| builds | status | `draft` \| `complete` \| `purchased` |
| builds | is_public | boolean, default false |
| builds | share_token | string, nullable, unique — only resolves via the public share link when `is_public` is also true |
| build_items | build_id, product_id | FK, cascade/restrict delete; unique together |
| build_items | slot | string — `cpu`, `motherboard`, `gpu`, `cooler`, `case`, `psu`, `ram`, or `storage` |
| build_items | quantity | unsigned int, default 1 — only meaningful for `ram`/`storage` (multi-instance slots); every other slot always holds exactly one item, and adding a new one there replaces the old row rather than adding a second |

## `banners`

Homepage carousel images, unrelated to products.

| Column | Type | Notes |
|---|---|---|
| image_path | string | |
| link_url | string | nullable |
| sort_order | unsigned int | default 0 |
| is_active | boolean | default true |

## Entity Relationship Summary

```
users ──< addresses
users ──< orders >── addresses
users ──< builds ──< build_items >── products
users ──< carts ──< cart_items >── products
users ──< wishlists >── products
users ──< reviews >── products

categories ──< products
brands ──< products
products ──< product_images
products ─1─ product_specifications
products ──< benchmarks >── benchmark_targets
products ──< order_items ──> orders
                          ──> builds (nullable, when purchased via a build)
```
