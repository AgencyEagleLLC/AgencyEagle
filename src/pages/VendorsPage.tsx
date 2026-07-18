import { useMemo, useState } from 'react'
import {
  AtSign,
  CreditCard,
  Mail,
  Phone,
  Pencil,
  Plus,
  Search,
  ShieldAlert,
  Tag,
  Trash2,
  Users,
} from 'lucide-react'
import { useStore } from '../store/useStore'
import type { Vendor } from '../types'
import { Badge, Button, EmptyState, Field, Modal, Select, TextArea, TextInput } from '../components/ui'
import { useReadOnly } from '../components/RoleGate'

export default function VendorsPage() {
  const vendors = useStore((s) => s.vendors)
  const categories = useStore((s) => s.vendorCategories)
  const addVendor = useStore((s) => s.addVendor)
  const updateVendor = useStore((s) => s.updateVendor)
  const removeVendor = useStore((s) => s.removeVendor)
  const addVendorCategory = useStore((s) => s.addVendorCategory)
  const removeVendorCategory = useStore((s) => s.removeVendorCategory)

  const readOnly = useReadOnly()
  const [query, setQuery] = useState('')
  const [filter, setFilter] = useState('all')
  const [editing, setEditing] = useState<Vendor | null>(null)
  const [catOpen, setCatOpen] = useState(false)
  const [newCat, setNewCat] = useState('')

  const filtered = useMemo(() => {
    const q = query.toLowerCase()
    return vendors.filter((v) => {
      const matchesCat = filter === 'all' || v.category === filter
      const matchesQuery =
        !q ||
        v.businessName.toLowerCase().includes(q) ||
        v.contactName.toLowerCase().includes(q) ||
        v.category.toLowerCase().includes(q)
      return matchesCat && matchesQuery
    })
  }, [vendors, query, filter])

  const startAdd = () => {
    const created = addVendor()
    if (created) setEditing(created)
  }

  return (
    <div className="mx-auto max-w-6xl px-6 py-8">
      <header className="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-slate-800">Vendors</h1>
          <p className="mt-1 text-sm text-slate-500">
            Your directory of vendors — contacts, emergency numbers, socials &amp; payment prefs.
          </p>
        </div>
        <div className="flex gap-2">
          <Button variant="secondary" onClick={() => setCatOpen(true)} disabled={readOnly}>
            <Tag size={16} /> Categories
          </Button>
          <Button onClick={startAdd} disabled={readOnly}>
            <Plus size={16} /> Add Vendor
          </Button>
        </div>
      </header>

      <div className="mb-5 flex flex-wrap gap-3">
        <div className="relative flex-1 min-w-56">
          <Search size={16} className="absolute left-3 top-2.5 text-slate-400" />
          <input
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Search vendors…"
            className="w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20"
          />
        </div>
        <Select value={filter} onChange={(e) => setFilter(e.target.value)} className="max-w-48">
          <option value="all">All categories</option>
          {categories.map((c) => (
            <option key={c} value={c}>
              {c}
            </option>
          ))}
        </Select>
      </div>

      {filtered.length === 0 ? (
        <EmptyState
          icon={<Users size={40} />}
          title="No vendors yet"
          message="Add your first vendor to start building your directory."
          action={
            <Button onClick={startAdd} disabled={readOnly}>
              <Plus size={16} /> Add Vendor
            </Button>
          }
        />
      ) : (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          {filtered.map((v) => (
            <VendorCard
              key={v.id}
              vendor={v}
              onEdit={() => setEditing(v)}
              onDelete={() => removeVendor(v.id)}
            />
          ))}
        </div>
      )}

      {editing && (
        <VendorModal
          vendor={editing}
          categories={categories}
          onChange={(patch) => updateVendor(editing.id, patch)}
          onClose={() => setEditing(null)}
        />
      )}

      <Modal open={catOpen} onClose={() => setCatOpen(false)} title="Vendor Categories">
        <div className="space-y-4">
          <div className="flex gap-2">
            <TextInput
              value={newCat}
              placeholder="New category name"
              onChange={(e) => setNewCat(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter' && newCat.trim()) {
                  addVendorCategory(newCat)
                  setNewCat('')
                }
              }}
            />
            <Button
              onClick={() => {
                addVendorCategory(newCat)
                setNewCat('')
              }}
              disabled={!newCat.trim()}
            >
              Add
            </Button>
          </div>
          <div className="flex flex-wrap gap-2">
            {categories.map((c) => (
              <span
                key={c}
                className="inline-flex items-center gap-1.5 rounded-full bg-slate-100 py-1 pl-3 pr-1.5 text-sm text-slate-700"
              >
                {c}
                <button
                  onClick={() => removeVendorCategory(c)}
                  className="rounded-full p-0.5 text-slate-400 hover:bg-slate-200 hover:text-red-500"
                  title="Remove"
                >
                  <Trash2 size={13} />
                </button>
              </span>
            ))}
          </div>
        </div>
      </Modal>
    </div>
  )
}

function VendorCard({
  vendor,
  onEdit,
  onDelete,
}: {
  vendor: Vendor
  onEdit: () => void
  onDelete: () => void
}) {
  return (
    <div className="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="flex items-start justify-between">
        <div>
          <div className="flex items-center gap-2">
            <h3 className="text-base font-semibold text-slate-800">
              {vendor.businessName || 'Untitled Vendor'}
            </h3>
            <Badge className="bg-teal-50 text-teal-700">{vendor.category}</Badge>
          </div>
          {vendor.contactName && (
            <p className="mt-0.5 text-sm text-slate-500">{vendor.contactName}</p>
          )}
        </div>
        <div className="flex gap-1 opacity-0 transition-opacity group-hover:opacity-100">
          <button
            onClick={onEdit}
            className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
          >
            <Pencil size={15} />
          </button>
          <button
            onClick={onDelete}
            className="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-500"
          >
            <Trash2 size={15} />
          </button>
        </div>
      </div>

      <div className="mt-3 space-y-1.5 text-sm text-slate-600">
        {vendor.phone && (
          <div className="flex items-center gap-2">
            <Phone size={14} className="text-slate-400" /> {vendor.phone}
          </div>
        )}
        {vendor.email && (
          <div className="flex items-center gap-2">
            <Mail size={14} className="text-slate-400" /> {vendor.email}
          </div>
        )}
        {vendor.ownerCell && (
          <div className="flex items-center gap-2 text-amber-700">
            <ShieldAlert size={14} className="text-amber-500" /> Owner cell: {vendor.ownerCell}
          </div>
        )}
        {vendor.social.instagram && (
          <div className="flex items-center gap-2">
            <AtSign size={14} className="text-slate-400" /> {vendor.social.instagram}
          </div>
        )}
        {vendor.paymentNotes && (
          <div className="flex items-start gap-2 text-slate-500">
            <CreditCard size={14} className="mt-0.5 shrink-0 text-slate-400" />
            <span className="line-clamp-2">{vendor.paymentNotes}</span>
          </div>
        )}
      </div>
    </div>
  )
}

function VendorModal({
  vendor,
  categories,
  onChange,
  onClose,
}: {
  vendor: Vendor
  categories: string[]
  onChange: (patch: Partial<Vendor>) => void
  onClose: () => void
}) {
  const set = (patch: Partial<Vendor>) => onChange(patch)
  const setSocial = (patch: Partial<Vendor['social']>) =>
    onChange({ social: { ...vendor.social, ...patch } })

  return (
    <Modal open onClose={onClose} title="Vendor Details" wide>
      <div className="space-y-5">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Business Name">
            <TextInput
              autoFocus
              value={vendor.businessName}
              onChange={(e) => set({ businessName: e.target.value })}
            />
          </Field>
          <Field label="Category">
            <Select value={vendor.category} onChange={(e) => set({ category: e.target.value })}>
              {categories.map((c) => (
                <option key={c} value={c}>
                  {c}
                </option>
              ))}
            </Select>
          </Field>
        </div>

        <fieldset className="rounded-xl border border-slate-200 p-4">
          <legend className="px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
            Primary Contact
          </legend>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Field label="Contact Name">
              <TextInput value={vendor.contactName} onChange={(e) => set({ contactName: e.target.value })} />
            </Field>
            <Field label="Phone">
              <TextInput value={vendor.phone} onChange={(e) => set({ phone: e.target.value })} />
            </Field>
            <Field label="Email">
              <TextInput type="email" value={vendor.email} onChange={(e) => set({ email: e.target.value })} />
            </Field>
            <Field label="Website">
              <TextInput value={vendor.website} onChange={(e) => set({ website: e.target.value })} />
            </Field>
            <Field label="Address" className="sm:col-span-2">
              <TextInput value={vendor.address} onChange={(e) => set({ address: e.target.value })} />
            </Field>
          </div>
        </fieldset>

        <fieldset className="rounded-xl border border-amber-200 bg-amber-50/40 p-4">
          <legend className="px-1 text-xs font-semibold uppercase tracking-wide text-amber-700">
            Emergency / Owner Contact
          </legend>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Field label="Owner Name">
              <TextInput value={vendor.ownerName} onChange={(e) => set({ ownerName: e.target.value })} />
            </Field>
            <Field label="Owner Cell">
              <TextInput value={vendor.ownerCell} onChange={(e) => set({ ownerCell: e.target.value })} />
            </Field>
            <Field label="Other Emergency Contact" className="sm:col-span-2">
              <TextInput
                value={vendor.emergencyContact}
                onChange={(e) => set({ emergencyContact: e.target.value })}
                placeholder="Name & number for day-of emergencies"
              />
            </Field>
          </div>
        </fieldset>

        <fieldset className="rounded-xl border border-slate-200 p-4">
          <legend className="px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
            Social Media <span className="normal-case text-slate-400">(used for post generation)</span>
          </legend>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Field label="Instagram">
              <TextInput value={vendor.social.instagram} placeholder="@handle or URL" onChange={(e) => setSocial({ instagram: e.target.value })} />
            </Field>
            <Field label="Facebook">
              <TextInput value={vendor.social.facebook} onChange={(e) => setSocial({ facebook: e.target.value })} />
            </Field>
            <Field label="TikTok">
              <TextInput value={vendor.social.tiktok} placeholder="@handle or URL" onChange={(e) => setSocial({ tiktok: e.target.value })} />
            </Field>
            <Field label="Other">
              <TextInput value={vendor.social.other} onChange={(e) => setSocial({ other: e.target.value })} />
            </Field>
          </div>
        </fieldset>

        <Field label="Payment Details / Preferences">
          <TextArea
            value={vendor.paymentNotes}
            placeholder="e.g. Prefers check made out to… / Zelle to 555-… / Deposit 50% due 30 days out"
            onChange={(e) => set({ paymentNotes: e.target.value })}
          />
        </Field>

        <Field label="General Notes">
          <TextArea value={vendor.notes} onChange={(e) => set({ notes: e.target.value })} />
        </Field>

        <div className="flex justify-end pt-1">
          <Button onClick={onClose}>Done</Button>
        </div>
      </div>
    </Modal>
  )
}
