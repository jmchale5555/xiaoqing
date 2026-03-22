import { Link, useNavigate } from 'react-router-dom'
import AdminGuard from '../../components/AdminGuard'
import MenuItemForm from '../../components/MenuItemForm'
import { createMenuItem } from '../../lib/menu'

export default function AdminMenuNewPage() {
  const navigate = useNavigate()

  async function handleCreate(payload) {
    await createMenuItem(payload)
    navigate('/admin/menu')
  }

  return (
    <AdminGuard>
      <section className="admin-shell">
        <header className="admin-head">
          <div>
            <p className="menu-kicker">Staff</p>
            <h1 className="admin-title">Add New Dish</h1>
            <p className="admin-muted">Create a new menu item for the public menu.</p>
          </div>
          <Link className="admin-btn-secondary" to="/admin/menu">
            Back to list
          </Link>
        </header>

        <section className="admin-card">
          <MenuItemForm onSubmit={handleCreate} submitLabel="Create dish" />
        </section>
      </section>
    </AdminGuard>
  )
}
