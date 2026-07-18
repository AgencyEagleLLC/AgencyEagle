import { useMemo, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import {
  ArrowLeft,
  Clock,
  Copy,
  Download,
  FileText,
  Pencil,
  Plus,
  Settings,
  Sparkles,
  Trash2,
} from 'lucide-react'
import { useStore } from '../store/useStore'
import type { EventRecord, TimelineEntry, Vendor } from '../types'
import { Button, EmptyState, Field, Modal, Select, TextArea, TextInput } from '../components/ui'
import { buildSlots, formatDateLong, formatMin, parseTime, toTimeInput } from '../lib/time'
import { exportGlobalTimelinePDF, exportVendorTimelinePDF } from '../lib/pdf'
import { generateSocialPost } from '../lib/social'

const LANE_COLORS = [
  '#0ea5e9', '#8b5cf6', '#f97316', '#22c55e', '#ec4899',
  '#14b8a6', '#f59e0b', '#6366f1', '#ef4444', '#0f766e',
]

const SLOT_WIDTH = 46

interface EntryDraft {
  vendorId: string
  label: string
  startMin: number
  endMin: number
  notes: string
}

export default function EventTimelinePage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const event = useStore((s) => s.events.find((e) => e.id === id))
  const vendors = useStore((s) => s.vendors)
  const floorPlans = useStore((s) => s.floorPlans)
  const updateEvent = useStore((s) => s.updateEvent)
  const addTimelineEntry = useStore((s) => s.addTimelineEntry)
  const updateTimelineEntry = useStore((s) => s.updateTimelineEntry)
  const removeTimelineEntry = useStore((s) => s.removeTimelineEntry)

  const [settingsOpen, setSettingsOpen] = useState(false)
  const [entryModal, setEntryModal] = useState<{ mode: 'add' | 'edit'; entry?: TimelineEntry } | null>(null)
  const [socialOpen, setSocialOpen] = useState(false)
  const [vendorPickOpen, setVendorPickOpen] = useState(false)

  const gridStart = event ? parseTime(event.startTime) ?? 0 : 0
  const gridEndRaw = event ? parseTime(event.endTime) ?? 0 : 0
  const slots = useMemo(() => buildSlots(gridStart, gridEndRaw, 15), [gridStart, gridEndRaw])
  const gridEnd = slots[slots.length - 1] ?? gridStart

  // Vendor lanes used in this event's timeline
  const laneVendorIds = useMemo(() => {
    if (!event) return []
    const ids = Array.from(new Set(event.timeline.map((t) => t.vendorId)))
    return ids
  }, [event])

  const vendorById = useMemo(() => new Map(vendors.map((v) => [v.id, v])), [vendors])
  const laneColor = (vendorId: string) =>
    LANE_COLORS[laneVendorIds.indexOf(vendorId) % LANE_COLORS.length]

  if (!event) {
    return (
      <div className="mx-auto max-w-3xl px-6 py-16 text-center">
        <p className="text-slate-500">Event not found.</p>
        <Link to="/events" className="mt-3 inline-block text-teal-700 underline">
          Back to events
        </Link>
      </div>
    )
  }

  const normalizeToSlot = (min: number) => Math.round(min / 15) * 15

  const openAdd = () => {
    setEntryModal({ mode: 'add' })
  }

  return (
    <div className="mx-auto max-w-6xl px-6 py-8">
      <button
        onClick={() => navigate('/events')}
        className="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700"
      >
        <ArrowLeft size={15} /> All events
      </button>

      <header className="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-800">
            {event.eventName || 'Untitled Event'}
          </h1>
          <p className="mt-1 text-sm text-slate-500">
            {event.clientName || 'No client'} · {formatDateLong(event.date)} ·{' '}
            {formatMin(gridStart)} – {formatMin(gridEndRaw)}
            {event.venue ? ` · ${event.venue}` : ''}
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button variant="secondary" onClick={() => setSettingsOpen(true)}>
            <Settings size={16} /> Settings
          </Button>
          <Button variant="secondary" onClick={() => setSocialOpen(true)}>
            <Sparkles size={16} /> Social Post
          </Button>
          <Button variant="secondary" onClick={() => setVendorPickOpen(true)}>
            <FileText size={16} /> Vendor PDF
          </Button>
          <Button onClick={() => exportGlobalTimelinePDF(event, vendors)}>
            <Download size={16} /> Master PDF
          </Button>
        </div>
      </header>

      <div className="mb-4 flex items-center justify-between">
        <h2 className="flex items-center gap-2 text-sm font-semibold text-slate-600">
          <Clock size={16} /> Timeline · 15-minute increments
        </h2>
        <Button onClick={openAdd} disabled={vendors.length === 0}>
          <Plus size={16} /> Add Vendor Block
        </Button>
      </div>

      {vendors.length === 0 && (
        <p className="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700">
          You have no vendors yet.{' '}
          <Link to="/vendors" className="font-semibold underline">
            Add vendors
          </Link>{' '}
          first, then assign them to time blocks here.
        </p>
      )}

      {laneVendorIds.length === 0 ? (
        <EmptyState
          icon={<Clock size={40} />}
          title="No timeline blocks yet"
          message="Add a vendor block to lay them onto the schedule in 15-minute increments."
          action={
            <Button onClick={openAdd} disabled={vendors.length === 0}>
              <Plus size={16} /> Add Vendor Block
            </Button>
          }
        />
      ) : (
        <TimelineGrid
          event={event}
          slots={slots}
          gridStart={gridStart}
          gridEnd={gridEnd}
          laneVendorIds={laneVendorIds}
          vendorById={vendorById}
          laneColor={laneColor}
          onEdit={(entry) => setEntryModal({ mode: 'edit', entry })}
        />
      )}

      {/* Chronological list */}
      {event.timeline.length > 0 && (
        <div className="mt-8">
          <h3 className="mb-2 text-sm font-semibold text-slate-600">Run of Show</h3>
          <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
            {[...event.timeline]
              .sort((a, b) => a.startMin - b.startMin)
              .map((t) => {
                const v = vendorById.get(t.vendorId)
                return (
                  <div
                    key={t.id}
                    className="flex items-center gap-4 border-b border-slate-100 px-4 py-2.5 last:border-0"
                  >
                    <div className="w-32 shrink-0 text-sm font-medium text-slate-700">
                      {formatMin(t.startMin)} – {formatMin(t.endMin)}
                    </div>
                    <span
                      className="h-2.5 w-2.5 shrink-0 rounded-full"
                      style={{ backgroundColor: laneColor(t.vendorId) }}
                    />
                    <div className="min-w-0 flex-1">
                      <span className="text-sm font-medium text-slate-800">
                        {t.label || 'Untitled block'}
                      </span>
                      <span className="ml-2 text-sm text-slate-400">
                        {v?.businessName || v?.category || 'Unknown vendor'}
                      </span>
                      {t.notes && <span className="ml-2 text-xs text-slate-400">· {t.notes}</span>}
                    </div>
                    <div className="flex gap-1">
                      <button
                        onClick={() => setEntryModal({ mode: 'edit', entry: t })}
                        className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                      >
                        <Pencil size={14} />
                      </button>
                      <button
                        onClick={() => removeTimelineEntry(event.id, t.id)}
                        className="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-500"
                      >
                        <Trash2 size={14} />
                      </button>
                    </div>
                  </div>
                )
              })}
          </div>
        </div>
      )}

      {settingsOpen && (
        <SettingsModal
          event={event}
          floorPlanOptions={floorPlans.map((p) => ({ id: p.id, name: p.name }))}
          onChange={(patch) => updateEvent(event.id, patch)}
          onClose={() => setSettingsOpen(false)}
        />
      )}

      {entryModal && (
        <EntryModal
          mode={entryModal.mode}
          entry={entryModal.entry}
          vendors={vendors}
          slots={slots}
          defaultStart={normalizeToSlot(gridStart)}
          onSave={(draft) => {
            if (entryModal.mode === 'edit' && entryModal.entry) {
              updateTimelineEntry(event.id, entryModal.entry.id, draft)
            } else {
              addTimelineEntry(event.id, draft)
            }
            setEntryModal(null)
          }}
          onClose={() => setEntryModal(null)}
        />
      )}

      {vendorPickOpen && (
        <Modal open onClose={() => setVendorPickOpen(false)} title="Export Vendor Schedule">
          <p className="mb-3 text-sm text-slate-500">
            Pick a vendor to export a PDF containing only their blocks for this event.
          </p>
          <div className="max-h-80 space-y-1 overflow-y-auto">
            {laneVendorIds.length === 0 && (
              <p className="text-sm text-slate-400">No vendors are scheduled yet.</p>
            )}
            {laneVendorIds.map((vid) => {
              const v = vendorById.get(vid)
              return (
                <button
                  key={vid}
                  onClick={() => {
                    exportVendorTimelinePDF(event, vendors, vid)
                    setVendorPickOpen(false)
                  }}
                  className="flex w-full items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-left text-sm hover:border-teal-300 hover:bg-teal-50/40"
                >
                  <span className="font-medium text-slate-700">
                    {v?.businessName || v?.category || 'Vendor'}
                  </span>
                  <Download size={15} className="text-slate-400" />
                </button>
              )
            })}
          </div>
        </Modal>
      )}

      {socialOpen && (
        <SocialModal event={event} onClose={() => setSocialOpen(false)} />
      )}
    </div>
  )
}

function TimelineGrid({
  event,
  slots,
  gridStart,
  gridEnd,
  laneVendorIds,
  vendorById,
  laneColor,
  onEdit,
}: {
  event: EventRecord
  slots: number[]
  gridStart: number
  gridEnd: number
  laneVendorIds: string[]
  vendorById: Map<string, Vendor>
  laneColor: (id: string) => string
  onEdit: (entry: TimelineEntry) => void
}) {
  const totalWidth = (slots.length - 1) * SLOT_WIDTH
  return (
    <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
      <div style={{ minWidth: totalWidth + 180 }}>
        {/* Time header */}
        <div className="flex border-b border-slate-200 bg-slate-50">
          <div className="w-44 shrink-0 px-3 py-2 text-xs font-semibold text-slate-500">
            Vendor
          </div>
          <div className="relative" style={{ width: totalWidth }}>
            {slots.map((s, i) => (
              <div
                key={s}
                className="absolute top-0 border-l border-slate-200 py-2 text-[10px] text-slate-400"
                style={{ left: i * SLOT_WIDTH }}
              >
                {i % 2 === 0 && <span className="pl-1">{formatMin(s)}</span>}
              </div>
            ))}
          </div>
        </div>

        {/* Lanes */}
        {laneVendorIds.map((vid) => {
          const v = vendorById.get(vid)
          const entries = event.timeline.filter((t) => t.vendorId === vid)
          return (
            <div key={vid} className="flex border-b border-slate-100 last:border-0">
              <div className="flex w-44 shrink-0 items-center gap-2 px-3 py-3">
                <span
                  className="h-2.5 w-2.5 shrink-0 rounded-full"
                  style={{ backgroundColor: laneColor(vid) }}
                />
                <div className="min-w-0">
                  <div className="truncate text-sm font-medium text-slate-700">
                    {v?.businessName || v?.category || 'Vendor'}
                  </div>
                  <div className="truncate text-[11px] text-slate-400">{v?.category}</div>
                </div>
              </div>
              <div
                className="relative my-1.5"
                style={{ width: totalWidth, minHeight: 34 }}
              >
                {/* grid lines */}
                {slots.map((s, i) => (
                  <div
                    key={s}
                    className="absolute top-0 bottom-0 border-l border-slate-100"
                    style={{ left: i * SLOT_WIDTH }}
                  />
                ))}
                {entries.map((t) => {
                  const clampedStart = Math.max(t.startMin, gridStart)
                  const clampedEnd = Math.min(t.endMin, gridEnd)
                  const left = ((clampedStart - gridStart) / 15) * SLOT_WIDTH
                  const width = Math.max(
                    SLOT_WIDTH * 0.6,
                    ((clampedEnd - clampedStart) / 15) * SLOT_WIDTH,
                  )
                  return (
                    <button
                      key={t.id}
                      onClick={() => onEdit(t)}
                      className="absolute top-0 flex h-8 items-center overflow-hidden rounded-md px-2 text-left text-xs font-medium text-white shadow-sm transition-transform hover:scale-[1.02]"
                      style={{ left, width, backgroundColor: laneColor(vid) }}
                      title={`${t.label} · ${formatMin(t.startMin)}–${formatMin(t.endMin)}`}
                    >
                      <span className="truncate">{t.label || formatMin(t.startMin)}</span>
                    </button>
                  )
                })}
              </div>
            </div>
          )
        })}
      </div>
    </div>
  )
}

function EntryModal({
  mode,
  entry,
  vendors,
  slots,
  defaultStart,
  onSave,
  onClose,
}: {
  mode: 'add' | 'edit'
  entry?: TimelineEntry
  vendors: { id: string; businessName: string; category: string }[]
  slots: number[]
  defaultStart: number
  onSave: (draft: EntryDraft) => void
  onClose: () => void
}) {
  const [draft, setDraft] = useState<EntryDraft>({
    vendorId: entry?.vendorId ?? vendors[0]?.id ?? '',
    label: entry?.label ?? '',
    startMin: entry?.startMin ?? defaultStart,
    endMin: entry?.endMin ?? defaultStart + 60,
    notes: entry?.notes ?? '',
  })

  const startOptions = slots.slice(0, -1)
  const endOptions = slots.filter((s) => s > draft.startMin)

  const valid = draft.vendorId && draft.endMin > draft.startMin

  return (
    <Modal open onClose={onClose} title={mode === 'edit' ? 'Edit Block' : 'Add Vendor Block'}>
      <div className="space-y-4">
        <Field label="Vendor">
          <Select
            value={draft.vendorId}
            onChange={(e) => setDraft({ ...draft, vendorId: e.target.value })}
          >
            {vendors.map((v) => (
              <option key={v.id} value={v.id}>
                {v.businessName || 'Untitled'} — {v.category}
              </option>
            ))}
          </Select>
        </Field>
        <Field label="Activity / Label">
          <TextInput
            autoFocus
            value={draft.label}
            placeholder="e.g. Bar service, First dance, Dinner served"
            onChange={(e) => setDraft({ ...draft, label: e.target.value })}
          />
        </Field>
        <div className="grid grid-cols-2 gap-4">
          <Field label="Start">
            <Select
              value={draft.startMin}
              onChange={(e) => {
                const startMin = Number(e.target.value)
                setDraft((d) => ({
                  ...d,
                  startMin,
                  endMin: d.endMin <= startMin ? startMin + 15 : d.endMin,
                }))
              }}
            >
              {startOptions.map((s) => (
                <option key={s} value={s}>
                  {formatMin(s)}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="End">
            <Select
              value={draft.endMin}
              onChange={(e) => setDraft({ ...draft, endMin: Number(e.target.value) })}
            >
              {endOptions.map((s) => (
                <option key={s} value={s}>
                  {formatMin(s)}
                </option>
              ))}
            </Select>
          </Field>
        </div>
        <Field label="Notes">
          <TextArea
            value={draft.notes}
            onChange={(e) => setDraft({ ...draft, notes: e.target.value })}
          />
        </Field>
        <div className="flex justify-end gap-2 pt-1">
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button onClick={() => onSave(draft)} disabled={!valid}>
            {mode === 'edit' ? 'Save Block' : 'Add Block'}
          </Button>
        </div>
      </div>
    </Modal>
  )
}

function SettingsModal({
  event,
  floorPlanOptions,
  onChange,
  onClose,
}: {
  event: EventRecord
  floorPlanOptions: { id: string; name: string }[]
  onChange: (patch: Partial<EventRecord>) => void
  onClose: () => void
}) {
  return (
    <Modal open onClose={onClose} title="Event Settings">
      <div className="space-y-4">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Client / Bride">
            <TextInput value={event.clientName} onChange={(e) => onChange({ clientName: e.target.value })} />
          </Field>
          <Field label="Event Name">
            <TextInput value={event.eventName} onChange={(e) => onChange({ eventName: e.target.value })} />
          </Field>
        </div>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <Field label="Date">
            <TextInput type="date" value={event.date} onChange={(e) => onChange({ date: e.target.value })} />
          </Field>
          <Field label="Start" hint={formatMin(parseTime(event.startTime) ?? 0)}>
            <TextInput
              type="time"
              step={900}
              value={toTimeInput(parseTime(event.startTime) ?? 0)}
              onChange={(e) => onChange({ startTime: e.target.value })}
            />
          </Field>
          <Field label="End" hint={formatMin(parseTime(event.endTime) ?? 0)}>
            <TextInput
              type="time"
              step={900}
              value={toTimeInput(parseTime(event.endTime) ?? 0)}
              onChange={(e) => onChange({ endTime: e.target.value })}
            />
          </Field>
        </div>
        <Field label="Venue">
          <TextInput value={event.venue} onChange={(e) => onChange({ venue: e.target.value })} />
        </Field>
        <Field label="Attached Floor Plan">
          <Select
            value={event.floorPlanId ?? ''}
            onChange={(e) => onChange({ floorPlanId: e.target.value || null })}
          >
            <option value="">None</option>
            {floorPlanOptions.map((p) => (
              <option key={p.id} value={p.id}>
                {p.name}
              </option>
            ))}
          </Select>
        </Field>
        <Field label="Event Notes">
          <TextArea value={event.notes} onChange={(e) => onChange({ notes: e.target.value })} />
        </Field>
        <div className="flex justify-end pt-1">
          <Button onClick={onClose}>Done</Button>
        </div>
      </div>
    </Modal>
  )
}

function SocialModal({ event, onClose }: { event: EventRecord; onClose: () => void }) {
  const vendors = useStore((s) => s.vendors)
  const [text, setText] = useState(() => generateSocialPost(event, vendors))
  const [copied, setCopied] = useState(false)

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(text)
      setCopied(true)
      setTimeout(() => setCopied(false), 1500)
    } catch {
      /* clipboard blocked — user can copy manually */
    }
  }

  return (
    <Modal open onClose={onClose} title="Generated Social Post" wide>
      <p className="mb-3 text-sm text-slate-500">
        Auto-drafted from this event's timeline and vendor Instagram handles. Edit freely before posting.
      </p>
      <TextArea
        value={text}
        onChange={(e) => setText(e.target.value)}
        className="min-h-64 font-mono text-[13px]"
      />
      <div className="mt-3 flex justify-end gap-2">
        <Button variant="secondary" onClick={() => setText(generateSocialPost(event, vendors))}>
          Regenerate
        </Button>
        <Button onClick={copy}>
          <Copy size={15} /> {copied ? 'Copied!' : 'Copy'}
        </Button>
      </div>
    </Modal>
  )
}
