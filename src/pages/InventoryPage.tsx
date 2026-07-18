import { useState } from 'react'
import { Circle, Pencil, Plus, Square, Trash2 } from 'lucide-react'
import { useStore } from '../store/useStore'
import type { CatalogItem, ItemShape } from '../types'
import { Badge, Button, Field, Modal, Select, TextInput } from '../components/ui'

const PALETTE = [
  '#0ea5e9', '#3b82f6', '#8b5cf6', '#a855f7', '#ec4899', '#f43f5e',
  '#f97316', '#f59e0b', '#14b8a6', '#22c55e', '#64748b', '#0f766e',
]

type Draft = Omit<CatalogItem, 'id' | 'isDefault'>

const blank: Draft = {
  title: '',
  shape: 'rect',
  widthFt: 6,
  depthFt: 2.5,
  chairs: 0,
  color: PALETTE[0],
}

export default function InventoryPage() {
  const catalog = useStore((s) => s.catalog)
  const addCatalogItem = useStore((s) => s.addCatalogItem)
  const updateCatalogItem = useStore((s) => s.updateCatalogItem)
  const removeCatalogItem = useStore((s) => s.removeCatalogItem)

  const [open, setOpen] = useState(false)
  const [editingId, setEditingId] = useState<string | null>(null)
  const [draft, setDraft] = useState<Draft>(blank)

  const startAdd = () => {
    setEditingId(null)
    setDraft(blank)
    setOpen(true)
  }

  const startEdit = (item: CatalogItem) => {
    setEditingId(item.id)
    setDraft({
      title: item.title,
      shape: item.shape,
      widthFt: item.widthFt,
      depthFt: item.depthFt,
      chairs: item.chairs,
      color: item.color,
    })
    setOpen(true)
  }

  const save = () => {
    if (!draft.title.trim()) return
    const normalized: Draft = {
      ...draft,
      depthFt: draft.shape === 'round' ? draft.widthFt : draft.depthFt,
    }
    if (editingId) updateCatalogItem(editingId, normalized)
    else addCatalogItem(normalized)
    setOpen(false)
  }

  return (
    <div className="mx-auto max-w-6xl px-6 py-8">
      <header className="mb-6 flex items-end justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-800">Inventory</h1>
          <p className="mt-1 text-sm text-slate-500">
            Reusable furniture &amp; equipment you can drop onto any floor plan.
          </p>
        </div>
        <Button onClick={startAdd}>
          <Plus size={16} /> Add Item
        </Button>
      </header>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {catalog.map((item) => (
          <div
            key={item.id}
            className="group flex flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
          >
            <div className="flex items-start justify-between">
              <div className="flex items-center gap-2">
                <span
                  className="flex h-10 w-10 items-center justify-center rounded-lg text-white"
                  style={{ backgroundColor: item.color }}
                >
                  {item.shape === 'round' ? <Circle size={18} /> : <Square size={18} />}
                </span>
                <div>
                  <div className="text-sm font-semibold leading-tight text-slate-800">
                    {item.title}
                  </div>
                  <div className="text-xs text-slate-400">
                    {item.shape === 'round'
                      ? `${item.widthFt}ft round`
                      : `${item.widthFt}ft × ${item.depthFt}ft`}
                  </div>
                </div>
              </div>
              <div className="flex gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                <button
                  onClick={() => startEdit(item)}
                  className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                >
                  <Pencil size={15} />
                </button>
                <button
                  onClick={() => removeCatalogItem(item.id)}
                  className="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-500"
                >
                  <Trash2 size={15} />
                </button>
              </div>
            </div>
            <div className="mt-3 flex flex-wrap gap-1.5">
              {item.chairs > 0 && (
                <Badge className="bg-slate-100 text-slate-600">{item.chairs} chairs</Badge>
              )}
              {item.isDefault && (
                <Badge className="bg-teal-50 text-teal-700">Default</Badge>
              )}
            </div>
          </div>
        ))}
      </div>

      <Modal
        open={open}
        onClose={() => setOpen(false)}
        title={editingId ? 'Edit Item' : 'Add Inventory Item'}
      >
        <div className="space-y-4">
          <Field label="Title">
            <TextInput
              autoFocus
              value={draft.title}
              placeholder="e.g. 8ft Banquet Table · 10 chairs"
              onChange={(e) => setDraft({ ...draft, title: e.target.value })}
            />
          </Field>
          <div className="grid grid-cols-2 gap-4">
            <Field label="Shape">
              <Select
                value={draft.shape}
                onChange={(e) => setDraft({ ...draft, shape: e.target.value as ItemShape })}
              >
                <option value="rect">Rectangle</option>
                <option value="round">Round</option>
              </Select>
            </Field>
            <Field label="Chairs">
              <TextInput
                type="number"
                min={0}
                value={draft.chairs}
                onChange={(e) => setDraft({ ...draft, chairs: Math.max(0, Number(e.target.value)) })}
              />
            </Field>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <Field label={draft.shape === 'round' ? 'Diameter (ft)' : 'Width (ft)'}>
              <TextInput
                type="number"
                min={0.5}
                step={0.5}
                value={draft.widthFt}
                onChange={(e) => setDraft({ ...draft, widthFt: Number(e.target.value) })}
              />
            </Field>
            <Field label="Depth (ft)">
              <TextInput
                type="number"
                min={0.5}
                step={0.5}
                disabled={draft.shape === 'round'}
                value={draft.shape === 'round' ? draft.widthFt : draft.depthFt}
                onChange={(e) => setDraft({ ...draft, depthFt: Number(e.target.value) })}
              />
            </Field>
          </div>
          <Field label="Color">
            <div className="flex flex-wrap gap-2">
              {PALETTE.map((c) => (
                <button
                  key={c}
                  onClick={() => setDraft({ ...draft, color: c })}
                  className={`h-7 w-7 rounded-full border-2 ${
                    draft.color === c ? 'border-slate-800' : 'border-transparent'
                  }`}
                  style={{ backgroundColor: c }}
                />
              ))}
            </div>
          </Field>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="secondary" onClick={() => setOpen(false)}>
              Cancel
            </Button>
            <Button onClick={save} disabled={!draft.title.trim()}>
              {editingId ? 'Save Changes' : 'Add Item'}
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
