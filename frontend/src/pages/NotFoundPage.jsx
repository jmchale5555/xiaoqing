import { Link } from 'react-router-dom'

export default function NotFoundPage() {
  return (
    <section className="rounded-xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
      <h1 className="mb-2 text-2xl font-bold text-slate-900">Page not found</h1>
      <p className="mb-4 text-slate-600">The route you requested does not exist in the SPA.</p>
      <Link className="font-semibold text-sky-700 hover:underline" to="/">
        Return home
      </Link>
    </section>
  )
}
