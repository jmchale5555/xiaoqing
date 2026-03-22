import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import AdminGuard from '../../components/AdminGuard'
import { deleteMenuItem, fetchMenu, reorderMenuItems, updateMenuItem } from '../../lib/menu'

function toGbp(pence) {
  return new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency: 'GBP',
  }).format((Number(pence) || 0) / 100)
}

export default function AdminMenuPage() {
  const [items, setItems] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [savingOrder, setSavingOrder] = useState(false)

  useEffect(() => {
    let mounted = true

    async function load() {
      setLoading(true)
      setError('')

      try {
        const data = await fetchMenu()
        if (!mounted) {
          return
        }
        setItems(Array.isArray(data.items) ? data.items : [])
      } catch (err) {
        if (!mounted) {
          return
        }
        setError(err.message || 'Unable to load menu items')
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
  }, [])

  const hasChanges = useMemo(
    () => items.some((item, index) => Number(item.display_order) !== index),
    [items],
  )

  function move(index, direction) {
    const next = index + direction
    if (next < 0 || next >= items.length) {
      return
    }

    setItems((prev) => {
      const clone = [...prev]
      const temp = clone[index]
      clone[index] = clone[next]
      clone[next] = temp
      return clone.map((item, idx) => ({ ...item, display_order: idx }))
    })
    setMessage('')
  }

  async function saveOrder() {
    setSavingOrder(true)
    setError('')
    setMessage('')

    try {
      const ids = items.map((item) => item.id)
      const data = await reorderMenuItems(ids)
      const nextItems = Array.isArray(data.items) ? data.items : []
      setItems(nextItems)
      setMessage('Menu order saved.')
    } catch (err) {
      setError(err.message || 'Unable to save order')
    } finally {
      setSavingOrder(false)
    }
  }

  async function handleDelete(item) {
    const confirmed = window.confirm(`Delete menu item: ${item.name}?`)
    if (!confirmed) {
      return
    }

    setError('')
    setMessage('')

    try {
      await deleteMenuItem(item.id)
      setItems((prev) => prev.filter((entry) => entry.id !== item.id).map((entry, idx) => ({ ...entry, display_order: idx })))
      setMessage('Menu item deleted.')
    } catch (err) {
      setError(err.message || 'Unable to delete menu item')
    }
  }

  async function toggleAvailability(item) {
    setError('')
    setMessage('')

    try {
      const data = await updateMenuItem(item.id, { is_available: !item.is_available })
      const updated = data.item
      setItems((prev) => prev.map((entry) => (entry.id === updated.id ? updated : entry)))
    } catch (err) {
      setError(err.message || 'Unable to update availability')
    }
  }

  return (
    <AdminGuard>
      <section className="admin-shell">
        <header className="admin-head">
          <div>
            <p className="menu-kicker">Staff</p>
            <h1 className="admin-title">Menu Manager</h1>
            <p className="admin-muted">Create, edit, reorder, and publish dishes.</p>
          </div>
          <div className="admin-actions">
            <Link to="/admin/menu/new" className="admin-cta">
              New dish
            </Link>
            <button className="admin-btn-secondary" onClick={saveOrder} disabled={!hasChanges || savingOrder}>
              {savingOrder ? 'Saving...' : 'Save order'}
            </button>
          </div>
        </header>

        {message ? <p className="admin-success">{message}</p> : null}
        {error ? <p className="admin-error">{error}</p> : null}

        {loading ? <p className="menu-state">Loading menu items...</p> : null}

        {!loading && items.length === 0 ? <p className="menu-state">No dishes yet. Add your first dish.</p> : null}

        {!loading && items.length > 0 ? (
          <div className="admin-table-wrap">
            <table className="admin-table">
              <thead>
                <tr>
                  <th>Order</th>
                  <th>Dish</th>
                  <th>Category</th>
                  <th>Price</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {items.map((item, index) => (
                  <tr key={item.id}>
                    <td>
                      <div className="admin-order-controls">
                        <button onClick={() => move(index, -1)} disabled={index === 0} className="admin-mini-btn">
                          Up
                        </button>
                        <button onClick={() => move(index, 1)} disabled={index === items.length - 1} className="admin-mini-btn">
                          Down
                        </button>
                      </div>
                    </td>
                    <td>
                      <div className="admin-dish-cell">
                        {item.image_path ? <img src={item.image_path} alt={item.name} /> : <span className="admin-chip">No image</span>}
                        <div>
                          <strong>{item.name}</strong>
                          <p>{item.description || 'No description'}</p>
                        </div>
                      </div>
                    </td>
                    <td>{item.category || '-'}</td>
                    <td>{toGbp(item.price_pence)}</td>
                    <td>
                      <button className="admin-mini-btn" onClick={() => toggleAvailability(item)}>
                        {item.is_available ? 'Visible' : 'Hidden'}
                      </button>
                    </td>
                    <td>
                      <div className="admin-order-controls">
                        <Link to={`/admin/menu/${item.id}/edit`} className="admin-mini-btn">
                          Edit
                        </Link>
                        <button className="admin-mini-btn danger" onClick={() => handleDelete(item)}>
                          Delete
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : null}
      </section>
    </AdminGuard>
  )
}
