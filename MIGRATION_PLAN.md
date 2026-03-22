# Migration Plan: PHP MVC Views -> React SPA + PHP API

This document captures the concrete migration plan for moving this repository from mixed PHP view rendering + Alpine.js to **Option 1: React SPA + PHP JSON API**.

## Goal

- Keep PHP as backend/API layer.
- Move all UI rendering and routing to React.
- Keep server-side sessions for authentication (initially).
- Establish a reusable boilerplate structure for future projects.

## Target File Tree

- `app/`
- `app/controllers/`
- `app/controllers/Api/`
- `app/controllers/Api/AuthController.php`
- `app/controllers/Api/UserController.php`
- `app/controllers/Api/HealthController.php`
- `app/core/`
- `app/core/ApiController.php` (JSON response helpers)
- `app/core/App.php` (route split: `/api/*` vs SPA fallback)
- `app/core/Request.php` (JSON body helpers)
- `app/models/`
- `app/models/User.php`
- `public/`
- `public/index.php` (backend front controller)
- `public/spa.php` (serves SPA shell for non-API routes)
- `frontend/`
- `frontend/index.html`
- `frontend/src/main.jsx`
- `frontend/src/App.jsx`
- `frontend/src/router.jsx`
- `frontend/src/pages/HomePage.jsx`
- `frontend/src/pages/LoginPage.jsx`
- `frontend/src/pages/SignupPage.jsx`
- `frontend/src/pages/NotFoundPage.jsx`
- `frontend/src/components/Layout.jsx`
- `frontend/src/components/NavBar.jsx`
- `frontend/src/lib/api.js`
- `frontend/src/lib/auth.js`
- `frontend/src/styles/index.css`
- `frontend/postcss.config.js`
- `frontend/tailwind.config.cjs`
- `vite.config.js`
- `package.json` (Vite scripts)
- `docker/`
- `docker/nginx/default.conf` (serve built SPA + `/api` to PHP-FPM)
- `docker-compose.yaml` (optional `vite` service for dev profile)

## Architecture Changes

- PHP stops rendering app pages and returns JSON from `/api/*`.
- React owns all UI routing (`/`, `/login`, `/signup`, etc.).
- Sessions remain server-side in PHP; React calls API with `credentials: 'include'`.
- `app/views/*.view.php` and Alpine usage are retired for UI paths (except optional maintenance views).

## Minimum API Contract

- `POST /api/auth/login` -> `{user}` or `{errors}`
- `POST /api/auth/signup` -> `{user}` or `{errors}`
- `POST /api/auth/logout` -> `{ok:true}`
- `GET /api/auth/me` -> `{user|null}`
- `GET /api/health` -> `{ok:true}`

## Exact Migration Checklist

1. **Install frontend stack**
   - Add `vite`, `@vitejs/plugin-react`, `react`, `react-dom`, `react-router-dom`, `tailwindcss`, `postcss`, `autoprefixer`.
   - Replace Mix entry assumptions (`public/src/app.js`) with Vite entry (`frontend/src/main.jsx`).

2. **Create React app skeleton**
   - Add `frontend/` files listed above.
   - Build pages: Home, Login, Signup, NotFound.
   - Build shared nav and layout.

3. **Wire Tailwind to React**
   - Tailwind content paths should target `frontend/index.html` + `frontend/src/**/*.{js,jsx,ts,tsx}`.
   - Move app styles into `frontend/src/styles/index.css`.

4. **Implement API base helpers**
   - `frontend/src/lib/api.js`: fetch wrapper with JSON parsing + error normalization + `credentials: 'include'`.
   - `frontend/src/lib/auth.js`: `login`, `signup`, `logout`, `me`.

5. **Refactor PHP routing**
   - Update `app/core/App.php` to:
     - dispatch `/api/*` to API controllers
     - for non-API GET routes, return SPA shell (`public/spa.php`)

6. **Add API controllers**
   - Implement `ApiController` base (`json()`, `ok()`, `error()`, status codes).
   - Implement Auth endpoints using existing `User` model/session logic.
   - Keep validation behavior compatible with current `$errors` pattern.

7. **Session + CSRF approach**
   - Keep cookie session auth.
   - Phase 1: same-origin SPA + API in production (no CORS needed).
   - Phase 2 (dev split ports): configure Vite proxy to backend and keep `credentials: 'include'`.
   - Add CSRF endpoint/token check if production hardening is needed immediately.

8. **Create SPA serving path**
   - `public/spa.php` serves built asset references from Vite build (`dist`).
   - In production, nginx serves static assets and forwards `/api` to PHP.

9. **Update nginx/docker**
   - Nginx:
     - `/api` -> PHP-FPM
     - `/assets` and SPA build files -> static
     - fallback to SPA `index.html`
   - Optional dev profile: run Vite dev server and proxy API requests.

10. **Remove old UI surface**
    - Remove/retire `app/views/home.view.php`, `app/views/login.view.php`, `app/views/signup.view.php`, partials, and Alpine-specific JS once parity is reached.

11. **Command updates**
    - Add npm scripts:
      - `dev`, `build`, `preview`
    - Keep PHP syntax checks.
    - Add optional frontend lint/typecheck scripts.

12. **Verification pass**
    - Signup -> login -> me -> logout flow in SPA.
    - Deep links (`/login`, `/signup`) should load directly.
    - Invalid API route returns JSON 404.
    - Invalid SPA route renders React NotFound page.

## Definition of Done

- No user-facing PHP views required for core app flow.
- All auth flows run from React pages against `/api/*`.
- Direct URL refresh on SPA routes works.
- Docker and non-docker dev commands are documented and working.
- `AGENTS.md` reflects SPA+API architecture and canonical commands.

## AGENTS.md Update Plan

- Replace MVC view-first guidance with:
  - `frontend/` as React source of truth for UI
  - `app/controllers/Api/*` for JSON backend endpoints
  - `/api/*` reserved for backend
- Build/test/lint command section:
  - `npm run dev`, `npm run build`, `npm run preview`
  - `php -l` checks for edited backend files
  - single-test commands for PHPUnit/Pest retained as conditional conventions
- Coding conventions:
  - React component/file naming and hooks usage
  - API client pattern and response handling
  - PHP API response shape + status code rules
  - auth/session and CSRF guidance
- Manual verification updates:
  - SPA routes, auth flow, API health checks

## Suggested Implementation Passes

1. Scaffold Vite + React + Tailwind and add API endpoints.
2. Remove old PHP views/Alpine surface and finalize `AGENTS.md`.
