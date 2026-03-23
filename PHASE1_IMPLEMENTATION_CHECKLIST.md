# Phase 1 Implementation Checklist (Menu CRUD + Image Upload + Reordering)

Goal: deliver a complete admin workflow to manage menu items (create, edit, delete, image upload, reorder) and render ordered items on the public menu page.

---

## 0) Scope Lock and Contracts

- [x] Confirm menu item fields for v1: `name`, `description`, `price_pence`, `category`, `image_path`, `display_order`, `is_available`.
- [x] Confirm allowed image types and max size: jpg/png/webp, 2MB.
- [x] Confirm reorder behavior: full-list reorder payload.
- [x] Confirm categories are free text in Phase 1 (normalize later).

---

## 1) Database and Migration Work

### 1.1 Create migration

- [x] Add migration file: `database/migrations/20260322_000200_create_menu_items_table.php`.
- [x] Add columns:
  - [x] `id` (primary key)
  - [x] `name` (string/varchar)
  - [x] `description` (text, nullable)
  - [x] `price_pence` (int)
  - [x] `category` (varchar, nullable)
  - [x] `image_path` (varchar, nullable)
  - [x] `display_order` (int, default 0, indexed)
  - [x] `is_available` (tinyint/bool, default 1, indexed)
  - [x] `created_at`, `updated_at`
- [x] Add migration `down()` that cleanly drops the table.

### 1.2 Apply and validate migration

- [x] Run `php cli/migrate.php up` (docker executed via `docker exec phpsk-php php cli/migrate.php up`).
- [x] Run `php cli/migrate.php status` and verify migration marked as applied.
- [x] Roll back and re-apply once to verify idempotent behavior (`down` then `up`).

---

## 2) Backend Model Layer

### 2.1 Create model

- [x] Add `app/models/MenuItem.php` with namespace `Model`.
- [x] Configure model table name (`menu_items`).
- [x] Define `$allowedColumns` for all writable fields.

### 2.2 Validation helpers (in model or controller-level helper)

- [x] Add validation for required `name`.
- [x] Add validation for `price_pence` integer and non-negative.
- [x] Add validation for `display_order` integer (when provided).
- [x] Normalize booleans (`is_available`) from request payload.

---

## 3) Backend API Endpoints

### 3.1 Create controller

- [x] Add `app/controllers/Api/MenuController.php`.
- [x] Ensure controller uses existing API response helpers (`ok`, `error`, etc.).

### 3.2 Implement endpoints

- [x] `GET /api/menu/index`
  - [x] Return ordered list by `display_order ASC`, then `id ASC`.
  - [x] Optional filters: `is_available`, `category`.
- [x] `GET /api/menu/show/{id}`
  - [x] Return single item or `404` JSON.
- [x] `POST /api/menu/create`
  - [x] Validate payload.
  - [x] Auto-set `display_order` to next available position if omitted.
  - [x] Return `201` + created resource.
- [x] `POST /api/menu/update/{id}`
  - [x] Validate payload.
  - [x] Return updated resource or `404`.
- [x] `POST /api/menu/delete/{id}`
  - [x] Delete item and return success payload.
  - [x] Decide hard delete vs soft delete (recommended Phase 1: hard delete).
- [x] `POST /api/menu/reorder`
  - [x] Accept ordered list of IDs (or id/order pairs).
  - [x] Validate all IDs exist.
  - [x] Update `display_order` consistently.
  - [x] Return updated ordered list.

### 3.3 Error and security behavior

- [x] Ensure all mutating endpoints require CSRF token.
- [x] Ensure consistent validation error payload: `{"message":"...","errors":{...}}` with `422`.
- [x] Ensure `404` payload consistency for missing records.

---

## 4) Image Uploads (Menu Item Images)

### 4.1 Upload endpoint

- [x] Add `app/controllers/Api/UploadsController.php` (or `MenuController::uploadImage`).
- [x] Add `POST /api/uploads/menu_image` endpoint.
- [x] Validate uploaded file type and size.
- [x] Generate unique safe filename.
- [x] Save file under a public uploads directory (e.g., `public/uploads/menu/`).
- [x] Return JSON containing resolved `image_path`.

### 4.2 Hardening

- [x] Block executable extensions.
- [x] Do not trust client-provided filename.
- [x] Normalize path separators and return web-safe URL path.
- [x] Decide behavior for replacing old images on update (recommended: keep Phase 1 simple, no cleanup job yet).

---

## 5) Frontend Admin UI

### 5.1 API client wrappers

- [x] Add/extend `frontend/src/lib` API client module for menu endpoints.
- [x] Add helper for image upload endpoint.

### 5.2 Admin pages/components

- [x] Add route/page: `/admin/menu` (list + reorder UI).
- [x] Add route/page: `/admin/menu/new` (create form).
- [x] Add route/page: `/admin/menu/:id/edit` (edit form).
- [x] Build shared menu form component with fields:
  - [x] name
  - [x] description
  - [x] price
  - [x] category
  - [x] image upload
  - [x] available toggle

### 5.3 Reorder UX

- [x] Implement drag-and-drop ordering UI (or explicit move up/down fallback).
- [x] Persist new order via `/api/menu/reorder`.
- [x] Add optimistic update + rollback on API failure.

### 5.4 Admin quality requirements

- [x] Loading, empty, and error states for all screens.
- [x] Success/error toasts or inline alerts.
- [x] Mobile-friendly layout (usable at small widths).

---

## 6) Frontend Public Menu Page

- [x] Add/finish `/menu` page that fetches `GET /api/menu/index`.
- [x] Render grouped/ordered menu cards (category grouping optional but recommended).
- [x] Hide unavailable items by default on public page (or visually mark if included).
- [x] Ensure images render with safe fallback placeholder.
- [x] Apply funky visual direction once color scheme is defined.

---

## 7) Test Implementation

### 7.1 Backend feature tests

- [x] Add `tests/Feature/MenuApiTest.php` covering:
  - [x] create success
  - [x] create validation failure
  - [x] index order correctness
  - [x] show 404
  - [x] update success/failure
  - [x] delete success/failure
  - [x] reorder success
  - [x] reorder with invalid IDs fails
- [x] Add upload endpoint tests (valid/invalid mime/size).

### 7.2 Frontend behavior checks

- [x] At minimum, add smoke tests/manual verification script for admin menu flows.
- [x] If test framework exists, add component/integration tests for form validation and reorder persistence.

---

## 8) Verification Commands

- [x] `docker compose up -d --build`
- [x] `docker compose exec -T php php cli/migrate.php up`
- [x] `docker compose exec -T php vendor/bin/phpunit --testsuite Feature`
- [x] `npm run build` (executed in container via `docker compose exec -T node npm run build`)
- [x] Verify manually (API-level QA walkthrough completed):
  - [x] Admin can create/edit/delete menu item
  - [x] Admin can upload/change image
  - [x] Admin reorder persists after refresh
  - [x] Public `/menu` displays correct ordered items

## Seed Data (Initial Menu)

- [x] Parse and seed from `menuplan/Restaurant.md` via `cli/seed_menu.php`.
- [x] Seed bilingual dish names from markdown translations (Chinese + English).
- [x] Seed bilingual free-text category names from markdown translations (Chinese + English).
- [x] Copy image files from `menuplan/` to `public/uploads/menu/`.
- [x] Persist `display_order` from markdown sequence.
- [x] Default `price_pence` to `0` for initial seed (staff edits later).
- [x] Skip image cleanup automation in Phase 1.

---

## 9) Definition of Done (Phase 1)

- [x] Restaurant staff can fully manage menu content from admin.
- [x] Public menu reflects updates and ordering immediately.
- [x] Validation/security behavior is consistent with API conventions.
- [x] Feature tests for menu endpoints are passing.
- [x] Build passes and no syntax errors in edited PHP files.

---

## 10) Suggested File-Level Task Order

1. Migration: `database/migrations/*_create_menu_items_table.php`
2. Model: `app/models/MenuItem.php`
3. API: `app/controllers/Api/MenuController.php`
4. Upload API: `app/controllers/Api/UploadsController.php` (or menu upload method)
5. Frontend API wrappers: `frontend/src/lib/*`
6. Admin pages: `frontend/src/pages/admin/*`
7. Public menu page: `frontend/src/pages/Menu*`
8. Tests: `tests/Feature/MenuApiTest.php` (+ upload tests)

This order keeps backend contracts stable before UI wiring, and keeps tests close to each completed slice.
