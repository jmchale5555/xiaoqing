import { useEffect, useMemo, useRef, useState } from 'react'
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
  const [isNarrowScreen, setIsNarrowScreen] = useState(false)
  const tableWrapRef = useRef(null)
  const pointerDragRef = useRef({
    active: false,
    pointerId: null,
    startX: 0,
    startScrollLeft: 0,
  })
  const mobileReorderRef = useRef({
    active: false,
    pointerId: null,
    sourceIndex: null,
  })

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

  useEffect(() => {
    if (typeof window.matchMedia !== 'function') {
      return undefined
    }

    const mediaQuery = window.matchMedia('(max-width: 640px)')
    const update = () => setIsNarrowScreen(mediaQuery.matches)
    update()

    if (typeof mediaQuery.addEventListener === 'function') {
      mediaQuery.addEventListener('change', update)
      return () => mediaQuery.removeEventListener('change', update)
    }

    mediaQuery.addListener(update)
    return () => mediaQuery.removeListener(update)
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

  function applyReorder(fromIndex, toIndex) {
    if (fromIndex === null || toIndex === null || fromIndex === toIndex) {
      return
    }

    setItems((prev) => {
      const clone = [...prev]
      const [moved] = clone.splice(fromIndex, 1)
      clone.splice(toIndex, 0, moved)
      return normalizeOrder(clone)
    })
  }

  function handleMobileHandlePointerDown(event, index) {
    if (!isNarrowScreen || event.pointerType === 'mouse') {
      return
    }

    mobileReorderRef.current = {
      active: true,
      pointerId: event.pointerId,
      sourceIndex: index,
    }

    setDragIndex(index)
    setDragOverIndex(index)
    setMessage('')
    event.currentTarget.setPointerCapture(event.pointerId)
  }

  function handleMobileHandlePointerMove(event) {
    const reorder = mobileReorderRef.current
    if (!reorder.active || reorder.pointerId !== event.pointerId) {
      return
    }

    event.preventDefault()
    const row = document.elementFromPoint(event.clientX, event.clientY)?.closest('tr[data-menu-index]')
    if (!row) {
      return
    }

    const nextIndex = Number(row.dataset.menuIndex)
    if (!Number.isInteger(nextIndex)) {
      return
    }

    setDragOverIndex(nextIndex)
  }

  function finishMobileHandlePointer(event) {
    const reorder = mobileReorderRef.current
    if (!reorder.active || reorder.pointerId !== event.pointerId) {
      return
    }

    const targetIndex = dragOverIndex
    applyReorder(reorder.sourceIndex, targetIndex)

    mobileReorderRef.current = {
      active: false,
      pointerId: null,
      sourceIndex: null,
    }

    if (event.currentTarget.hasPointerCapture(event.pointerId)) {
      event.currentTarget.releasePointerCapture(event.pointerId)
    }

    setDragIndex(null)
    setDragOverIndex(null)
  }

  function handleTablePointerDown(event) {
    if (!isNarrowScreen) {
      return
    }

    if (event.pointerType === 'mouse' && event.button !== 0) {
      return
    }

    if (event.target.closest('button, a, input, select, textarea, .admin-drag-handle, .menu-order-controls')) {
      return
    }

    const wrap = tableWrapRef.current
    if (!wrap) {
      return
    }

    pointerDragRef.current = {
      active: true,
      pointerId: event.pointerId,
      startX: event.clientX,
      startScrollLeft: wrap.scrollLeft,
    }

    wrap.setPointerCapture(event.pointerId)
    wrap.dataset.dragging = 'true'
  }

  function handleTablePointerMove(event) {
    const wrap = tableWrapRef.current
    const drag = pointerDragRef.current
    if (!wrap || !drag.active || drag.pointerId !== event.pointerId) {
      return
    }

    const deltaX = event.clientX - drag.startX
    wrap.scrollLeft = drag.startScrollLeft - deltaX
  }

  function endTablePointerDrag(event) {
    const wrap = tableWrapRef.current
    const drag = pointerDragRef.current
    if (!wrap || !drag.active || drag.pointerId !== event.pointerId) {
      return
    }

    pointerDragRef.current = {
      active: false,
      pointerId: null,
      startX: 0,
      startScrollLeft: 0,
    }

    if (wrap.hasPointerCapture(event.pointerId)) {
      wrap.releasePointerCapture(event.pointerId)
    }

    delete wrap.dataset.dragging
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
        <p className="admin-muted menu-mobile-reorder-hint">Tip (mobile): drag the :: handle to reorder rows.</p>

        {message ? <p className="admin-success">{message}</p> : null}
        {error ? <p className="admin-error">{error}</p> : null}

        {loading ? <p className="menu-state">Loading menu items...</p> : null}

        {!loading && items.length === 0 ? <p className="menu-state">No dishes yet. Add your first dish.</p> : null}

        {!loading && items.length > 0 ? (
          <div
            className="admin-table-wrap menu-table-wrap"
            ref={tableWrapRef}
            onPointerDown={handleTablePointerDown}
            onPointerMove={handleTablePointerMove}
            onPointerUp={endTablePointerDrag}
            onPointerCancel={endTablePointerDrag}
          >
            <table className="admin-table menu-table">
              <thead>
                <tr>
                  <th className="menu-col-order" aria-label="Order">
                    <span className="menu-order-label">Order</span>
                  </th>
                  <th>Dish</th>
                  <th className="menu-col-category">Category</th>
                  <th className="menu-col-price">Price</th>
                  <th className="menu-col-status">Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {items.map((item, index) => (
                  <tr
                    key={item.id}
                    draggable={!isNarrowScreen}
                    onDragStart={() => handleDragStart(index)}
                    onDragOver={(event) => handleDragOver(event, index)}
                    onDrop={(event) => handleDrop(event, index)}
                    onDragEnd={handleDragEnd}
                    data-menu-index={index}
                    className={[
                      'admin-row-draggable',
                      dragIndex === index ? 'admin-row-dragging' : '',
                      dragOverIndex === index && dragIndex !== index ? 'admin-row-drop-target' : '',
                    ].join(' ')}
                  >
                    <td>
                      <div className="admin-order-controls menu-order-controls">
                        <span
                          className="admin-drag-handle"
                          title="Drag to reorder"
                          onPointerDown={(event) => handleMobileHandlePointerDown(event, index)}
                          onPointerMove={handleMobileHandlePointerMove}
                          onPointerUp={finishMobileHandlePointer}
                          onPointerCancel={finishMobileHandlePointer}
                        >
                          ::
                        </span>
                        <button
                          onClick={() => move(index, -1)}
                          disabled={index === 0}
                          className="admin-mini-btn menu-move-btn"
                          aria-label="Move up"
                        >
                          Up
                        </button>
                        <button
                          onClick={() => move(index, 1)}
                          disabled={index === items.length - 1}
                          className="admin-mini-btn menu-move-btn"
                          aria-label="Move down"
                        >
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
                    <td className="menu-col-category">{item.category || '-'}</td>
                    <td className="menu-col-price">{toGbp(item.price_pence)}</td>
                    <td className="menu-col-status">
                      <span className="admin-chip">{item.is_available ? 'Visible' : 'Hidden'}</span>
                    </td>
                    <td className="menu-actions-cell">
                      <div className="admin-order-controls menu-actions-stack">
                        <button className="admin-mini-btn menu-action-btn" onClick={() => toggleAvailability(item)}>
                          <EyeIcon />
                          <span>{item.is_available ? 'Visible' : 'Hidden'}</span>
                        </button>
                        <Link to={`/admin/menu/${item.id}/edit`} className="admin-mini-btn menu-action-btn">
                          <EditIcon />
                          <span>Edit</span>
                        </Link>
                        <button className="admin-mini-btn danger menu-action-btn" onClick={() => handleDelete(item)}>
                          <TrashIcon />
                          <span>Delete</span>
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

function EyeIcon() {
  return (
    <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false">
      <path
        d="M2 12s3.8-6 10-6 10 6 10 6-3.8 6-10 6S2 12 2 12Z"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.8"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <circle cx="12" cy="12" r="2.8" fill="none" stroke="currentColor" strokeWidth="1.8" />
    </svg>
  )
}

function EditIcon() {
  return (
    <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false">
      <path
        d="M3 21h4l11-11a2.1 2.1 0 0 0-3-3L4 18v3Z"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.8"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  )
}

function TrashIcon() {
  return (
    <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false">
      <path
        d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.8"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  )
}
