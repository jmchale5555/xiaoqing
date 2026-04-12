import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import AdminBookingsPage from './AdminBookingsPage'

const mockFetchBookings = vi.fn()
const mockFetchAvailability = vi.fn()
const mockAssignBookingTable = vi.fn()
const mockCreateBooking = vi.fn()
const mockFetchTables = vi.fn()

vi.mock('../../components/AdminGuard', () => ({
  default: ({ children }) => children,
}))

vi.mock('../../lib/bookings', () => ({
  fetchBookings: (...args) => mockFetchBookings(...args),
  fetchBookingAvailability: (...args) => mockFetchAvailability(...args),
  assignBookingTable: (...args) => mockAssignBookingTable(...args),
  createBooking: (...args) => mockCreateBooking(...args),
}))

vi.mock('../../lib/tables', () => ({
  fetchTables: (...args) => mockFetchTables(...args),
}))

describe('AdminBookingsPage oversized assignment flow', () => {
  beforeEach(() => {
    mockCreateBooking.mockReset()
    mockCreateBooking.mockResolvedValue({ booking: { id: 99 } })
  })

  test('requires confirmation when backend warns oversized seating', async () => {
    const user = userEvent.setup()

    mockFetchBookings.mockResolvedValue({
      bookings: [
        {
          id: 42,
          guest_name: 'Taylor',
          guest_phone: '07000000000',
          party_size: 2,
          booking_start: '2026-04-10 18:00:00',
          booking_end: '2026-04-10 19:30:00',
          status: 'confirmed',
          table_id: null,
        },
      ],
    })

    mockFetchTables.mockResolvedValue({
      tables: [{ id: 9, name: 'Patio 9', seats: 8, is_active: true }],
    })

    mockFetchAvailability.mockResolvedValue({
      recommended_tables: [],
      larger_tables: [{ id: 9, name: 'Patio 9', seats: 8, extra_seats: 6 }],
      busy_table_ids: [],
    })

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

    render(
      <MemoryRouter>
        <AdminBookingsPage />
      </MemoryRouter>,
    )

    await waitFor(() => {
      expect(screen.getByText('Taylor')).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: 'Assign table' }))

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

  test('creates booking from inline modal', async () => {
    const user = userEvent.setup()

    mockFetchBookings
      .mockResolvedValueOnce({ bookings: [] })
      .mockResolvedValueOnce({
        bookings: [
          {
            id: 99,
            guest_name: 'New Guest',
            guest_phone: '07001112222',
            party_size: 3,
            booking_start: '2026-04-11 18:00:00',
            booking_end: '2026-04-11 19:30:00',
            status: 'pending',
            table_id: null,
          },
        ],
      })

    mockFetchTables.mockResolvedValue({
      tables: [{ id: 7, name: 'Main 7', seats: 4, is_active: true }],
    })

    mockFetchAvailability.mockResolvedValue({
      recommended_tables: [],
      larger_tables: [],
      busy_table_ids: [],
    })

    render(
      <MemoryRouter>
        <AdminBookingsPage />
      </MemoryRouter>,
    )

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'New booking' })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: 'New booking' }))

    await waitFor(() => {
      expect(screen.getByRole('dialog', { name: 'New booking modal' })).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText('Guest name'), 'New Guest')
    await user.clear(screen.getByLabelText('Party size'))
    await user.type(screen.getByLabelText('Party size'), '3')

    await user.click(screen.getByRole('button', { name: 'Create booking' }))

    await waitFor(() => {
      expect(mockCreateBooking).toHaveBeenCalledTimes(1)
    })

    expect(mockCreateBooking).toHaveBeenCalledWith(
      expect.objectContaining({
        guest_name: 'New Guest',
        party_size: 3,
        status: 'pending',
      }),
    )

    await waitFor(() => {
      expect(screen.getByText('Booking created successfully.')).toBeInTheDocument()
    })
  })

  test('blocks create when booking end is not after start', async () => {
    const user = userEvent.setup()

    mockFetchBookings.mockResolvedValue({ bookings: [] })
    mockFetchTables.mockResolvedValue({
      tables: [{ id: 7, name: 'Main 7', seats: 4, is_active: true }],
    })

    render(
      <MemoryRouter>
        <AdminBookingsPage />
      </MemoryRouter>,
    )

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'New booking' })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: 'New booking' }))

    const start = screen.getByLabelText('Booking start')
    const end = screen.getByLabelText('Booking end')
    await user.clear(start)
    await user.type(start, '2026-04-12T19:00')
    await user.clear(end)
    await user.type(end, '2026-04-12T18:00')

    await user.type(screen.getByLabelText('Guest name'), 'Late Guest')
    await user.clear(screen.getByLabelText('Party size'))
    await user.type(screen.getByLabelText('Party size'), '2')

    await user.click(screen.getByRole('button', { name: 'Create booking' }))

    expect(mockCreateBooking).not.toHaveBeenCalled()
    expect(screen.getByText('Booking end must be after booking start.')).toBeInTheDocument()
  })

  test('closes create modal on Escape key', async () => {
    const user = userEvent.setup()

    mockFetchBookings.mockResolvedValue({ bookings: [] })
    mockFetchTables.mockResolvedValue({
      tables: [{ id: 7, name: 'Main 7', seats: 4, is_active: true }],
    })

    render(
      <MemoryRouter>
        <AdminBookingsPage />
      </MemoryRouter>,
    )

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'New booking' })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: 'New booking' }))

    await waitFor(() => {
      expect(screen.getByRole('dialog', { name: 'New booking modal' })).toBeInTheDocument()
    })

    await user.keyboard('{Escape}')

    await waitFor(() => {
      expect(screen.queryByRole('dialog', { name: 'New booking modal' })).not.toBeInTheDocument()
    })
  })
})
