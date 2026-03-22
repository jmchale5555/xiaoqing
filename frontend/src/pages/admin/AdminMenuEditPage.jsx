import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import AdminGuard from '../../components/AdminGuard'
import MenuItemForm from '../../components/MenuItemForm'
import { fetchMenuItem, updateMenuItem } from '../../lib/menu'

export default function AdminMenuEditPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [item, setItem] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    let mounted = true

    async function load() {
      setLoading(true)
      setError('')
      try {
        const data = await fetchMenuItem(id)
        if (!mounted) {
          return
        }
        setItem(data.item || null)
      } catch (err) {
        if (!mounted) {
          return
        }
        setError(err.message || 'Unable to load menu item')
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

  async function handleUpdate(payload) {
    await updateMenuItem(id, payload)
    navigate('/admin/menu')
  }

  return (
    <AdminGuard>
      <section className="admin-shell">
        <header className="admin-head">
          <div>
            <p className="menu-kicker">Staff</p>
            <h1 className="admin-title">Edit Dish</h1>
            <p className="admin-muted">Update details, price, image, and availability.</p>
          </div>
          <Link className="admin-btn-secondary" to="/admin/menu">
            Back to list
          </Link>
        </header>

        {loading ? <p className="menu-state">Loading dish...</p> : null}
        {error ? <p className="admin-error">{error}</p> : null}

        {!loading && !error && item ? (
          <section className="admin-card">
            <MenuItemForm initialItem={item} onSubmit={handleUpdate} submitLabel="Save changes" />
          </section>
        ) : null}
      </section>
    </AdminGuard>
  )
}
