import { useEffect, useMemo, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import AdminGuard from '../../components/AdminGuard'
import {
  assignBookingTable,
  cancelBooking,
  fetchBooking,
  fetchBookingAvailability,
  updateBooking,
} from '../../lib/bookings'
import { getFriendlyError } from '../../lib/errors'
import { fetchTables } from '../../lib/tables'

const STATUS_OPTIONS = ['pending', 'confirmed', 'seated', 'completed', 'cancelled', 'no_show']

const STATUS_TRANSITIONS = {
  pending: ['pending', 'confirmed', 'seated', 'cancelled', 'no_show'],
  confirmed: ['confirmed', 'seated', 'cancelled', 'no_show'],
  seated: ['seated', 'completed', 'cancelled'],
  completed: ['completed'],
  cancelled: ['cancelled'],
  no_show: ['no_show'],
}

export default function AdminBookingDetailPage() {
  const { id } = useParams()
  const [booking, setBooking] = useState(null)
  const [tables, setTables] = useState([])
  const [form, setForm] = useState(defaultForm())
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [assigningTableId, setAssigningTableId] = useState(null)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [availability, setAvailability] = useState({
    recommended_tables: [],
    larger_tables: [],
    busy_table_ids: [],
  })
  const [availabilityLoading, setAvailabilityLoading] = useState(false)
  const [availabilityError, setAvailabilityError] = useState('')
  const [oversizedConfirm, setOversizedConfirm] = useState(null)
  const [events, setEvents] = useState([])

  const tableMap = useMemo(() => {
    const map = new Map()
    tables.forEach((table) => map.set(table.id, table))
    return map
  }, [tables])

  const allowedStatuses = useMemo(() => {
    const currentStatus = booking?.status || 'pending'
    return new Set(STATUS_TRANSITIONS[currentStatus] || [currentStatus])
  }, [booking?.status])

  useEffect(() => {
    let mounted = true

    async function load() {
      setLoading(true)
      setError('')

      try {
        const [bookingData, tablesData] = await Promise.all([fetchBooking(id), fetchTables({ isActive: true })])

        if (!mounted) {
          return
        }

        const nextBooking = bookingData.booking || null
        setBooking(nextBooking)
        setEvents(Array.isArray(bookingData.events) ? bookingData.events : [])
        setTables(Array.isArray(tablesData.tables) ? tablesData.tables : [])
        const nextForm = nextBooking ? toFormState(nextBooking) : defaultForm()
        setForm(nextForm)

        if (nextBooking) {
          await loadAvailability(nextBooking, nextForm)
        }
      } catch (err) {
        if (!mounted) {
          return
        }
        setError(getFriendlyError(err, 'Could not load booking details. Please refresh and try again.'))
      } finally {
        if (mounted) {
          setLoading(false)
        }
      }
    }

    load()

    return () => {
      mounted = false
    }
  }, [id])

  useEffect(() => {
    if (!message) {
      return
    }

    const timeout = window.setTimeout(() => setMessage(''), 4000)
    return () => window.clearTimeout(timeout)
  }, [message])

  useEffect(() => {
    if (!error) {
      return
    }

    const timeout = window.setTimeout(() => setError(''), 4000)
    return () => window.clearTimeout(timeout)
  }, [error])

  useEffect(() => {
    if (!availabilityError) {
      return
    }

    const timeout = window.setTimeout(() => setAvailabilityError(''), 4000)
    return () => window.clearTimeout(timeout)
  }, [availabilityError])

  async function loadAvailability(sourceBooking = booking, sourceForm = form) {
    const candidate = sourceBooking || booking
    if (!candidate) {
      return
    }

    const partySize = Number(sourceForm.party_size || candidate.party_size || 0)
    const bookingStart = normalizeDateTimePayload(sourceForm.booking_start) || candidate.booking_start
    const bookingEnd = normalizeDateTimePayload(sourceForm.booking_end) || candidate.booking_end

    if (!partySize || !bookingStart || !bookingEnd) {
      setAvailability({ recommended_tables: [], larger_tables: [], busy_table_ids: [] })
      return
    }

    setAvailabilityLoading(true)
    setAvailabilityError('')

    try {
      const data = await fetchBookingAvailability({
        partySize,
        bookingStart,
        bookingEnd,
        excludeBookingId: candidate.id,
      })

      setAvailability({
        recommended_tables: Array.isArray(data.recommended_tables) ? data.recommended_tables : [],
        larger_tables: Array.isArray(data.larger_tables) ? data.larger_tables : [],
        busy_table_ids: Array.isArray(data.busy_table_ids) ? data.busy_table_ids : [],
      })
    } catch (err) {
      setAvailability({ recommended_tables: [], larger_tables: [], busy_table_ids: [] })
      setAvailabilityError(getFriendlyError(err, 'Could not load table options. Please try again.'))
    } finally {
      setAvailabilityLoading(false)
    }
  }

  async function refreshBooking() {
    const bookingData = await fetchBooking(id)
    const nextBooking = bookingData.booking || null
    setBooking(nextBooking)
    setEvents(Array.isArray(bookingData.events) ? bookingData.events : [])
    setForm(nextBooking ? toFormState(nextBooking) : defaultForm())
    if (nextBooking) {
      await loadAvailability(nextBooking, toFormState(nextBooking))
    }
  }

  async function handleSubmit(event) {
    event.preventDefault()
    if (!booking) {
      return
    }

    setSaving(true)
    setError('')
    setMessage('')

    try {
      await updateBooking(booking.id, {
        guest_name: form.guest_name,
        guest_phone: form.guest_phone,
        guest_email: form.guest_email,
        party_size: Number(form.party_size || 0),
        booking_start: normalizeDateTimePayload(form.booking_start),
        booking_end: normalizeDateTimePayload(form.booking_end),
        status: form.status,
        notes: form.notes,
      })

      await refreshBooking()
      setMessage('Booking changes saved successfully.')
    } catch (err) {
      setError(getFriendlyError(err, 'Could not save booking changes. Please review details and try again.'))
    } finally {
      setSaving(false)
    }
  }

  async function handleCancelBooking() {
    if (!booking) {
      return
    }

    const confirmed = window.confirm('Cancel this booking?')
    if (!confirmed) {
      return
    }

    setSaving(true)
    setError('')
    setMessage('')

    try {
      await cancelBooking(booking.id)
      await refreshBooking()
      setMessage('Booking cancelled successfully.')
    } catch (err) {
      setError(getFriendlyError(err, 'Could not cancel this booking. Please try again.'))
    } finally {
      setSaving(false)
    }
  }

  async function handleAssignTable(tableId, confirmOversized = false) {
    if (!booking) {
      return
    }

    setAssigningTableId(tableId)
    setError('')
    setMessage('')

    try {
      await assignBookingTable(booking.id, tableId, { confirmOversized })
      setOversizedConfirm(null)
      await refreshBooking()
      setMessage('Table assignment updated successfully.')
    } catch (err) {
      const hasOversizedError = Boolean(err?.payload?.errors?.confirm_oversized)

      if (!confirmOversized && err.status === 422 && hasOversizedError) {
        setOversizedConfirm({
          bookingId: booking.id,
          tableId,
          warning: err.payload?.warning || null,
        })
      } else {
        setError(getFriendlyError(err, 'Could not assign a table. Please review details and try again.'))
      }
    } finally {
      setAssigningTableId(null)
    }
  }

  return (
    <AdminGuard>
      <section className="admin-shell">
        <header className="admin-head">
          <div>
            <p className="menu-kicker">Staff</p>
            <h1 className="admin-title">Booking Detail</h1>
            <p className="admin-muted">Update guest details, status, notes, and table assignment.</p>
          </div>
          <div className="admin-actions">
            <Link className="admin-btn-secondary" to="/admin/bookings">
              Back to bookings
            </Link>
            <button className="admin-mini-btn danger" onClick={handleCancelBooking} disabled={saving || !booking}>
              Cancel booking
            </button>
          </div>
        </header>

        {message ? <p className="admin-success">{message}</p> : null}
        {error ? <p className="admin-error">{error}</p> : null}

        {loading ? <p className="menu-state">Loading booking...</p> : null}

        {!loading && booking ? (
          <section className="booking-layout">
            <section className="admin-card">
              <form className="admin-form" onSubmit={handleSubmit}>
                <div className="admin-grid">
                  <label>
                    <span>Guest name</span>
                    <input
                      value={form.guest_name}
                      onChange={(event) => setForm((prev) => ({ ...prev, guest_name: event.target.value }))}
                      required
                    />
                  </label>

                  <label>
                    <span>Phone</span>
                    <input
                      value={form.guest_phone}
                      onChange={(event) => setForm((prev) => ({ ...prev, guest_phone: event.target.value }))}
                    />
                  </label>

                  <label>
                    <span>Email</span>
                    <input
                      type="email"
                      value={form.guest_email}
                      onChange={(event) => setForm((prev) => ({ ...prev, guest_email: event.target.value }))}
                    />
                  </label>

                  <label>
                    <span>Party size</span>
                    <input
                      type="number"
                      min="1"
                      value={form.party_size}
                      onChange={(event) => setForm((prev) => ({ ...prev, party_size: event.target.value }))}
                    />
                  </label>

                  <label>
                    <span>Booking start</span>
                    <input
                      type="datetime-local"
                      value={form.booking_start}
                      onChange={(event) => setForm((prev) => ({ ...prev, booking_start: event.target.value }))}
                    />
                  </label>

                  <label>
                    <span>Booking end</span>
                    <input
                      type="datetime-local"
                      value={form.booking_end}
                      onChange={(event) => setForm((prev) => ({ ...prev, booking_end: event.target.value }))}
                    />
                  </label>

                  <label>
                    <span>Status</span>
                    <select value={form.status} onChange={(event) => setForm((prev) => ({ ...prev, status: event.target.value }))}>
                      {STATUS_OPTIONS.map((status) => (
                        <option key={status} value={status} disabled={!allowedStatuses.has(status)}>
                          {status}
                        </option>
                      ))}
                    </select>
                  </label>
                </div>

                <p className="admin-muted">
                  Allowed transitions from <strong>{booking.status}</strong> are enabled; terminal states stay locked.
                </p>

                <label>
                  <span>Notes</span>
                  <textarea
                    rows={4}
                    value={form.notes}
                    onChange={(event) => setForm((prev) => ({ ...prev, notes: event.target.value }))}
                  />
                </label>

                <div className="admin-actions">
                  <button className="admin-cta" type="submit" disabled={saving}>
                    {saving ? 'Saving...' : 'Save changes'}
                  </button>
                  <button className="admin-btn-secondary" type="button" onClick={() => loadAvailability()} disabled={availabilityLoading}>
                    {availabilityLoading ? 'Checking...' : 'Refresh table options'}
                  </button>
                </div>
              </form>
            </section>

            <aside className="admin-card">
              <h2 className="booking-panel-title">Table Assignment</h2>
              <p className="admin-muted">
                Current table: {booking.table_id ? tableMap.get(booking.table_id)?.name || booking.table_id : 'Not assigned'}
              </p>

              {availabilityError ? <p className="admin-error">{availabilityError}</p> : null}

              {!availabilityLoading ? (
                <div className="booking-options">
                  <TableOptions
                    title="Recommended"
                    emptyLabel="No recommended tables available"
                    options={availability.recommended_tables}
                    assigningTableId={assigningTableId}
                    onAssign={(tableId) => handleAssignTable(tableId)}
                  />
                  <TableOptions
                    title="Alternative larger tables"
                    emptyLabel="No larger-table alternatives"
                    options={availability.larger_tables}
                    assigningTableId={assigningTableId}
                    onAssign={(tableId) => handleAssignTable(tableId)}
                    oversized
                  />
                </div>
              ) : (
                <p className="menu-state">Checking table availability...</p>
              )}
            </aside>

            <aside className="admin-card">
              <h2 className="booking-panel-title">Activity</h2>

              {events.length === 0 ? <p className="admin-muted">No activity has been recorded yet.</p> : null}

              {events.length > 0 ? (
                <div className="booking-events-list">
                  {events.map((event) => (
                    <article className="booking-event-item" key={event.id || `${event.event_type}-${event.created_at}`}>
                      <h3>{formatEventTitle(event)}</h3>
                      <p className="admin-muted">{formatEventDetail(event)}</p>
                      <p className="admin-muted">{toReadableDateTime(event.created_at)}</p>
                    </article>
                  ))}
                </div>
              ) : null}
            </aside>
          </section>
        ) : null}

        {oversizedConfirm ? (
          <section className="admin-modal-backdrop" role="dialog" aria-modal="true" aria-label="Oversized table confirmation">
            <div className="admin-modal-card">
              <h2>Confirm oversized table assignment</h2>
              <p>
                {oversizedConfirm.warning?.message ||
                  'This table has 4 or more extra seats. Please confirm to continue.'}
              </p>

              <ul className="admin-modal-list">
                <li>Table: {oversizedConfirm.warning?.table_name || oversizedConfirm.warning?.table_id || oversizedConfirm.tableId}</li>
                <li>Party size: {oversizedConfirm.warning?.party_size ?? '-'}</li>
                <li>Seats: {oversizedConfirm.warning?.seats ?? '-'}</li>
                <li>Extra seats: {oversizedConfirm.warning?.extra_seats ?? '-'}</li>
              </ul>

              <div className="admin-actions">
                <button className="admin-btn-secondary" onClick={() => setOversizedConfirm(null)}>
                  Cancel
                </button>
                <button
                  className="admin-cta"
                  onClick={() => handleAssignTable(oversizedConfirm.tableId, true)}
                  disabled={assigningTableId === oversizedConfirm.tableId}
                >
                  {assigningTableId === oversizedConfirm.tableId ? 'Assigning...' : 'Confirm assignment'}
                </button>
              </div>
            </div>
          </section>
        ) : null}
      </section>
    </AdminGuard>
  )
}

function TableOptions({ title, emptyLabel, options, oversized = false, assigningTableId, onAssign }) {
  return (
    <section className="booking-option-group">
      <h3>{title}</h3>
      {options.length === 0 ? <p className="admin-muted">{emptyLabel}</p> : null}

      {options.map((table) => (
        <article key={table.id} className="booking-option-card">
          <div>
            <strong>{table.name}</strong>
            <p className="admin-muted">
              Seats: {table.seats}
              {typeof table.extra_seats === 'number' ? ` · Extra seats: ${table.extra_seats}` : ''}
            </p>
          </div>

          <button className="admin-mini-btn" onClick={() => onAssign(table.id)} disabled={assigningTableId === table.id}>
            {assigningTableId === table.id ? 'Assigning...' : oversized ? 'Assign (confirm if warned)' : 'Assign'}
          </button>
        </article>
      ))}
    </section>
  )
}

function defaultForm() {
  return {
    guest_name: '',
    guest_phone: '',
    guest_email: '',
    party_size: '2',
    booking_start: '',
    booking_end: '',
    status: 'pending',
    notes: '',
  }
}

function toFormState(booking) {
  return {
    guest_name: booking.guest_name || '',
    guest_phone: booking.guest_phone || '',
    guest_email: booking.guest_email || '',
    party_size: String(booking.party_size || ''),
    booking_start: toDateTimeLocal(booking.booking_start),
    booking_end: toDateTimeLocal(booking.booking_end),
    status: booking.status || 'pending',
    notes: booking.notes || '',
  }
}

function toDateTimeLocal(value) {
  if (!value) {
    return ''
  }

  const parsed = new Date(value.replace(' ', 'T'))
  if (Number.isNaN(parsed.getTime())) {
    return ''
  }

  const yyyy = String(parsed.getFullYear())
  const mm = String(parsed.getMonth() + 1).padStart(2, '0')
  const dd = String(parsed.getDate()).padStart(2, '0')
  const hh = String(parsed.getHours()).padStart(2, '0')
  const min = String(parsed.getMinutes()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}T${hh}:${min}`
}

function normalizeDateTimePayload(value) {
  if (!value) {
    return ''
  }

  const normalized = value.replace('T', ' ')
  return normalized.length === 16 ? `${normalized}:00` : normalized
}

function toReadableDateTime(value) {
  if (!value) {
    return '-'
  }

  const date = new Date(String(value).replace(' ', 'T'))
  if (Number.isNaN(date.getTime())) {
    return String(value)
  }

  return new Intl.DateTimeFormat('en-GB', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date)
}

function formatEventTitle(event) {
  const map = {
    booking_created: 'Booking created',
    booking_details_updated: 'Booking details updated',
    booking_status_changed: 'Booking status changed',
    booking_cancelled: 'Booking cancelled',
    booking_table_assigned: 'Table assigned',
    booking_table_reassigned: 'Table reassigned',
    booking_table_unassigned: 'Table unassigned',
  }

  return map[event?.event_type] || 'Booking updated'
}

function formatEventDetail(event) {
  if (!event || typeof event !== 'object') {
    return 'Activity recorded.'
  }

  if (event.event_type === 'booking_status_changed') {
    return `From ${event.from_value || '-'} to ${event.to_value || '-'}.`
  }

  if (
    event.event_type === 'booking_table_assigned' ||
    event.event_type === 'booking_table_reassigned' ||
    event.event_type === 'booking_table_unassigned'
  ) {
    return `From table ${event.from_value || 'none'} to ${event.to_value || 'none'}.`
  }

  if (event.event_type === 'booking_details_updated') {
    const fields = Array.isArray(event?.meta?.changed_fields) ? event.meta.changed_fields : []
    return fields.length > 0 ? `Changed: ${fields.join(', ')}.` : 'One or more booking fields were changed.'
  }

  return 'Activity recorded.'
}
