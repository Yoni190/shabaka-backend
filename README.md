# Shabaka backend

Laravel API for the Shabaka storefront and admin application. It provides the product catalog, customer authentication, addresses, carts, orders, Moyasar payments, and admin catalog and order management.

Live Preview: https://shabaka-frontend.pages.dev/

## Requirements

- PHP 8.3 or later with the extensions required by the Composer dependencies, including SQLite for the default database.
- Composer and Node.js with npm.

## Run locally

1. Install dependencies and create an environment file:

   ```sh
   composer install
   cp .env.example .env
   php artisan key:generate
   ```

2. The example environment uses SQLite. Create the database and run migrations:

   ```sh
   touch database/database.sqlite
   php artisan migrate
   ```

3. Optionally seed brands, categories, and a development admin account:

   ```sh
   php artisan db:seed
   ```

   The admin login is `admin@shabaka.com` with password `Aa123456`. The seeder resets this account's password when run again. Change the credentials before using a shared environment.

4. Start the API:

   ```sh
   php artisan serve
   ```

The API is available at `http://127.0.0.1:8000/api/v1`. Set `DB_*` in `.env` before migrating if you use another database. The example configuration also uses the database for sessions, cache, and queues.

## Configuration

- Set `MOYASAR_SECRET_KEY` and `MOYASAR_WEBHOOK_SECRET` for payment verification and webhooks. `MOYASAR_BASE_URL` and `MOYASAR_TIMEOUT` are optional; see `config/services.php`.
- Uploads use the S3 disk by default. Configure the `AWS_*` values, including `AWS_BUCKET` and `AWS_ENDPOINT`, for S3-compatible storage. See `config/filesystems.php`.
- The frontend and admin clients use bearer tokens issued by Laravel Sanctum. Admin routes also require the admin role.

## API and checks

Routes are defined in `routes/api.php`. Public catalog endpoints are under `/api/v1`, and admin endpoints are under `/api/v1/admin`. Run `php artisan route:list --path=api` to inspect the available routes.

Run the test suite with:

```sh
composer test
```

The test configuration uses an in-memory SQLite database. The optional Laravel asset scripts are `npm run dev` and `npm run build`.
