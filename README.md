# XiaoQing Restaurant Web App

XiaoQing is a restaurant operations app with a custom PHP JSON API backend and a React SPA frontend.
It supports staff-friendly menu management, booking workflows, role-based admin access, and audit-friendly booking history.

## What The App Does

### Public-facing

- Browse the restaurant menu (`/menu`) grouped by category.
- View menu item names, descriptions, prices, and availability.

### Authentication and accounts

- Sign up, log in, and log out.
- Change password from the account menu.
- First registered user becomes `manager`; subsequent users default to `customer`.

### Staff/manager features

- **Menu management** (`/admin/menu`)
  - Create, edit, and delete dishes.
  - Toggle visibility (publish/unpublish behavior).
  - Reorder dishes and save order.
  - Upload item images.
- **Booking management** (`/admin/bookings`, `/admin/bookings/:id`)
  - Filter bookings by date and status.
  - Create and amend bookings.
  - Assign/reassign tables with availability assist.
  - Confirm oversized assignments when a table has 4+ extra seats.
  - Cancel bookings and update statuses with guarded transitions.
  - View booking activity/audit events in detail view.

## Tech Stack

- Backend: custom PHP app (JSON API under `/api/*`)
- Frontend: React + Vite + Tailwind
- Database: MariaDB
- Runtime/ops: Docker Compose (`db`, `php`, `nginx`, `node` + test services)

## Getting Started (Docker)

### 1) Prerequisites

- Docker Engine + Docker Compose plugin installed
- Ports available: `8080`, `8081`, `5173`, `3306`

### 2) Clone and configure

From repo root:

```bash
cp .env.example .env
docker compose up -d --build
```

### 3) Install dependencies in containers

```bash
docker compose exec -T php composer install
docker compose exec -T node npm install
```

### 4) Run database migrations

```bash
docker compose exec -T php php cli/migrate.php up
```

Optional menu seed (recommended for demo data):

```bash
docker compose exec -T php php cli/seed_menu.php
```

### 5) Access the app

- Main app via nginx: `http://localhost:8080`
- Vite dev server (frontend assets/dev): `http://localhost:5173`
- Test stack API/nginx: `http://localhost:8081`

Quick health checks:

```bash
curl -s http://localhost:8080/api/health/index
curl -s http://localhost:8080/api/auth/me
curl -s http://localhost:8080/api/posts
```

## Daily Development Commands

From repo root:

- Start stack: `docker compose up -d`
- Rebuild stack: `docker compose up -d --build`
- Stop stack: `docker compose down`
- View services: `docker compose ps`

Frontend:

- Dev server: `docker compose exec -T node npm run dev`
- Build: `docker compose exec -T node npm run build`

Backend:

- PHP syntax check example: `docker compose exec -T php php -l app/core/App.php`

Tests:

- Frontend tests: `docker compose exec -T node npm run test:frontend`
- Full PHPUnit: `docker compose exec -T php vendor/bin/phpunit`
- Feature tests only: `docker compose exec -T php vendor/bin/phpunit --testsuite Feature`

## API and Security Notes

- API routes are under `/api/*` and always return JSON.
- Mutating endpoints are protected and require auth/role where relevant.
- CSRF token endpoint: `GET /api/auth/csrf`
- Mutating auth endpoints expect header: `X-CSRF-Token`
- Staff-only mutations enforce role checks (staff or manager).

## Booking Status Transitions (Staff/Dev Reference)

Backend-enforced transitions for `bookings.status`:

- `pending` -> `pending`, `confirmed`, `seated`, `cancelled`, `no_show`
- `confirmed` -> `confirmed`, `seated`, `cancelled`, `no_show`
- `seated` -> `seated`, `completed`, `cancelled`
- `completed` -> `completed` (terminal)
- `cancelled` -> `cancelled` (terminal)
- `no_show` -> `no_show` (terminal)

Notes:

- `POST /api/bookings/update/{id}` rejects invalid transitions with `422`.
- `POST /api/bookings/cancel/{id}` follows the same rules (terminal states cannot be cancelled again).
- Admin UI mirrors the same transition matrix to prevent invalid choices client-side.

## Project Structure (High Level)

- Backend entry: `public/index.php`
- SPA entry: `public/spa.php`
- API controllers: `app/controllers/Api/*.php`
- Core app/router: `app/core/App.php`
- Migrations: `database/migrations/*.php`
- Migration CLI: `cli/migrate.php`
- Frontend source: `frontend/src`

## Screenshots (Optional)

You can add screenshots under `docs/images/` and wire them here for a polished project overview.

Suggested files:

- `docs/images/public-menu.png`
- `docs/images/admin-menu.png`
- `docs/images/admin-bookings-board.png`
- `docs/images/admin-booking-detail.png`
- `docs/images/mobile-admin-menu.png`

Markdown template:

```md
## Screenshots

### Public Menu
![Public menu](docs/images/public-menu.png)

### Admin Menu Manager
![Admin menu manager](docs/images/admin-menu.png)

### Admin Bookings Board
![Admin bookings board](docs/images/admin-bookings-board.png)

### Admin Booking Detail
![Admin booking detail](docs/images/admin-booking-detail.png)

### Mobile Admin Menu
![Mobile admin menu](docs/images/mobile-admin-menu.png)
```

## Current Scope and Status

- Menu and booking operations are implemented.
- Role-based admin access is implemented.
- Booking audit events are implemented and visible in admin booking detail.
- Mobile/admin usability has active ongoing polish.
