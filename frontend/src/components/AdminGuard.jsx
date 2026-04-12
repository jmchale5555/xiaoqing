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
        const role = String(data?.user?.role || '')
        const isStaff = role === 'staff' || role === 'manager'
        setStatus(data?.user ? (isStaff ? 'ok' : 'forbidden') : 'unauthenticated')
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

  if (status === 'forbidden') {
    return (
      <section className="admin-card">
        <h1 className="admin-title">Staff access required</h1>
        <p className="admin-muted">Your account is signed in but does not have staff permissions.</p>
      </section>
    )
  }

  return children
}
