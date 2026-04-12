import { api } from './api'

let csrfToken = null

async function getCsrfToken(force = false) {
  if (!force && csrfToken) {
    return csrfToken
  }

  const data = await api('/api/auth/csrf')
  csrfToken = data.csrfToken
  return csrfToken
}

async function withCsrf(path, payload = {}, options = {}) {
  const token = await getCsrfToken()

  try {
    return await api(path, {
      method: options.method || 'POST',
      headers: {
        'X-CSRF-Token': token,
        ...(options.headers || {}),
      },
      body: JSON.stringify(payload),
    })
  } catch (error) {
    if (error.status === 419) {
      const refreshed = await getCsrfToken(true)
      return api(path, {
        method: options.method || 'POST',
        headers: {
          'X-CSRF-Token': refreshed,
          ...(options.headers || {}),
        },
        body: JSON.stringify(payload),
      })
    }

    throw error
  }
}

export async function fetchBookings(params = {}) {
  const search = new URLSearchParams()

  if (params.status) {
    search.set('status', params.status)
  }

  if (params.date) {
    search.set('date', params.date)
  }

  if (params.tableId) {
    search.set('table_id', String(params.tableId))
  }

  const query = search.toString()
  const path = query ? `/api/bookings?${query}` : '/api/bookings'

  return api(path, { method: 'GET' })
}

export function createBooking(payload) {
  return withCsrf('/api/bookings/create', payload)
}

export function fetchBooking(id) {
  return api(`/api/bookings/show/${id}`, { method: 'GET' })
}

export function fetchBookingAvailability({ partySize, bookingStart, bookingEnd, excludeBookingId }) {
  const search = new URLSearchParams({
    party_size: String(partySize),
    booking_start: String(bookingStart),
    booking_end: String(bookingEnd),
  })

  if (excludeBookingId) {
    search.set('exclude_booking_id', String(excludeBookingId))
  }

  return api(`/api/bookings/availability?${search.toString()}`, { method: 'GET' })
}

export function assignBookingTable(bookingId, tableId, options = {}) {
  return withCsrf(`/api/bookings/assign_table/${bookingId}`, {
    table_id: tableId,
    ...(options.confirmOversized ? { confirm_oversized: true } : {}),
  })
}

export function updateBooking(bookingId, payload) {
  return withCsrf(`/api/bookings/update/${bookingId}`, payload)
}

export function cancelBooking(bookingId) {
  return withCsrf(`/api/bookings/cancel/${bookingId}`, {})
}
