import NavBar from './NavBar'

export default function Layout({ children }) {
  return (
    <div className="min-h-screen bg-gradient-to-b from-slate-50 to-slate-200 text-slate-900">
      <NavBar />
      <main className="mx-auto w-full max-w-4xl px-4 py-10">{children}</main>
    </div>
  )
}
