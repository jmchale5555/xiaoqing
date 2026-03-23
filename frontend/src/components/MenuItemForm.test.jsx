import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import MenuItemForm from './MenuItemForm'

vi.mock('../lib/menu', () => ({
  uploadMenuImage: vi.fn(),
}))

describe('MenuItemForm', () => {
  test('blocks negative GBP price before submit', async () => {
    const user = userEvent.setup()
    const onSubmit = vi.fn().mockResolvedValue(undefined)

    render(<MenuItemForm onSubmit={onSubmit} />)

    await user.type(screen.getByLabelText('Name'), 'Test Dish')
    const priceInput = screen.getByLabelText('Price (GBP)')
    await user.clear(priceInput)
    await user.type(priceInput, '-1')
    await user.click(screen.getByRole('button', { name: 'Save item' }))

    expect(onSubmit).not.toHaveBeenCalled()
    expect(screen.getByText('Price must be a non-negative number.')).toBeInTheDocument()
  })

  test('submits valid form and converts GBP to pence', async () => {
    const user = userEvent.setup()
    const onSubmit = vi.fn().mockResolvedValue(undefined)

    render(<MenuItemForm onSubmit={onSubmit} submitLabel="Create dish" />)

    await user.type(screen.getByLabelText('Name'), 'Braised Tofu')
    await user.type(screen.getByLabelText('Category'), '蔬菜类 (Vegetable Dishes)')
    await user.type(screen.getByLabelText('Description'), '豆腐焖白菜')

    const priceInput = screen.getByLabelText('Price (GBP)')
    await user.clear(priceInput)
    await user.type(priceInput, '12.50')

    await user.click(screen.getByRole('button', { name: 'Create dish' }))

    expect(onSubmit).toHaveBeenCalledWith(
      expect.objectContaining({
        name: 'Braised Tofu',
        category: '蔬菜类 (Vegetable Dishes)',
        description: '豆腐焖白菜',
        price_pence: 1250,
      }),
    )
  })
})
