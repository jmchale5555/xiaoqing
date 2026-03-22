import { useMemo, useState } from 'react'
import { uploadMenuImage } from '../lib/menu'

function penceToDisplay(pence) {
  if (!Number.isFinite(Number(pence))) {
    return '0.00'
  }
  return (Number(pence) / 100).toFixed(2)
}

function poundsToPence(value) {
  const normalized = String(value || '').trim()
  if (normalized === '') {
    return 0
  }

  const numeric = Number(normalized)
  if (!Number.isFinite(numeric) || numeric < 0) {
    return null
  }

  return Math.round(numeric * 100)
}

export default function MenuItemForm({ initialItem, onSubmit, submitLabel = 'Save item' }) {
  const [form, setForm] = useState({
    name: initialItem?.name || '',
    description: initialItem?.description || '',
    category: initialItem?.category || '',
    priceGbp: penceToDisplay(initialItem?.price_pence || 0),
    image_path: initialItem?.image_path || '',
    is_available: initialItem?.is_available ?? true,
  })
  const [imageFile, setImageFile] = useState(null)
  const [imageStatus, setImageStatus] = useState('')
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)
  const [uploading, setUploading] = useState(false)

  const previewImage = useMemo(() => form.image_path || '', [form.image_path])

  async function handleUploadImage() {
    if (!imageFile) {
      setError('Choose an image first.')
      return
    }

    setError('')
    setImageStatus('')
    setUploading(true)

    try {
      const data = await uploadMenuImage(imageFile)
      setForm((prev) => ({ ...prev, image_path: data.image_path || '' }))
      setImageStatus('Image uploaded.')
      setImageFile(null)
    } catch (err) {
      const fieldErrors = err.payload?.errors ? Object.values(err.payload.errors) : []
      setError(fieldErrors[0] || err.message || 'Image upload failed')
    } finally {
      setUploading(false)
    }
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setError('')
    setImageStatus('')

    const price_pence = poundsToPence(form.priceGbp)
    if (price_pence === null) {
      setError('Price must be a non-negative number.')
      return
    }

    setSaving(true)

    try {
      await onSubmit({
        name: form.name,
        description: form.description,
        category: form.category,
        price_pence,
        image_path: form.image_path,
        is_available: form.is_available,
      })
    } catch (err) {
      const fieldErrors = err.payload?.errors ? Object.values(err.payload.errors) : []
      setError(fieldErrors[0] || err.message || 'Unable to save menu item')
    } finally {
      setSaving(false)
    }
  }

  return (
    <form className="admin-form" onSubmit={handleSubmit}>
      <div className="admin-grid">
        <label>
          <span>Name</span>
          <input
            value={form.name}
            onChange={(event) => setForm((prev) => ({ ...prev, name: event.target.value }))}
            required
          />
        </label>

        <label>
          <span>Category</span>
          <input
            value={form.category}
            onChange={(event) => setForm((prev) => ({ ...prev, category: event.target.value }))}
            placeholder="肉类 (Meat Dishes)"
          />
        </label>

        <label>
          <span>Price (GBP)</span>
          <input
            value={form.priceGbp}
            onChange={(event) => setForm((prev) => ({ ...prev, priceGbp: event.target.value }))}
            inputMode="decimal"
            placeholder="12.50"
          />
        </label>

        <label className="admin-checkbox">
          <input
            type="checkbox"
            checked={form.is_available}
            onChange={(event) => setForm((prev) => ({ ...prev, is_available: event.target.checked }))}
          />
          <span>Available on public menu</span>
        </label>
      </div>

      <label>
        <span>Description</span>
        <textarea
          rows={3}
          value={form.description}
          onChange={(event) => setForm((prev) => ({ ...prev, description: event.target.value }))}
        />
      </label>

      <div className="admin-upload">
        <label>
          <span>Image upload (jpg/png/webp, max 2MB)</span>
          <input
            type="file"
            accept="image/jpeg,image/png,image/webp"
            onChange={(event) => setImageFile(event.target.files?.[0] || null)}
          />
        </label>
        <button type="button" onClick={handleUploadImage} disabled={uploading || !imageFile} className="admin-btn-secondary">
          {uploading ? 'Uploading...' : 'Upload image'}
        </button>
      </div>

      {previewImage ? (
        <div className="admin-image-preview">
          <img src={previewImage} alt="Menu item" />
          <code>{previewImage}</code>
        </div>
      ) : null}

      {imageStatus ? <p className="admin-success">{imageStatus}</p> : null}
      {error ? <p className="admin-error">{error}</p> : null}

      <button className="admin-cta" type="submit" disabled={saving}>
        {saving ? 'Saving...' : submitLabel}
      </button>
    </form>
  )
}
