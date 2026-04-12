function flattenErrors(errors) {
  if (!errors || typeof errors !== 'object') {
    return []
  }

  return Object.values(errors)
    .flatMap((value) => {
      if (Array.isArray(value)) {
        return value
      }

      return [value]
    })
    .map((value) => String(value || '').trim())
    .filter(Boolean)
}

export function getFriendlyError(error, fallback = 'Request failed') {
  const fieldErrors = flattenErrors(error?.payload?.errors)

  if (fieldErrors.length > 0) {
    return fieldErrors[0]
  }

  const payloadMessage = String(error?.payload?.message || '').trim()
  if (payloadMessage) {
    return payloadMessage
  }

  const message = String(error?.message || '').trim()
  if (message) {
    return message
  }

  return fallback
}
