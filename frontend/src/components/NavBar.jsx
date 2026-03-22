import { Link, NavLink } from 'react-router-dom'

const active = 'text-sky-700 underline underline-offset-4'

export default function NavBar() {
  return (
    <header className="border-b border-slate-200 bg-white/80 backdrop-blur">
      <div className="mx-auto flex w-full max-w-4xl items-center justify-between px-4 py-4">
        <Link to="/" className="text-lg font-bold text-slate-900">
          PHP SPA Boilerplate
        </Link>
        <nav className="flex items-center gap-4 text-sm font-medium text-slate-700">
          <NavLink to="/" end className={({ isActive }) => (isActive ? active : '')}>
            Home
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
