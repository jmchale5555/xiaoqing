import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { me } from '../lib/auth'

export default function AdminGuard({ children }) {
  const [status, setStatus] = useState('loading')

  useEffect(() => {
    let mounted = true

    me()
      .then((data) => {
        if (!mounted) {
          return
        }
        setStatus(data?.user ? 'ok' : 'unauthenticated')
      })
      .catch(() => {
        if (!mounted) {
          return
        }
        setStatus('unauthenticated')
      })

    return () => {
      mounted = false
    }
  }, [])

  if (status === 'loading') {
    return <p className="menu-state">Checking staff access...</p>
  }

  if (status === 'unauthenticated') {
    return (
      <section className="admin-card">
        <h1 className="admin-title">Staff login required</h1>
        <p className="admin-muted">Please sign in to manage menu items.</p>
        <Link to="/login" className="admin-cta">
          Go to login
        </Link>
      </section>
    )
  }

  return children
}
