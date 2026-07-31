# Running the Project From Scratch

The project is two fully independent halves that each run on their own: the **backend** (Laravel API) and the **frontend** (plain HTML/JS). There's no build step, no npm/vite — the frontend opens directly via any static file server.

## Requirements

- PHP 8.2 or newer
- Composer
- Nothing else required — the database is SQLite (a single file, no MySQL/Postgres server needed)

## 1. Running the Backend

```bash
cd backend
composer install
```

Create the environment file:

```bash
cp .env.example .env
php artisan key:generate
```

Confirm `.env` has:

```
DB_CONNECTION=sqlite
```

(This is already the default — no extra setup needed since the SQLite database is just a file at `database/database.sqlite`.)

Run the migrations (creates every table):

```bash
php artisan migrate
```

Link the public storage folder (required for product/banner/logo images to actually load):

```bash
php artisan storage:link
```

Start the server:

```bash
php artisan serve
```

The backend runs at `http://127.0.0.1:8000`, and the API is available at `http://127.0.0.1:8000/api`.

### Enabling OPcache (optional, but important for performance)

If the backend feels slow, make sure OPcache is enabled in `php.ini`:

```ini
zend_extension=opcache
opcache.enable=1
opcache.memory_consumption=128
opcache.validate_timestamps=1
```

## 2. Running the Frontend

From the project root (not from inside `frontend/`):

```bash
php -S localhost:5501 -t frontend
```

The site opens at `http://localhost:5501`.

> **Note:** the API base URL is hardcoded in `frontend/js/api.js` (the `API_BASE_URL` constant). If you change the backend's port or domain, update that value too.

## 3. Creating an Admin Account

There's no UI to create an admin — regular registration (`register.html`) only creates a `role: user` account. To promote an account to admin:

```bash
cd backend
php artisan tinker
```

Then inside tinker:

```php
$user = App\Models\User::where('email', 'your-email@example.com')->first();
$user->update(['role' => 'admin']);
```

Log out and back in on the site afterward — the "Admin Dashboard" link will then appear in the profile menu.

## 4. Sample Data (optional)

`database/seeders/DatabaseSeeder.php` just creates one test user. For a full catalog to browse (350 products across 10 categories, real brands, specs), run the dedicated seeder instead:

```bash
php artisan db:seed --class=RealCatalogSeeder
```

This is destructive — it wipes and rebuilds `products`, `product_specifications`, `brands`, and every order/build/cart/review/wishlist/benchmark row tied to them, so only run it on a database you don't mind resetting. It's also what the production deploy runs automatically on first boot if the `products` table is empty (see `app/Console/Commands/SeedCatalogIfEmpty.php`).

Otherwise, log in as an admin and add categories/brands/products by hand through the admin panel.

## Common Troubleshooting

| Problem | Fix |
|---|---|
| Images don't show up | Make sure you ran `php artisan storage:link` |
| 401 on any admin request | Confirm the account is actually `role = admin` and that you logged in again after changing it |
| Site feels generally slow | Enable OPcache as above — there's no app-level caching in the project, so slowness is usually plain PHP overhead |
| CORS errors in the console | Make sure the backend is actually running on the same port registered in `API_BASE_URL` inside `frontend/js/api.js` |
