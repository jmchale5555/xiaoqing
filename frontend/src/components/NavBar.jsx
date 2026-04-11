import { Link, NavLink } from 'react-router-dom'

const active = 'nav-active'

export default function NavBar() {
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
          <NavLink to="/admin/menu" className={({ isActive }) => (isActive ? active : '')}>
            Admin Menu
          </NavLink>
          <NavLink to="/admin/bookings" className={({ isActive }) => (isActive ? active : '')}>
            Bookings
          </NavLink>
          <NavLink to="/login" className={({ isActive }) => (isActive ? active : '')}>
            Login
          </NavLink>
          <NavLink to="/signup" className={({ isActive }) => (isActive ? active : '')}>
            Sign up
          </NavLink>
        </nav>
      </div>
    </header>
  )
}
