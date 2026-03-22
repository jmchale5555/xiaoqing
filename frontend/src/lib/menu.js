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

async function withCsrf(path, payload = {}, options = {}) {
  const token = await getCsrfToken()

  try {
    return await api(path, {
      method: options.method || 'POST',
      headers: {
        'X-CSRF-Token': token,
        ...(options.headers || {}),
      },
      body: JSON.stringify(payload),
    })
  } catch (error) {
    if (error.status === 419) {
      const refreshed = await getCsrfToken(true)
      return api(path, {
        method: options.method || 'POST',
        headers: {
          'X-CSRF-Token': refreshed,
          ...(options.headers || {}),
        },
        body: JSON.stringify(payload),
      })
    }

    throw error
  }
}

export async function fetchMenu(params = {}) {
  const search = new URLSearchParams()

  if (params.category) {
    search.set('category', params.category)
  }

  if (typeof params.isAvailable === 'boolean') {
    search.set('is_available', params.isAvailable ? '1' : '0')
  }

  const query = search.toString()
  const path = query ? `/api/menu?${query}` : '/api/menu'

  return api(path, { method: 'GET' })
}

export function fetchMenuItem(id) {
  return api(`/api/menu/show/${id}`, { method: 'GET' })
}

export function createMenuItem(payload) {
  return withCsrf('/api/menu/create', payload)
}

export function updateMenuItem(id, payload) {
  return withCsrf(`/api/menu/update/${id}`, payload)
}

export function deleteMenuItem(id) {
  return withCsrf(`/api/menu/delete/${id}`, {})
}

export function reorderMenuItems(ids) {
  return withCsrf('/api/menu/reorder', { ids })
}

export async function uploadMenuImage(file) {
  const token = await getCsrfToken()
  const form = new FormData()
  form.append('image', file)

  async function submit(csrf) {
    const response = await fetch('/api/uploads/menu_image', {
      method: 'POST',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        'X-CSRF-Token': csrf,
      },
      body: form,
    })

    const contentType = response.headers.get('content-type') || ''
    const isJson = contentType.includes('application/json')
    const data = isJson ? await response.json() : { message: await response.text() }

    if (!response.ok) {
      const error = new Error(data.message || 'Upload failed')
      error.status = response.status
      error.payload = data
      throw error
    }

    return data
  }

  try {
    return await submit(token)
  } catch (error) {
    if (error.status === 419) {
      const refreshed = await getCsrfToken(true)
      return submit(refreshed)
    }
    throw error
  }
}
