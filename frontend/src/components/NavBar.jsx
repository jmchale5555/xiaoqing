import { useEffect, useRef, useState } from 'react'
import { Link, NavLink, useLocation, useNavigate } from 'react-router-dom'
import { logout, me } from '../lib/auth'

const active = 'nav-active'

export default function NavBar() {
  const navigate = useNavigate()
  const location = useLocation()
  const [user, setUser] = useState(null)
  const [menuOpen, setMenuOpen] = useState(false)
  const menuRef = useRef(null)

  const isAuthenticated = Boolean(user)
  const role = String(user?.role || '')
  const isStaff = role === 'staff' || role === 'manager'

  useEffect(() => {
    let mounted = true

    me()
      .then((data) => {
        if (!mounted) {
          return
        }

        setUser(data?.user || null)
      })
      .catch(() => {
        if (!mounted) {
          return
        }

        setUser(null)
      })

    return () => {
      mounted = false
    }
  }, [location.pathname])

  useEffect(() => {
    setMenuOpen(false)
  }, [location.pathname])

  useEffect(() => {
    function onDocumentPointerDown(event) {
      if (!menuRef.current) {
        return
      }

      if (!menuRef.current.contains(event.target)) {
        setMenuOpen(false)
      }
    }

    document.addEventListener('mousedown', onDocumentPointerDown)
    document.addEventListener('touchstart', onDocumentPointerDown)

    return () => {
      document.removeEventListener('mousedown', onDocumentPointerDown)
      document.removeEventListener('touchstart', onDocumentPointerDown)
    }
  }, [])

  async function onLogout() {
    try {
      await logout()
    } catch {
      // no-op: we still clear local auth state and redirect
    }

    setUser(null)
    navigate('/')
  }

  return (
    <header className="site-header">
      <div className="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-4">
        <Link to="/" className="brand-mark">
          XiaoQing Kitchen
        </Link>
        <nav className="site-nav">
          <NavLink to="/" end className={({ isActive }) => (isActive ? active : '')}>
            Home
          </NavLink>
          <NavLink to="/menu" className={({ isActive }) => (isActive ? active : '')}>
            Menu
          </NavLink>
          {isStaff ? (
            <>
              <NavLink to="/admin/menu" className={({ isActive }) => (isActive ? active : '')}>
                Admin Menu
              </NavLink>
              <NavLink to="/admin/bookings" className={({ isActive }) => (isActive ? active : '')}>
                Bookings
              </NavLink>
            </>
          ) : null}
          <div className="auth-menu-wrap" ref={menuRef}>
            <button
              type="button"
              className="auth-menu-trigger"
              aria-label="Open account menu"
              aria-expanded={menuOpen}
              onClick={() => setMenuOpen((prev) => !prev)}
            >
              <span className="auth-menu-icon" aria-hidden="true">
                <span />
                <span />
                <span />
              </span>
            </button>

            {menuOpen ? (
              <div className="auth-menu-dropdown">
                {isAuthenticated ? (
                  <>
                    <NavLink to="/change-password" className={({ isActive }) => (isActive ? `auth-menu-link ${active}` : 'auth-menu-link')}>
                      Change password
                    </NavLink>
                    <button type="button" className="auth-menu-link" onClick={onLogout}>
                      Logout
                    </button>
                  </>
                ) : (
                  <>
                    <NavLink to="/login" className={({ isActive }) => (isActive ? `auth-menu-link ${active}` : 'auth-menu-link')}>
                      Login
                    </NavLink>
                    <NavLink to="/signup" className={({ isActive }) => (isActive ? `auth-menu-link ${active}` : 'auth-menu-link')}>
                      Sign up
                    </NavLink>
                  </>
                )}
              </div>
            ) : null}
          </div>
        </nav>
      </div>
    </header>
  )
}
