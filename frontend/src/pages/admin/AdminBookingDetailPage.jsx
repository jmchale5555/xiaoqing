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
import { fetchTables } from '../../lib/tables'

const STATUS_OPTIONS = ['pending', 'confirmed', 'seated', 'completed', 'cancelled', 'no_show']

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

  const tableMap = useMemo(() => {
    const map = new Map()
    tables.forEach((table) => map.set(table.id, table))
    return map
  }, [tables])

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
        setTables(Array.isArray(tablesData.tables) ? tablesData.tables : [])
        setForm(nextBooking ? toFormState(nextBooking) : defaultForm())

        if (nextBooking) {
          await loadAvailability(nextBooking)
        }
      } catch (err) {
        if (!mounted) {
          return
        }
        setError(err.message || 'Unable to load booking')
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
      setAvailabilityError(err.message || 'Unable to load table options')
    } finally {
      setAvailabilityLoading(false)
    }
  }

  async function refreshBooking() {
    const bookingData = await fetchBooking(id)
    const nextBooking = bookingData.booking || null
    setBooking(nextBooking)
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
      setMessage('Booking details saved.')
    } catch (err) {
      setError(err.message || 'Unable to save booking')
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
      setMessage('Booking cancelled.')
    } catch (err) {
      setError(err.message || 'Unable to cancel booking')
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
      setMessage('Table assignment saved.')
    } catch (err) {
      const hasOversizedError = Boolean(err?.payload?.errors?.confirm_oversized)

      if (!confirmOversized && err.status === 422 && hasOversizedError) {
        setOversizedConfirm({
          bookingId: booking.id,
          tableId,
          warning: err.payload?.warning || null,
        })
      } else {
        setError(err.message || 'Unable to assign table')
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
                        <option key={status} value={status}>
                          {status}
                        </option>
                      ))}
                    </select>
                  </label>
                </div>

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
