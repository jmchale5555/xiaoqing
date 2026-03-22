export async function api(path, options = {}) {
  const response = await fetch(path, {
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      ...(options.headers || {}),
    },
    ...options,
  })

  const contentType = response.headers.get('content-type') || ''
  const isJson = contentType.includes('application/json')
  const data = isJson ? await response.json() : { message: await response.text() }

  if (!response.ok) {
    const message = data.message || 'Request failed'
    const error = new Error(message)
    error.status = response.status
    error.payload = data
    throw error
  }

  return data
}
