import { api } from './api'

let csrfToken = null

async function getCsrfToken(force = false) {
  if (!force && csrfToken) {
    return csrfToken
  }

  const data = await api('/api/auth/csrf')
  csrfToken = data.csrfToken
  return csrfToken
}

async function withCsrf(path, payload = {}) {
  const token = await getCsrfToken()

  try {
    return await api(path, {
      method: 'POST',
      headers: {
        'X-CSRF-Token': token,
      },
      body: JSON.stringify(payload),
    })
  } catch (error) {
    if (error.status === 419) {
      const refreshed = await getCsrfToken(true)
      return api(path, {
        method: 'POST',
        headers: {
          'X-CSRF-Token': refreshed,
        },
        body: JSON.stringify(payload),
      })
    }

    throw error
  }
}

export function me() {
  return api('/api/auth/me')
}

export function login(payload) {
  return withCsrf('/api/auth/login', payload)
}

export function signup(payload) {
  return withCsrf('/api/auth/signup', payload)
}

export function logout() {
  return withCsrf('/api/auth/logout')
}
