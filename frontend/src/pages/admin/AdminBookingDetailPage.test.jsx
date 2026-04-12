import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import AdminBookingDetailPage from './AdminBookingDetailPage'

const mockFetchBooking = vi.fn()
const mockFetchBookingAvailability = vi.fn()
const mockAssignBookingTable = vi.fn()
const mockUpdateBooking = vi.fn()
const mockCancelBooking = vi.fn()
const mockFetchTables = vi.fn()

vi.mock('../../components/AdminGuard', () => ({
  default: ({ children }) => children,
}))

vi.mock('../../lib/bookings', () => ({
  fetchBooking: (...args) => mockFetchBooking(...args),
  fetchBookingAvailability: (...args) => mockFetchBookingAvailability(...args),
  assignBookingTable: (...args) => mockAssignBookingTable(...args),
  updateBooking: (...args) => mockUpdateBooking(...args),
  cancelBooking: (...args) => mockCancelBooking(...args),
}))

vi.mock('../../lib/tables', () => ({
  fetchTables: (...args) => mockFetchTables(...args),
}))

describe('AdminBookingDetailPage', () => {
  beforeEach(() => {
    mockCancelBooking.mockResolvedValue({ booking: { id: 42 } })
    mockFetchTables.mockResolvedValue({ tables: [{ id: 9, name: 'Patio 9', seats: 8, is_active: true }] })
    mockFetchBooking.mockResolvedValue({
      booking: {
        id: 42,
        guest_name: 'Taylor',
        guest_phone: '07000000000',
        guest_email: 'taylor@example.com',
        party_size: 2,
        booking_start: '2026-04-10 18:00:00',
        booking_end: '2026-04-10 19:30:00',
        status: 'confirmed',
        table_id: null,
        notes: 'Near window',
      },
      events: [
        {
          id: 1,
          event_type: 'booking_created',
          from_value: null,
          to_value: null,
          created_at: '2026-04-10 10:00:00',
          meta: null,
        },
      ],
    })
    mockFetchBookingAvailability.mockResolvedValue({
      recommended_tables: [],
      larger_tables: [{ id: 9, name: 'Patio 9', seats: 8, extra_seats: 6 }],
      busy_table_ids: [],
    })
  })

  test('saves booking detail updates', async () => {
    const user = userEvent.setup()
    mockUpdateBooking.mockResolvedValue({ booking: { id: 42 } })

    renderWithRoute()

    await waitFor(() => {
      expect(screen.getByDisplayValue('Taylor')).toBeInTheDocument()
    })

    const notes = screen.getByLabelText('Notes')
    await user.clear(notes)
    await user.type(notes, 'Birthday dinner')

    await user.click(screen.getByRole('button', { name: 'Save changes' }))

    await waitFor(() => {
      expect(mockUpdateBooking).toHaveBeenCalledTimes(1)
    })

    expect(mockUpdateBooking).toHaveBeenCalledWith(
      42,
      expect.objectContaining({
        guest_name: 'Taylor',
        status: 'confirmed',
        notes: 'Birthday dinner',
      }),
    )
  })

  test('requires confirmation for oversized assignment', async () => {
    const user = userEvent.setup()

    const oversizedError = new Error('Validation failed')
    oversizedError.status = 422
    oversizedError.payload = {
      message: 'Validation failed',
      errors: {
        confirm_oversized: 'Set confirm_oversized=true to assign an oversized table',
      },
      warning: {
        code: 'oversized_table_confirmation_required',
        table_name: 'Patio 9',
        party_size: 2,
        seats: 8,
        extra_seats: 6,
      },
    }

    mockAssignBookingTable
      .mockRejectedValueOnce(oversizedError)
      .mockResolvedValueOnce({ booking: { id: 42, table_id: 9 } })

    renderWithRoute()

    await waitFor(() => {
      expect(screen.getByText('Alternative larger tables')).toBeInTheDocument()
    })

    const optionCard = screen.getByText('Patio 9').closest('article')
    expect(optionCard).toBeTruthy()
    await user.click(within(optionCard).getByRole('button', { name: /Assign/ }))

    await waitFor(() => {
      expect(screen.getByRole('dialog', { name: 'Oversized table confirmation' })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: 'Confirm assignment' }))

    await waitFor(() => {
      expect(mockAssignBookingTable).toHaveBeenCalledTimes(2)
    })

    expect(mockAssignBookingTable).toHaveBeenNthCalledWith(1, 42, 9, { confirmOversized: false })
    expect(mockAssignBookingTable).toHaveBeenNthCalledWith(2, 42, 9, { confirmOversized: true })
  })

  test('disables invalid status transitions for terminal booking states', async () => {
    mockFetchBooking.mockResolvedValueOnce({
      booking: {
        id: 42,
        guest_name: 'Taylor',
        guest_phone: '07000000000',
        guest_email: 'taylor@example.com',
        party_size: 2,
        booking_start: '2026-04-10 18:00:00',
        booking_end: '2026-04-10 19:30:00',
        status: 'cancelled',
        table_id: null,
        notes: 'Near window',
      },
    })

    renderWithRoute()

    await waitFor(() => {
      expect(screen.getByDisplayValue('cancelled')).toBeInTheDocument()
    })

    const statusSelect = screen.getByLabelText('Status')
    const cancelledOption = within(statusSelect).getByRole('option', { name: 'cancelled' })
    const confirmedOption = within(statusSelect).getByRole('option', { name: 'confirmed' })
    const seatedOption = within(statusSelect).getByRole('option', { name: 'seated' })

    expect(cancelledOption).not.toBeDisabled()
    expect(confirmedOption).toBeDisabled()
    expect(seatedOption).toBeDisabled()
  })

  test('renders booking activity events', async () => {
    renderWithRoute()

    await waitFor(() => {
      expect(screen.getByText('Activity')).toBeInTheDocument()
    })

    expect(screen.getByText('Booking created')).toBeInTheDocument()
    expect(screen.getByText('Activity recorded.')).toBeInTheDocument()
  })

  test('uses booking party size when loading initial table availability', async () => {
    mockFetchBooking.mockResolvedValueOnce({
      booking: {
        id: 42,
        guest_name: 'Taylor',
        guest_phone: '07000000000',
        guest_email: 'taylor@example.com',
        party_size: 6,
        booking_start: '2026-04-10 18:00:00',
        booking_end: '2026-04-10 19:30:00',
        status: 'confirmed',
        table_id: null,
        notes: 'Near window',
      },
      events: [],
    })

    renderWithRoute()

    await waitFor(() => {
      expect(mockFetchBookingAvailability).toHaveBeenCalled()
    })

    expect(mockFetchBookingAvailability).toHaveBeenCalledWith(
      expect.objectContaining({
        partySize: 6,
      }),
    )
  })
})

function renderWithRoute() {
  return render(
    <MemoryRouter initialEntries={['/admin/bookings/42']}>
      <Routes>
        <Route path="/admin/bookings/:id" element={<AdminBookingDetailPage />} />
      </Routes>
    </MemoryRouter>,
  )
}
