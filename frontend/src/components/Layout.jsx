import NavBar from './NavBar'

export default function Layout({ children }) {
  return (
    <div className="site-shell">
      <NavBar />
      <main className="mx-auto w-full max-w-6xl px-4 py-10">{children}</main>
    </div>
  )
}
