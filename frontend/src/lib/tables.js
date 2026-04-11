import { api } from './api'

export async function fetchTables(params = {}) {
  const search = new URLSearchParams()

  if (typeof params.isActive === 'boolean') {
    search.set('is_active', params.isActive ? '1' : '0')
  }

  const query = search.toString()
  const path = query ? `/api/tables?${query}` : '/api/tables'

  return api(path, { method: 'GET' })
}
