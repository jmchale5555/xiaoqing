import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import AdminBookingsPage from './AdminBookingsPage'

const mockFetchBookings = vi.fn()
const mockFetchAvailability = vi.fn()
const mockAssignBookingTable = vi.fn()
const mockFetchTables = vi.fn()

vi.mock('../../components/AdminGuard', () => ({
  default: ({ children }) => children,
}))

vi.mock('../../lib/bookings', () => ({
  fetchBookings: (...args) => mockFetchBookings(...args),
  fetchBookingAvailability: (...args) => mockFetchAvailability(...args),
  assignBookingTable: (...args) => mockAssignBookingTable(...args),
}))

vi.mock('../../lib/tables', () => ({
  fetchTables: (...args) => mockFetchTables(...args),
}))

describe('AdminBookingsPage oversized assignment flow', () => {
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
})
