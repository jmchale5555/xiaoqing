# XiaoQing Restaurant Web App

React SPA + PHP JSON API for restaurant menu and booking operations.

## Booking Status Transitions (Staff/Dev Reference)

The backend enforces status transitions for `bookings.status`.

- `pending` -> `pending`, `confirmed`, `seated`, `cancelled`, `no_show`
- `confirmed` -> `confirmed`, `seated`, `cancelled`, `no_show`
- `seated` -> `seated`, `completed`, `cancelled`
- `completed` -> `completed` (terminal)
- `cancelled` -> `cancelled` (terminal)
- `no_show` -> `no_show` (terminal)

Notes:

- `POST /api/bookings/update/{id}` rejects invalid transitions with `422`.
- `POST /api/bookings/cancel/{id}` also follows these rules (terminal states cannot be cancelled again).
- Frontend admin status selector mirrors the same transition matrix for consistency.
