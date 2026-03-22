# AGENTS.md

Guidance for agentic coding tools operating in this repository.

## 1) Current Architecture

- Backend: custom PHP app serving JSON API endpoints.
- Frontend: React SPA built with Vite + Tailwind.
- API prefix: `/api/*`.
- SPA entry: `public/spa.php`.
- Backend entry: `public/index.php`.
- Routing split is handled in `app/core/App.php`.
- Database access is via model traits in `app/core/Model.php` and `app/core/Database.php`.

## 2) Rules Files (Cursor / Copilot)

Checked locations:

- `.cursorrules`
- `.cursor/rules/`
- `.github/copilot-instructions.md`

Result:

- No Cursor rules files found.
- No Copilot instructions file found.

If these files appear later, treat them as higher-priority repo policy and update this document.

## 3) Key Paths

- API controllers: `app/controllers/Api/*.php`
- Core API helpers: `app/core/ApiController.php`
- Migration runner: `app/core/MigrationRunner.php`
- Migration schema helpers: `app/core/Schema.php`
- Request helpers: `app/core/Request.php`
- Migration CLI entry: `cli/migrate.php`
- Migration files: `database/migrations/*.php`
- Frontend source: `frontend/`
- Vite config: `vite.config.js`
- Nginx config: `docker/nginx/default.conf`
- Compose stack: `docker-compose.yaml`

## 4) Setup Commands

From repo root:

```bash
composer install
npm install
cp .env.example .env
php cli/migrate.php up
```

Containerized dev (recommended):

```bash
docker compose up -d --build
```

## 5) Build / Lint / Test Commands

### Frontend

Dev server:

```bash
npm run dev
```

Production build:

```bash
npm run build
```

Preview production build:

```bash
npm run preview
```

### PHP syntax checks

```bash
php -l app/core/App.php
php -l app/controllers/Api/AuthController.php
```

For container workflows:

```bash
docker compose exec -T php php -l app/core/App.php
```

### Tests

Current status:

- PHPUnit suite is configured in `phpunit.xml`.

Run full suite (inside php container):

```bash
docker compose exec -T php vendor/bin/phpunit
```

Run unit suite only:

```bash
docker compose exec -T php vendor/bin/phpunit --testsuite Unit
```

Run feature suite against live docker nginx/php test stack:

```bash
docker compose exec -T php vendor/bin/phpunit --testsuite Feature
```

Run a single test file:

```bash
docker compose exec -T php vendor/bin/phpunit tests/Feature/AuthApiTest.php
```

Run a single test method:

```bash
docker compose exec -T php vendor/bin/phpunit --filter testSignupAndMeFlow tests/Feature/AuthApiTest.php
```

Local (non-docker) PHPUnit equivalents:

```bash
vendor/bin/phpunit
vendor/bin/phpunit tests/Feature/ExampleTest.php
vendor/bin/phpunit --filter test_method_name tests/Feature/ExampleTest.php
```

### Migrations

Create a migration:

```bash
php cli/migrate.php make create_posts_table
```

Run pending migrations:

```bash
php cli/migrate.php up
```

Rollback latest batch:

```bash
php cli/migrate.php down
```

Rollback all migrations:

```bash
php cli/migrate.php reset
```

Drop all tables and re-run migrations:

```bash
php cli/migrate.php fresh
```

Show migration status:

```bash
php cli/migrate.php status
```

## 6) API Conventions

- Return JSON for all `/api/*` endpoints.
- Routing pattern: `/api/{resource}/{method?}/{param1?}/{param2?...}`.
- If method is omitted, it defaults to `index`.
- Use `ApiController` helpers (`ok`, `error`, `methodNotAllowed`, `notFound`).
- CSRF token endpoint: `GET /api/auth/csrf`.
- Mutating auth endpoints expect `X-CSRF-Token` header.
- Keep error payloads consistent:
  - `{"message": "..."}`
  - `{"message": "...", "errors": {...}}` for validation issues.
- Use HTTP status codes intentionally:
  - `200` success
  - `201` resource created
  - `401` unauthenticated
  - `404` not found
  - `405` method not allowed
  - `422` validation failure
  - `419` CSRF token mismatch

Route examples:

- `GET /api/posts` -> `PostsController::index()`
- `GET /api/posts/show/12` -> `PostsController::show("12")`
- `POST /api/posts/create` -> `PostsController::create()`
- `POST /api/posts/update/12` -> `PostsController::update("12")`
- `POST /api/posts/delete/12` -> `PostsController::delete("12")`

## 7) Backend Style Guidelines

Design philosophy:

- Keep backend complexity and abstraction deliberately low.
- Prefer direct, readable PHP over framework-like indirection.
- It is acceptable to use native PHP primitives (`$_SERVER`, `$_POST`, `$_SESSION`, headers, etc.) when this keeps flow obvious.
- Avoid introducing extra layers (service containers, repositories, deep inheritance trees, heavy metaprogramming) unless there is a clear recurring need.
- Optimize for "easy to follow in one file" even if it means a bit of repetition.

- Use 4-space indentation in PHP.
- Follow Allman braces for PHP classes/methods/conditionals.
- Prefer explicit namespaces and `use` statements.
- Keep one class per file.
- Preserve compatibility with PHP 8.0+.
- Avoid adding strict runtime behavior changes without validation.

Naming:

- API controllers: PascalCase ending in `Controller`.
- Methods: camelCase.
- Models: PascalCase singular (`User`, `Order`, etc.).
- Constants: UPPER_SNAKE_CASE.

Data access:

- Use model trait methods (`first`, `where`, `insert`, `update`, `delete`) instead of ad-hoc SQL in controllers.
- Respect `$allowedColumns` in models.

Migrations:

- Keep migrations class-based and explicit (`up` and `down` methods).
- Prefer readable schema definitions over heavy abstraction.
- Keep migration files append-only after they have been applied in shared environments.

Security:

- Do not commit secrets or environment credentials.
- Keep runtime configuration in environment variables (`.env` for local only).
- Preserve session handling conventions when changing auth endpoints.

## 8) Frontend Style Guidelines

- Keep UI code in `frontend/src`.
- Use React function components and hooks.
- Route UI paths with React Router.
- Keep API calls in thin client wrappers under `frontend/src/lib`.
- Use Tailwind utility classes for styling; avoid inline style objects unless necessary.

Naming:

- Components/pages: PascalCase file names.
- Utility modules: lowercase camelCase names.
- Keep files focused and avoid oversized page modules.

## 9) Docker Notes

- Services: `db`, `php`, `nginx`, `node`.
- Test services: `php_test`, `nginx_test`.
- App URL through nginx: `http://localhost:8080`.
- Vite dev server URL: `http://localhost:5173`.
- Test API URL through nginx test service: `http://localhost:8081`.
- Node service runs `npm install && npm run dev`.
- PHP service uses `VITE_DEV_SERVER` to inject dev assets in `public/spa.php`.

## 10) Verification Checklist

- `docker compose ps` shows all services up and healthy.
- `GET /api/health/index` returns `{"ok":true,...}`.
- `GET /api/auth/me` returns `{"user":null}` when logged out.
- `GET /api/posts` returns a posts array.
- `GET /api/posts/show/1` returns either a post object or `404` JSON.
- `POST /api/posts/create` with auth + CSRF creates a post (`201`).
- SPA loads at `/` and mounts React root.
- Frontend build outputs to `public/spa`.
- No PHP syntax errors in edited backend files.

Quick API smoke commands:

```bash
curl -s http://localhost:8080/api/posts
curl -s http://localhost:8080/api/posts/show/1
```

---

When architecture or tooling changes, update this file first so future agents execute the correct commands and follow current conventions.
