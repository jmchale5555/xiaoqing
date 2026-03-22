import { useEffect, useMemo, useState } from 'react'
import { fetchMenu } from '../lib/menu'

function formatGbp(pence) {
  const value = Number.isFinite(pence) ? pence : 0
  return new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency: 'GBP',
  }).format(value / 100)
}

function groupByCategory(items) {
  return items.reduce((acc, item) => {
    const category = item.category || 'Chef Specials'
    if (!acc[category]) {
      acc[category] = []
    }
    acc[category].push(item)
    return acc
  }, {})
}

export default function MenuPage() {
  const [items, setItems] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    let mounted = true

    async function loadMenu() {
      setLoading(true)
      setError('')

      try {
        const data = await fetchMenu({ isAvailable: true })
        if (!mounted) {
          return
        }
        setItems(Array.isArray(data.items) ? data.items : [])
      } catch (err) {
        if (!mounted) {
          return
        }
        setError(err?.message || 'Unable to load menu right now.')
      } finally {
        if (mounted) {
          setLoading(false)
        }
      }
    }

    loadMenu()

    return () => {
      mounted = false
    }
  }, [])

  const grouped = useMemo(() => groupByCategory(items), [items])
  const categories = Object.keys(grouped)

  return (
    <section className="menu-shell">
      <div className="menu-hero">
        <p className="menu-kicker">Today&apos;s Spread</p>
        <h1 className="menu-title">House Menu</h1>
        <p className="menu-subtitle">
          Bilingual dishes inspired by Northeast Chinese comfort cooking.
        </p>
      </div>

      {loading && <p className="menu-state">Loading fresh dishes...</p>}

      {!loading && error && <p className="menu-state menu-state-error">{error}</p>}

      {!loading && !error && categories.length === 0 && (
        <p className="menu-state">No dishes are available yet. Check back soon.</p>
      )}

      {!loading && !error && categories.length > 0 && (
        <div className="menu-categories">
          {categories.map((category) => (
            <article className="menu-category" key={category}>
              <header className="menu-category-head">
                <h2>{category}</h2>
                <span>{grouped[category].length} dishes</span>
              </header>

              <div className="menu-grid">
                {grouped[category].map((item) => (
                  <article className="menu-card" key={item.id}>
                    <div className="menu-card-image-wrap">
                      {item.image_path ? (
                        <img src={item.image_path} alt={item.name} className="menu-card-image" loading="lazy" />
                      ) : (
                        <div className="menu-card-image menu-card-image-fallback">No Image</div>
                      )}
                      <p className="menu-price">{formatGbp(item.price_pence)}</p>
                    </div>
                    <div className="menu-card-body">
                      <h3>{item.name}</h3>
                      {item.description ? <p>{item.description}</p> : null}
                    </div>
                  </article>
                ))}
              </div>
            </article>
          ))}
        </div>
      )}
    </section>
  )
}
