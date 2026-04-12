import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import AdminGuard from '../../components/AdminGuard'
import { deleteMenuItem, fetchMenu, reorderMenuItems, updateMenuItem } from '../../lib/menu'
import { getFriendlyError } from '../../lib/errors'

function toGbp(pence) {
  return new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency: 'GBP',
  }).format((Number(pence) || 0) / 100)
}

export default function AdminMenuPage() {
  const [items, setItems] = useState([])
  const [savedItems, setSavedItems] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [savingOrder, setSavingOrder] = useState(false)
  const [dragIndex, setDragIndex] = useState(null)
  const [dragOverIndex, setDragOverIndex] = useState(null)

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
        const nextItems = normalizeOrder(Array.isArray(data.items) ? data.items : [])
        setItems(nextItems)
        setSavedItems(nextItems)
      } catch (err) {
        if (!mounted) {
          return
        }
        setError(getFriendlyError(err, 'Could not load menu items. Please refresh and try again.'))
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

  const hasChanges = useMemo(() => {
    if (items.length !== savedItems.length) {
      return true
    }

    return items.some((item, index) => item.id !== savedItems[index]?.id)
  }, [items, savedItems])

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
      return normalizeOrder(clone)
    })
    setMessage('')
  }

  function handleDragStart(index) {
    setDragIndex(index)
    setDragOverIndex(index)
    setMessage('')
  }

  function handleDragOver(event, index) {
    event.preventDefault()
    if (dragOverIndex !== index) {
      setDragOverIndex(index)
    }
  }

  function handleDrop(event, targetIndex) {
    event.preventDefault()

    if (dragIndex === null || dragIndex === targetIndex) {
      setDragIndex(null)
      setDragOverIndex(null)
      return
    }

    setItems((prev) => {
      const clone = [...prev]
      const [moved] = clone.splice(dragIndex, 1)
      clone.splice(targetIndex, 0, moved)
      return normalizeOrder(clone)
    })

    setDragIndex(null)
    setDragOverIndex(null)
  }

  function handleDragEnd() {
    setDragIndex(null)
    setDragOverIndex(null)
  }

  async function saveOrder() {
    setSavingOrder(true)
    setError('')
    setMessage('')

    try {
      const ids = items.map((item) => item.id)
      const data = await reorderMenuItems(ids)
      const nextItems = normalizeOrder(Array.isArray(data.items) ? data.items : [])
      setItems(nextItems)
      setSavedItems(nextItems)
      setMessage('Menu order saved successfully.')
    } catch (err) {
      setItems(savedItems)
      setError(getFriendlyError(err, 'Could not save menu order') + '. The previous saved order was restored.')
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
      setItems((prev) => normalizeOrder(prev.filter((entry) => entry.id !== item.id)))
      setSavedItems((prev) => normalizeOrder(prev.filter((entry) => entry.id !== item.id)))
      setMessage('Menu item deleted successfully.')
    } catch (err) {
      setError(getFriendlyError(err, 'Could not delete the menu item. Please try again.'))
    }
  }

  async function toggleAvailability(item) {
    setError('')
    setMessage('')

    try {
      const data = await updateMenuItem(item.id, { is_available: !item.is_available })
      const updated = data.item
      setItems((prev) => prev.map((entry) => (entry.id === updated.id ? updated : entry)))
      setSavedItems((prev) => prev.map((entry) => (entry.id === updated.id ? updated : entry)))
    } catch (err) {
      setError(getFriendlyError(err, 'Could not update availability. Please try again.'))
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

        <p className="admin-muted">Tip: drag a row to a new position, then click Save order.</p>

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
                  <tr
                    key={item.id}
                    draggable
                    onDragStart={() => handleDragStart(index)}
                    onDragOver={(event) => handleDragOver(event, index)}
                    onDrop={(event) => handleDrop(event, index)}
                    onDragEnd={handleDragEnd}
                    className={[
                      'admin-row-draggable',
                      dragIndex === index ? 'admin-row-dragging' : '',
                      dragOverIndex === index && dragIndex !== index ? 'admin-row-drop-target' : '',
                    ].join(' ')}
                  >
                    <td>
                      <div className="admin-order-controls">
                        <span className="admin-drag-handle" title="Drag to reorder">::</span>
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

function normalizeOrder(list) {
  return list.map((item, index) => ({
    ...item,
    display_order: index,
  }))
}
