import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { changePassword, me } from '../lib/auth'
import { getFriendlyError } from '../lib/errors'

export default function ChangePasswordPage() {
  const [loading, setLoading] = useState(true)
  const [authenticated, setAuthenticated] = useState(false)
  const [form, setForm] = useState({
    current_password: '',
    new_password: '',
    confirm_password: '',
  })
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    let mounted = true

    me()
      .then((data) => {
        if (!mounted) {
          return
        }
        setAuthenticated(Boolean(data?.user))
      })
      .catch(() => {
        if (!mounted) {
          return
        }
        setAuthenticated(false)
      })
      .finally(() => {
        if (mounted) {
          setLoading(false)
        }
      })

    return () => {
      mounted = false
    }
  }, [])

  useEffect(() => {
    if (!message) {
      return
    }
    const timer = window.setTimeout(() => setMessage(''), 4000)
    return () => window.clearTimeout(timer)
  }, [message])

  useEffect(() => {
    if (!error) {
      return
    }
    const timer = window.setTimeout(() => setError(''), 4000)
    return () => window.clearTimeout(timer)
  }, [error])

  async function onSubmit(event) {
    event.preventDefault()
    setSaving(true)
    setError('')
    setMessage('')

    try {
      await changePassword(form)
      setMessage('Password changed successfully.')
      setForm({ current_password: '', new_password: '', confirm_password: '' })
    } catch (err) {
      setError(getFriendlyError(err, 'Could not change password. Please review and try again.'))
    } finally {
      setSaving(false)
    }
  }

  if (loading) {
    return <p className="menu-state">Checking account...</p>
  }

  if (!authenticated) {
    return (
      <section className="admin-card">
        <h1 className="admin-title">Sign in required</h1>
        <p className="admin-muted">Please log in to change your password.</p>
        <Link to="/login" className="admin-cta">
          Go to login
        </Link>
      </section>
    )
  }

  return (
    <section className="admin-shell">
      <header className="admin-head">
        <div>
          <p className="menu-kicker">Account</p>
          <h1 className="admin-title">Change Password</h1>
          <p className="admin-muted">Update your password for secure staff access.</p>
        </div>
      </header>

      <section className="admin-card">
        {message ? <p className="admin-success">{message}</p> : null}
        {error ? <p className="admin-error">{error}</p> : null}

        <form className="admin-form" onSubmit={onSubmit}>
          <label>
            <span>Current password</span>
            <input
              type="password"
              value={form.current_password}
              onChange={(event) => setForm((prev) => ({ ...prev, current_password: event.target.value }))}
              required
            />
          </label>

          <label>
            <span>New password</span>
            <input
              type="password"
              value={form.new_password}
              onChange={(event) => setForm((prev) => ({ ...prev, new_password: event.target.value }))}
              required
              minLength={8}
            />
          </label>

          <label>
            <span>Confirm new password</span>
            <input
              type="password"
              value={form.confirm_password}
              onChange={(event) => setForm((prev) => ({ ...prev, confirm_password: event.target.value }))}
              required
              minLength={8}
            />
          </label>

          <div className="admin-actions">
            <button className="admin-cta" type="submit" disabled={saving}>
              {saving ? 'Saving...' : 'Change password'}
            </button>
            <Link to="/" className="admin-btn-secondary">
              Back to home
            </Link>
          </div>
        </form>
      </section>
    </section>
  )
}
