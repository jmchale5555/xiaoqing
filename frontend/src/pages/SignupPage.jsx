import { useState } from 'react'
import { signup } from '../lib/auth'

export default function SignupPage() {
  const [form, setForm] = useState({ name: '', email: '', password: '', confirm: '' })
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')

  async function onSubmit(event) {
    event.preventDefault()
    setError('')
    setMessage('')

    try {
      const data = await signup(form)
      setMessage(`Account created for ${data.user?.name ?? 'user'}.`)
    } catch (err) {
      const fieldErrors = err.payload?.errors ? Object.values(err.payload.errors) : []
      setError(fieldErrors[0] || err.message || 'Signup failed')
    }
  }

  return (
    <section className="mx-auto w-full max-w-md rounded-xl bg-white p-8 shadow-sm ring-1 ring-slate-200">
      <h1 className="mb-6 text-2xl font-bold text-slate-900">Create account</h1>

      {message ? <p className="mb-4 rounded bg-emerald-100 px-3 py-2 text-emerald-800">{message}</p> : null}
      {error ? <p className="mb-4 rounded bg-rose-100 px-3 py-2 text-rose-800">{error}</p> : null}

      <form className="space-y-4" onSubmit={onSubmit}>
        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700" htmlFor="name">
            Name
          </label>
          <input
            id="name"
            className="w-full rounded border border-slate-300 px-3 py-2"
            value={form.name}
            onChange={(event) => setForm({ ...form, name: event.target.value })}
            required
          />
        </div>
        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700" htmlFor="email">
            Email
          </label>
          <input
            id="email"
            type="email"
            className="w-full rounded border border-slate-300 px-3 py-2"
            value={form.email}
            onChange={(event) => setForm({ ...form, email: event.target.value })}
            required
          />
        </div>
        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700" htmlFor="password">
            Password
          </label>
          <input
            id="password"
            type="password"
            className="w-full rounded border border-slate-300 px-3 py-2"
            value={form.password}
            onChange={(event) => setForm({ ...form, password: event.target.value })}
            required
          />
        </div>
        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700" htmlFor="confirm">
            Confirm password
          </label>
          <input
            id="confirm"
            type="password"
            className="w-full rounded border border-slate-300 px-3 py-2"
            value={form.confirm}
            onChange={(event) => setForm({ ...form, confirm: event.target.value })}
            required
          />
        </div>
        <button className="w-full rounded bg-sky-600 px-4 py-2 font-semibold text-white hover:bg-sky-700" type="submit">
          Sign up
        </button>
      </form>
    </section>
  )
}
