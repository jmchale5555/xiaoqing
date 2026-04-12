import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import AdminMenuPage from './AdminMenuPage'

const mockFetchMenu = vi.fn()
const mockReorder = vi.fn()
const mockDelete = vi.fn()
const mockUpdate = vi.fn()

vi.mock('../../components/AdminGuard', () => ({
  default: ({ children }) => children,
}))

vi.mock('../../lib/menu', () => ({
  fetchMenu: (...args) => mockFetchMenu(...args),
  reorderMenuItems: (...args) => mockReorder(...args),
  deleteMenuItem: (...args) => mockDelete(...args),
  updateMenuItem: (...args) => mockUpdate(...args),
}))

describe('AdminMenuPage reorder', () => {
  beforeEach(() => {
    mockDelete.mockResolvedValue({ ok: true })
    mockUpdate.mockResolvedValue({ item: {} })
  })

  test('restores last saved order if save fails', async () => {
    const user = userEvent.setup()

    const initialItems = [
      { id: 1, name: 'Dish A', description: 'A', category: 'Cat', price_pence: 1000, is_available: true, display_order: 0 },
      { id: 2, name: 'Dish B', description: 'B', category: 'Cat', price_pence: 1100, is_available: true, display_order: 1 },
      { id: 3, name: 'Dish C', description: 'C', category: 'Cat', price_pence: 1200, is_available: true, display_order: 2 },
    ]

    mockFetchMenu.mockResolvedValue({ items: initialItems })
    mockReorder.mockRejectedValue(new Error('Network issue'))

    const { container } = render(
      <MemoryRouter>
        <AdminMenuPage />
      </MemoryRouter>,
    )

    await waitFor(() => {
      expect(screen.getByText('Dish A')).toBeInTheDocument()
      expect(screen.getByText('Dish B')).toBeInTheDocument()
      expect(screen.getByText('Dish C')).toBeInTheDocument()
    })

    const rows = container.querySelectorAll('tbody tr')
    const secondRow = rows[1]
    await user.click(within(secondRow).getByRole('button', { name: 'Up' }))

    await user.click(screen.getByRole('button', { name: 'Save order' }))

    await waitFor(() => {
      expect(screen.getByText(/previous saved order was restored/i)).toBeInTheDocument()
    })

    const names = Array.from(container.querySelectorAll('tbody tr strong')).map((el) => el.textContent)
    expect(names).toEqual(['Dish A', 'Dish B', 'Dish C'])
  })
})
