import { useEffect, useState } from 'react'
import { me } from '../lib/auth'

export default function HomePage() {
  const [user, setUser] = useState(null)

  useEffect(() => {
    let active = true

    me()
      .then((data) => {
        if (active) {
          setUser(data.user)
        }
      })
      .catch(() => {
        if (active) {
          setUser(null)
        }
      })

    return () => {
      active = false
    }
  }, [])

  return (
    <section className="space-y-4 rounded-xl bg-white p-8 shadow-sm ring-1 ring-slate-200">
      <h1 className="text-3xl font-bold tracking-tight text-slate-900">Welcome</h1>
      <p className="text-slate-600">
        This is the new React SPA shell running against the PHP API.
      </p>
      <p className="text-slate-700">
        Current user: <span className="font-semibold">{user?.name ?? 'Guest'}</span>
      </p>
    </section>
  )
}
