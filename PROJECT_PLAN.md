# Restaurant Web App Plan

## 1) Product Vision

Build a restaurant web app with two core operational modules:

1. **Menu Management (Admin)**
   - Staff can create, edit, and delete menu items.
   - Staff can upload and update item images.
   - Staff can reorder menu items visually.

2. **Table Booking Management (Admin + Booking Flow)**
   - Staff can create, edit, and delete tables.
   - Staff can set seats per table.
   - Staff can accept and manage bookings against available tables and times.

Later phase:
- **Analytics dashboard** (Chart.js) for bookings, occupancy, and menu performance trends.

---

## 2) Proposed Delivery Strategy (Phased)

## Phase 0 — Project Setup and Design Foundation

- Confirm visual direction and color scheme (funky style system).
- Define typography, spacing, cards, buttons, and motion language.
- Set up route skeleton in frontend and API modules in backend.
- Add auth and role guard for admin pages (if not already in place from boilerplate).

**Output**
- App shell, design tokens, and page skeletons.

## Phase 1 — Menu CRUD + Image Upload + Reordering (MVP Part A)

- Create `menu_items` schema and model.
- Build admin list view with:
  - sortable order (drag and persist)
  - quick publish/unpublish toggle (optional but useful)
- Build create/edit form:
  - title, description, price, category, image, availability
- Implement image upload endpoint and storage path policy.
- Public menu page renders grouped and ordered items.

**Output**
- Restaurant can fully maintain its menu from admin.

## Phase 2 — Table and Booking Management (MVP Part B)

- Create `tables` schema and model:
  - table name/number, seats, active flag
- Create `bookings` schema and model:
  - guest details, party size, date/time, status, assigned table
- Implement booking conflict checks:
  - prevent overlapping bookings per table/time window
- Build admin booking board/list:
  - filter by date/status
  - assign/reassign table
  - confirm/cancel bookings
- Build customer-facing booking form (or staff-entered booking form first).

**Output**
- Staff can manage floor capacity and reservations reliably.

## Phase 3 — Polish, Validation, and Operations

- Input validation and friendly error states.
- Role and security hardening.
- Audit-friendly timestamps/events.
- Mobile responsiveness and accessibility pass.
- Add smoke and feature tests for menu and bookings.

**Output**
- Stable v1 suitable for real usage.

## Phase 4 — Analytics (Chart.js)

- Define metrics:
  - bookings per day/week
  - peak booking hours
  - occupancy by table
  - cancellation rate
- Add reporting endpoints and Chart.js admin dashboard.
- Add date-range filters and export-ready summaries.

**Output**
- Actionable operational analytics.

---

## 3) Suggested Data Model (Initial)

## `menu_items`

- `id`
- `name`
- `description`
- `price_cents` (integer; avoid float)
- `category` (or `category_id` if normalized later)
- `image_path`
- `display_order` (integer)
- `is_available` (bool)
- `created_at`, `updated_at`

## `tables`

- `id`
- `name` (e.g., "T1", "Patio-2")
- `seats` (integer)
- `is_active` (bool)
- `display_order` (optional)
- `created_at`, `updated_at`

## `bookings`

- `id`
- `guest_name`
- `guest_phone` (and/or email)
- `party_size`
- `booking_start` (datetime)
- `booking_end` (datetime or derived duration)
- `status` (`pending`, `confirmed`, `seated`, `completed`, `cancelled`, `no_show`)
- `table_id` (nullable until assigned)
- `notes`
- `created_at`, `updated_at`

---

## 4) Backend API Breakdown (Proposed)

Follow existing API routing style: `/api/{resource}/{method?}/{param1?...}`

## Menu

- `GET /api/menu/index`
- `GET /api/menu/show/{id}`
- `POST /api/menu/create`
- `POST /api/menu/update/{id}`
- `POST /api/menu/delete/{id}`
- `POST /api/menu/reorder` (array of IDs/order values)

## Uploads

- `POST /api/uploads/menu-image` (returns stored path/URL)

## Tables

- `GET /api/tables/index`
- `POST /api/tables/create`
- `POST /api/tables/update/{id}`
- `POST /api/tables/delete/{id}`
- `POST /api/tables/reorder` (optional)

## Bookings

- `GET /api/bookings/index` (filters: date, status)
- `GET /api/bookings/show/{id}`
- `POST /api/bookings/create`
- `POST /api/bookings/update/{id}`
- `POST /api/bookings/cancel/{id}`
- `POST /api/bookings/assign-table/{id}`
- `GET /api/bookings/availability` (date/time/party size)

---

## 5) Frontend Route Plan

## Public

- `/` home/hero
- `/menu` restaurant menu
- `/book` booking form (optional in first release if staff-only)

## Admin

- `/admin/menu` list + reorder
- `/admin/menu/new`
- `/admin/menu/:id/edit`
- `/admin/tables`
- `/admin/bookings`
- `/admin/bookings/:id`

---

## 6) UX Direction for Funky Layout

Use a deliberate visual system (once color scheme is provided):

- bold typography pairing (headline + readable body)
- layered backgrounds (gradients/shapes/pattern texture)
- asymmetrical grid sections
- playful card geometry and hover transitions
- animated reveals on section load (not overdone)
- strong visual distinction between public pages and admin utility pages

---

## 7) Validation and Business Rules

## Menu

- price must be non-negative
- required name/title
- image type/size checks
- reorder endpoint validates IDs belong to existing items

## Bookings

- party size > 0
- booking time in allowed service windows
- table seats must be >= party size
- no table overlap in booking interval
- status transition guards (e.g., cancelled -> seated invalid)

---

## 8) Testing Plan

- **Unit**
  - booking conflict logic
  - ordering/reorder logic
  - validation helpers

- **Feature/API**
  - menu CRUD success/failure
  - reorder persists correctly
  - table CRUD
  - booking creation and conflict rejection
  - booking status transitions

- **Integration**
  - migration up/down/fresh
  - image upload flow (if feasible in CI/test env)

---

## 9) Security and Ops Checklist

- CSRF enforced for mutating endpoints
- admin auth and authorization checks on admin routes
- sanitize uploads and filenames
- prevent direct executable uploads
- consistent JSON error format
- environment-driven config only (no secrets committed)

---

## 10) Milestones (Execution Order)

1. Finalize design tokens and color scheme.
2. Implement menu schema + API + admin UI.
3. Add image uploads.
4. Add drag-and-drop reorder + persist.
5. Implement tables schema + admin UI.
6. Implement bookings schema + API + admin UI.
7. Add conflict handling + availability helper.
8. Hardening + tests + polish.
9. Add analytics module (Chart.js).

---

## 11) Nice-to-Have Backlog

- menu categories as separate table with icons/colors
- booking reminders (email/SMS integration later)
- floor map visual editor
- soft deletes and audit trail
- multi-location support
- iCal/Google Calendar sync

---

## 12) Immediate Next Step

Once color scheme is provided, create:

- UI mood direction doc (1 page)
- concrete component list
- Phase 1 implementation tickets (backend + frontend + tests)
