<p align="center"><img width="160" src=".github/assets/logo.svg"></p>

![Gymie](.github/assets/banner.png)

## Overview
Laravel based web application for gym & club management. Currently being used by many fitness centers. For more information, visit - https://www.gymie.in

## Requirements

-   PHP >= 8.4
-   Laravel Framework ^13.0
-   Filament Admin Panel 5.x
-   Livewire 4.x
-   nnjeim/world ^1.1
-   barryvdh/laravel-dompdf ^3.1
-   Laravel Herd _(optional for local development)_

## Installation

To set up Gymie, follow these steps:

### 1. Clone the Repository

Clone the repository to your local system:

```bash
git clone git@github.com:lubusIN/gymie.git
```

### 2. Go to folder

```bash
cd gymie
```

### 3. Install dependencies

```bash
composer install
```

### 4. Prepare the environment

Run the following script to prepare your environment:

```bash
composer run prepare-env
```

This will:

-   Copy `.env.example` to `.env` (if missing)
-   Clear config cache
-   Generate application key
-   Create a symbolic link to the storage folder

### 5. Configure the `.env` file

-   Set your database credentials.
-   Update other relevant configuration values.
-   Set your application URL:
    ```env
    APP_URL=https://gymie.test
    ```

### 6. Database Setup

You can set up the database in one of two ways, depending on your requirements:

**Option 1: Blank Setup (Recommended for Production)**

Run the following command:

```bash
composer run setup
```

> [!NOTE]
> This command will prompt you to create an admin user via the terminal.

This will:

-   Set up the environment (.env, app key, storage link)
-   Run a fresh migration to create database tables
-   Seed the world data (countries, states, cities)
-   Create a default Filament admin user

**Option 2: Demo Setup**

If you want to explore the system with all demo data preloaded, use:

```bash
composer run setup-demo
```

This command will:

-   Reset the database
-   Seed all available demo data
-   Prepare the environment automatically

> [!CAUTION]
> This process will erase all existing data. Use it only in a local or demo environment.

Login credentials:

```bash
Email: test@example.com
Password: test
```

## Troubleshooting

**Memory Errors**

Ensure PHP has enough memory allocated. Edit your php.ini:

```ini
memory_limit = 512M
```

**Seeder Performance**

Seeders (like WorldSeeder) can add significant data and slow down performance. For production, avoid full seeding and run only necessary seeders:

```bash
php artisan db:seed --class=WorldSeeder
```

## Development

### 1. Start the development server:

```bash
php artisan serve
```

Or with Laravel Herd:

```bash
herd
```

### 2. Start the queue worker

To process background jobs:

```bash
php artisan queue:work
```

### 3. Start the Laravel scheduler

```bash
php artisan schedule:work
```

> [!NOTE]
> The scheduler must be running continuously to trigger time-based tasks (e.g., status updates).
>
> If those tasks dispatch queued jobs (like import/export or notifications), then the queue worker must also be running to process them.

## API (JSON, v1)

Gymie ships with a versioned JSON API under `routes/api.php` for integrations.

### Authentication (Sanctum Bearer Tokens)

-   Login: `POST /api/v1/auth/login`
-   Current user: `GET /api/v1/me`
-   Logout: `POST /api/v1/auth/logout`

Example:

```bash
curl -sX POST "$APP_URL/api/v1/auth/login" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password"}'
```

Use the returned token:

```bash
curl -s "$APP_URL/api/v1/me" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```

Notes:

-   The API is bearer-token only. Being logged into Filament in the browser does not authenticate API requests.
-   `/api/v1/me` always includes roles and permissions. Other user endpoints include permissions only when requested:
    -   `GET /api/v1/users?include=permissions` or `GET /api/v1/users?include_permissions=1`

### Index Query Parameters (Rich Filtering)

All index endpoints support allowlisted query params:

-   Search: `?q=...`
-   Pagination: `?page=...&per_page=...`
-   Sort (multi-sort): `?sort=-created_at,name`
-   Soft deletes (where supported): `?trashed=with|only`
-   Includes (allowlisted): `?include=service,subscription.member`
-   Filters (allowlisted): `?filter[field]=value`
    -   Range syntax for date/datetime: `?filter[date]=2026-03-01..2026-03-31`

Allowlists (searchable/sortable/includes/filters) are defined per resource in:

-   `app/Services/Api/Schemas/*Schema.php` via `::queryRules()`

## Meet Your Artisans

[LUBUS](https://lubus.in/?utm_source=github&utm_medium=open-source&utm_campaign=laravel-gymie-v3) is a web design agency based in Mumbai.

<a href="https://cal.com/lubus">
<img src="https://raw.githubusercontent.com/lubusIN/.github/refs/heads/main/profile/banner.png" />
</a>

## License

Gymie is an open-sourced saas licensed under the [MIT license](LICENSE)
