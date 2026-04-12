import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import AdminGuard from '../../components/AdminGuard'
import { assignBookingTable, createBooking, fetchBookingAvailability, fetchBookings } from '../../lib/bookings'
import { fetchTables } from '../../lib/tables'

const STATUS_OPTIONS = [
  { label: 'All statuses', value: '' },
  { label: 'Pending', value: 'pending' },
  { label: 'Confirmed', value: 'confirmed' },
  { label: 'Seated', value: 'seated' },
  { label: 'Completed', value: 'completed' },
  { label: 'Cancelled', value: 'cancelled' },
  { label: 'No show', value: 'no_show' },
]

export default function AdminBookingsPage() {
  const [bookings, setBookings] = useState([])
  const [tables, setTables] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [dateFilter, setDateFilter] = useState('')

  const [selectedBookingId, setSelectedBookingId] = useState(null)
  const [availability, setAvailability] = useState({
    recommended_tables: [],
    larger_tables: [],
    busy_table_ids: [],
  })
  const [availabilityLoading, setAvailabilityLoading] = useState(false)
  const [availabilityError, setAvailabilityError] = useState('')
  const [assigningTableId, setAssigningTableId] = useState(null)

  const [oversizedConfirm, setOversizedConfirm] = useState(null)
  const [showCreateModal, setShowCreateModal] = useState(false)
  const [createForm, setCreateForm] = useState(defaultCreateForm())
  const [createError, setCreateError] = useState('')
  const [creating, setCreating] = useState(false)
  const [createOversizedConfirm, setCreateOversizedConfirm] = useState(null)

  const tableMap = useMemo(() => {
    const map = new Map()
    tables.forEach((table) => {
      map.set(table.id, table)
    })
    return map
  }, [tables])

  const selectedBooking = useMemo(
    () => bookings.find((booking) => booking.id === selectedBookingId) || null,
    [bookings, selectedBookingId],
  )

  useEffect(() => {
    let mounted = true

    async function load() {
      setLoading(true)
      setError('')

      try {
        const [bookingsData, tablesData] = await Promise.all([
          fetchBookings({ status: statusFilter, date: dateFilter }),
          fetchTables({ isActive: true }),
        ])

        if (!mounted) {
          return
        }

        setBookings(Array.isArray(bookingsData.bookings) ? bookingsData.bookings : [])
        setTables(Array.isArray(tablesData.tables) ? tablesData.tables : [])
      } catch (err) {
        if (!mounted) {
          return
        }
        setError(err.message || 'Unable to load bookings')
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
  }, [statusFilter, dateFilter])

  async function reloadBookings() {
    const updated = await fetchBookings({ status: statusFilter, date: dateFilter })
    return Array.isArray(updated.bookings) ? updated.bookings : []
  }

  async function loadAvailability(booking) {
    setAvailabilityLoading(true)
    setAvailabilityError('')

    try {
      const data = await fetchBookingAvailability({
        partySize: booking.party_size,
        bookingStart: booking.booking_start,
        bookingEnd: booking.booking_end,
        excludeBookingId: booking.id,
      })

      setAvailability({
        recommended_tables: Array.isArray(data.recommended_tables) ? data.recommended_tables : [],
        larger_tables: Array.isArray(data.larger_tables) ? data.larger_tables : [],
        busy_table_ids: Array.isArray(data.busy_table_ids) ? data.busy_table_ids : [],
      })
    } catch (err) {
      setAvailability({ recommended_tables: [], larger_tables: [], busy_table_ids: [] })
      setAvailabilityError(err.message || 'Unable to load table availability')
    } finally {
      setAvailabilityLoading(false)
    }
  }

  async function selectBookingForAssignment(booking) {
    setSelectedBookingId(booking.id)
    setMessage('')
    setError('')
    setOversizedConfirm(null)
    await loadAvailability(booking)
  }

  async function assignTable(bookingId, tableId, confirmOversized = false) {
    if (!bookingId || !tableId) {
      return
    }

    setAssigningTableId(tableId)
    setError('')
    setMessage('')

    try {
      await assignBookingTable(bookingId, tableId, { confirmOversized })
      setOversizedConfirm(null)
      setMessage('Table assignment saved.')

      const nextBookings = await reloadBookings()
      setBookings(nextBookings)

      const current = nextBookings.find((booking) => booking.id === bookingId)
      if (current) {
        await loadAvailability(current)
      }
    } catch (err) {
      const hasOversizedError = Boolean(err?.payload?.errors?.confirm_oversized)

      if (!confirmOversized && err.status === 422 && hasOversizedError) {
        setOversizedConfirm({
          bookingId,
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

  function openCreateModal() {
    const now = new Date()
    const end = new Date(now.getTime() + 90 * 60 * 1000)

    setCreateForm({
      ...defaultCreateForm(),
      booking_start: toDateTimeLocal(now),
      booking_end: toDateTimeLocal(end),
    })
    setCreateError('')
    setCreateOversizedConfirm(null)
    setShowCreateModal(true)
  }

  function closeCreateModal() {
    setShowCreateModal(false)
    setCreateError('')
    setCreateOversizedConfirm(null)
    setCreating(false)
  }

  async function submitCreateBooking(confirmOversized = false) {
    const normalizedStart = normalizeDateTimePayload(createForm.booking_start)
    const normalizedEnd = normalizeDateTimePayload(createForm.booking_end)

    if (!normalizedStart || !normalizedEnd) {
      setCreateError('Booking start and end are required.')
      return
    }

    if (new Date(normalizedEnd.replace(' ', 'T')).getTime() <= new Date(normalizedStart.replace(' ', 'T')).getTime()) {
      setCreateError('Booking end must be after booking start.')
      return
    }

    setCreating(true)
    setCreateError('')
    setMessage('')

    const payload = {
      guest_name: createForm.guest_name.trim(),
      guest_phone: createForm.guest_phone.trim(),
      guest_email: createForm.guest_email.trim(),
      party_size: Number(createForm.party_size || 0),
      booking_start: normalizedStart,
      booking_end: normalizedEnd,
      status: createForm.status,
      notes: createForm.notes.trim(),
      ...(createForm.table_id ? { table_id: Number(createForm.table_id) } : {}),
      ...(confirmOversized ? { confirm_oversized: true } : {}),
    }

    try {
      const created = await createBooking(payload)
      const createdBooking = created.booking || null
      const nextBookings = await reloadBookings()
      setBookings(nextBookings)

      if (createdBooking?.id) {
        const current = nextBookings.find((booking) => booking.id === createdBooking.id)
        if (current) {
          setSelectedBookingId(current.id)
          await loadAvailability(current)
        }
      }

      setShowCreateModal(false)
      setCreateOversizedConfirm(null)
      setMessage('Booking created.')
    } catch (err) {
      const hasOversizedError = Boolean(err?.payload?.errors?.confirm_oversized)

      if (!confirmOversized && err.status === 422 && hasOversizedError) {
        setCreateOversizedConfirm({
          warning: err.payload?.warning || null,
        })
      } else {
        setCreateError(err.message || 'Unable to create booking')
      }
    } finally {
      setCreating(false)
    }
  }

  async function handleCreateSubmit(event) {
    event.preventDefault()
    await submitCreateBooking(false)
  }

  return (
    <AdminGuard>
      <section className="admin-shell">
        <header className="admin-head">
          <div>
            <p className="menu-kicker">Staff</p>
            <h1 className="admin-title">Bookings Board</h1>
            <p className="admin-muted">Review bookings and assign tables with confirmation for oversized seating.</p>
          </div>
          <div className="admin-actions">
            <button className="admin-cta" onClick={openCreateModal}>
              New booking
            </button>
          </div>
        </header>

        <section className="admin-card booking-filter-grid">
          <label>
            <span>Status</span>
            <select value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)}>
              {STATUS_OPTIONS.map((option) => (
                <option key={option.value || 'all'} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </label>

          <label>
            <span>Date</span>
            <input type="date" value={dateFilter} onChange={(event) => setDateFilter(event.target.value)} />
          </label>
        </section>

        {message ? <p className="admin-success">{message}</p> : null}
        {error ? <p className="admin-error">{error}</p> : null}

        {loading ? <p className="menu-state">Loading bookings...</p> : null}

        {!loading && bookings.length === 0 ? <p className="menu-state">No bookings for the selected filters.</p> : null}

        {!loading && bookings.length > 0 ? (
          <section className="booking-layout">
            <div className="admin-table-wrap">
              <table className="admin-table">
                <thead>
                  <tr>
                    <th>Guest</th>
                    <th>Party</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th>Table</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {bookings.map((booking) => {
                    const currentTable = booking.table_id ? tableMap.get(booking.table_id) : null

                    return (
                      <tr key={booking.id} className={booking.id === selectedBookingId ? 'booking-row-active' : ''}>
                        <td>
                          <strong>{booking.guest_name}</strong>
                          <p className="admin-muted">{booking.guest_phone || 'No phone'}</p>
                        </td>
                        <td>{booking.party_size}</td>
                        <td>
                          <div>{toReadableDateTime(booking.booking_start)}</div>
                          <small className="admin-muted">to {toReadableDateTime(booking.booking_end)}</small>
                        </td>
                        <td>
                          <span className="admin-chip">{booking.status}</span>
                        </td>
                        <td>{currentTable?.name || booking.table_id || '-'}</td>
                        <td>
                          <div className="admin-order-controls">
                            <button
                              className="admin-mini-btn"
                              onClick={() => selectBookingForAssignment(booking)}
                              disabled={assigningTableId !== null}
                            >
                              Assign table
                            </button>
                            <Link className="admin-mini-btn" to={`/admin/bookings/${booking.id}`}>
                              Details
                            </Link>
                          </div>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>

            <aside className="admin-card">
              <h2 className="booking-panel-title">Assignment Assist</h2>

              {!selectedBooking ? <p className="admin-muted">Select a booking to view table options.</p> : null}

              {selectedBooking ? (
                <>
                  <p className="admin-muted">
                    Booking #{selectedBooking.id} for {selectedBooking.guest_name}, party of {selectedBooking.party_size}.
                  </p>

                  {availabilityLoading ? <p className="menu-state">Checking table availability...</p> : null}
                  {availabilityError ? <p className="admin-error">{availabilityError}</p> : null}

                  {!availabilityLoading && !availabilityError ? (
                    <div className="booking-options">
                      <TableOptions
                        title="Recommended"
                        emptyLabel="No recommended tables available"
                        options={availability.recommended_tables}
                        assigningTableId={assigningTableId}
                        onAssign={(tableId) => assignTable(selectedBooking.id, tableId)}
                      />

                      <TableOptions
                        title="Alternative larger tables"
                        emptyLabel="No larger-table alternatives"
                        options={availability.larger_tables}
                        assigningTableId={assigningTableId}
                        onAssign={(tableId) => assignTable(selectedBooking.id, tableId)}
                        oversized
                      />
                    </div>
                  ) : null}
                </>
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
                  onClick={() => assignTable(oversizedConfirm.bookingId, oversizedConfirm.tableId, true)}
                  disabled={assigningTableId === oversizedConfirm.tableId}
                >
                  {assigningTableId === oversizedConfirm.tableId ? 'Assigning...' : 'Confirm assignment'}
                </button>
              </div>
            </div>
          </section>
        ) : null}

        {showCreateModal ? (
          <section className="admin-modal-backdrop" role="dialog" aria-modal="true" aria-label="New booking modal">
            <div className="admin-modal-card">
              <h2>New booking</h2>

              {createError ? <p className="admin-error">{createError}</p> : null}

              <form className="admin-form" onSubmit={handleCreateSubmit}>
                <div className="admin-grid">
                  <label>
                    <span>Guest name</span>
                    <input
                      required
                      value={createForm.guest_name}
                      onChange={(event) => setCreateForm((prev) => ({ ...prev, guest_name: event.target.value }))}
                    />
                  </label>

                  <label>
                    <span>Party size</span>
                    <input
                      required
                      type="number"
                      min="1"
                      value={createForm.party_size}
                      onChange={(event) => setCreateForm((prev) => ({ ...prev, party_size: event.target.value }))}
                    />
                  </label>

                  <label>
                    <span>Phone</span>
                    <input
                      value={createForm.guest_phone}
                      onChange={(event) => setCreateForm((prev) => ({ ...prev, guest_phone: event.target.value }))}
                    />
                  </label>

                  <label>
                    <span>Email</span>
                    <input
                      type="email"
                      value={createForm.guest_email}
                      onChange={(event) => setCreateForm((prev) => ({ ...prev, guest_email: event.target.value }))}
                    />
                  </label>

                  <label>
                    <span>Booking start</span>
                    <input
                      required
                      type="datetime-local"
                      value={createForm.booking_start}
                      onChange={(event) => setCreateForm((prev) => ({ ...prev, booking_start: event.target.value }))}
                    />
                  </label>

                  <label>
                    <span>Booking end</span>
                    <input
                      required
                      type="datetime-local"
                      value={createForm.booking_end}
                      onChange={(event) => setCreateForm((prev) => ({ ...prev, booking_end: event.target.value }))}
                    />
                  </label>

                  <label>
                    <span>Status</span>
                    <select
                      value={createForm.status}
                      onChange={(event) => setCreateForm((prev) => ({ ...prev, status: event.target.value }))}
                    >
                      {STATUS_OPTIONS.filter((option) => option.value).map((option) => (
                        <option key={option.value} value={option.value}>
                          {option.label}
                        </option>
                      ))}
                    </select>
                  </label>

                  <label>
                    <span>Optional table</span>
                    <select
                      value={createForm.table_id}
                      onChange={(event) => setCreateForm((prev) => ({ ...prev, table_id: event.target.value }))}
                    >
                      <option value="">Unassigned</option>
                      {tables.map((table) => (
                        <option key={table.id} value={String(table.id)}>
                          {table.name} ({table.seats} seats)
                        </option>
                      ))}
                    </select>
                  </label>
                </div>

                <label>
                  <span>Notes</span>
                  <textarea
                    rows={3}
                    value={createForm.notes}
                    onChange={(event) => setCreateForm((prev) => ({ ...prev, notes: event.target.value }))}
                  />
                </label>

                <div className="admin-actions">
                  <button type="button" className="admin-btn-secondary" onClick={closeCreateModal} disabled={creating}>
                    Cancel
                  </button>
                  <button type="submit" className="admin-cta" disabled={creating}>
                    {creating ? 'Creating...' : 'Create booking'}
                  </button>
                </div>
              </form>
            </div>
          </section>
        ) : null}

        {createOversizedConfirm ? (
          <section className="admin-modal-backdrop" role="dialog" aria-modal="true" aria-label="Confirm oversized create assignment">
            <div className="admin-modal-card">
              <h2>Confirm oversized table assignment</h2>
              <p>
                {createOversizedConfirm.warning?.message ||
                  'This table has 4 or more extra seats. Please confirm to continue.'}
              </p>
              <ul className="admin-modal-list">
                <li>Table: {createOversizedConfirm.warning?.table_name || createOversizedConfirm.warning?.table_id || '-'}</li>
                <li>Party size: {createOversizedConfirm.warning?.party_size ?? '-'}</li>
                <li>Seats: {createOversizedConfirm.warning?.seats ?? '-'}</li>
                <li>Extra seats: {createOversizedConfirm.warning?.extra_seats ?? '-'}</li>
              </ul>
              <div className="admin-actions">
                <button className="admin-btn-secondary" onClick={() => setCreateOversizedConfirm(null)} disabled={creating}>
                  Cancel
                </button>
                <button className="admin-cta" onClick={() => submitCreateBooking(true)} disabled={creating}>
                  {creating ? 'Creating...' : 'Confirm and create'}
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

function toReadableDateTime(value) {
  if (!value) {
    return '-'
  }

  const date = new Date(value.replace(' ', 'T'))
  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('en-GB', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date)
}

function toDateTimeLocal(value) {
  const source = value instanceof Date ? value : new Date(value)

  if (Number.isNaN(source.getTime())) {
    return ''
  }

  const yyyy = String(source.getFullYear())
  const mm = String(source.getMonth() + 1).padStart(2, '0')
  const dd = String(source.getDate()).padStart(2, '0')
  const hh = String(source.getHours()).padStart(2, '0')
  const min = String(source.getMinutes()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}T${hh}:${min}`
}

function normalizeDateTimePayload(value) {
  if (!value) {
    return ''
  }

  const normalized = value.replace('T', ' ')
  return normalized.length === 16 ? `${normalized}:00` : normalized
}

function defaultCreateForm() {
  return {
    guest_name: '',
    guest_phone: '',
    guest_email: '',
    party_size: '2',
    booking_start: '',
    booking_end: '',
    status: 'pending',
    table_id: '',
    notes: '',
  }
}
