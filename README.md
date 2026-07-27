# PC Builder

An e-commerce platform focused on computer components, built around one core idea: **helping the user assemble a fully compatible PC build**, rather than acting as a generic store that just sells disconnected products.

## The Idea

The regular storefront (browse, cart, orders) exists, but it's a supporting feature for "Build a PC" — the page where a user picks each component (CPU, motherboard, RAM, storage, PSU, GPU, cooler, case) and the site actively verifies the parts actually work together (matching CPU/motherboard socket, supported RAM type, enough PSU wattage, GPU length fitting inside the case, etc.) before they buy.

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12 (PHP 8.2) |
| Frontend | Plain HTML / CSS / JavaScript (no framework, no build step) |
| Database | SQLite (development) |
| Auth | Laravel Sanctum (token-based API auth) |
| Image storage | Laravel Storage (public disk) |

## Project Structure

```
pc-builder/
├── backend/          # Laravel API (all logic and data)
├── frontend/         # Plain HTML + JS pages (consume the API only)
├── docs/             # Documentation (see table below)
└── database/         # Supporting files (ERD, etc.)
```

## Documentation

| File | Contents |
|---|---|
| [docs/setup.md](docs/setup.md) | How to run the project from scratch (backend + frontend) |
| [docs/architecture.md](docs/architecture.md) | Technical architecture (backend layers, frontend organization) |
| [docs/erd.md](docs/erd.md) | Every database table, its columns, and relationships |
| [docs/api.md](docs/api.md) | Full reference of every API endpoint |
| [docs/features.md](docs/features.md) | Every feature in the site, customer-facing and admin |
| [docs/business-rules.md](docs/business-rules.md) | Business rules every service layer enforces |
| [docs/requirements.md](docs/requirements.md) | Original project scope |
| [project-status-report.md](project-status-report.md) | Current project status (everything built so far) |

## Quick Start

See [docs/setup.md](docs/setup.md) for full details. In short:

```bash
# Backend
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan serve

# Frontend (from the project root)
php -S localhost:5501 -t frontend
```
