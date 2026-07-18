import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { LayoutGrid, Plus, Trash2 } from 'lucide-react'
import { useStore } from '../store/useStore'
import type { FloorPlan } from '../types'
import { Button, EmptyState, Field, Modal, Select, TextInput } from '../components/ui'

export default function FloorPlansPage() {
  const floorPlans = useStore((s) => s.floorPlans)
  const events = useStore((s) => s.events)
  const addFloorPlan = useStore((s) => s.addFloorPlan)
  const removeFloorPlan = useStore((s) => s.removeFloorPlan)
  const navigate = useNavigate()

  const [open, setOpen] = useState(false)
  const [draft, setDraft] = useState({ name: '', roomWidthFt: 40, roomDepthFt: 30, eventId: '' })

  const create = () => {
    const plan = addFloorPlan({
      name: draft.name.trim() || 'Untitled Plan',
      roomWidthFt: Math.max(4, draft.roomWidthFt),
      roomDepthFt: Math.max(4, draft.roomDepthFt),
      eventId: draft.eventId || null,
    })
    setOpen(false)
    setDraft({ name: '', roomWidthFt: 40, roomDepthFt: 30, eventId: '' })
    navigate(`/floorplans/${plan.id}`)
  }

  return (
    <div className="mx-auto max-w-6xl px-6 py-8">
      <header className="mb-6 flex items-end justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-800">Floor Plans</h1>
          <p className="mt-1 text-sm text-slate-500">
            Design a room to scale and place your inventory where you need it.
          </p>
        </div>
        <Button onClick={() => setOpen(true)}>
          <Plus size={16} /> New Floor Plan
        </Button>
      </header>

      {floorPlans.length === 0 ? (
        <EmptyState
          icon={<LayoutGrid size={40} />}
          title="No floor plans yet"
          message="Create a plan, enter your room dimensions, and start dropping tables in."
          action={
            <Button onClick={() => setOpen(true)}>
              <Plus size={16} /> New Floor Plan
            </Button>
          }
        />
      ) : (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {floorPlans.map((plan) => (
            <PlanCard
              key={plan.id}
              plan={plan}
              eventName={events.find((e) => e.id === plan.eventId)?.eventName}
              onOpen={() => navigate(`/floorplans/${plan.id}`)}
              onDelete={() => removeFloorPlan(plan.id)}
            />
          ))}
        </div>
      )}

      <Modal open={open} onClose={() => setOpen(false)} title="New Floor Plan">
        <div className="space-y-4">
          <Field label="Plan Name">
            <TextInput
              autoFocus
              value={draft.name}
              placeholder="e.g. Reception Layout"
              onChange={(e) => setDraft({ ...draft, name: e.target.value })}
            />
          </Field>
          <div className="grid grid-cols-2 gap-4">
            <Field label="Room Width (ft)">
              <TextInput
                type="number"
                min={4}
                value={draft.roomWidthFt}
                onChange={(e) => setDraft({ ...draft, roomWidthFt: Number(e.target.value) })}
              />
            </Field>
            <Field label="Room Depth (ft)">
              <TextInput
                type="number"
                min={4}
                value={draft.roomDepthFt}
                onChange={(e) => setDraft({ ...draft, roomDepthFt: Number(e.target.value) })}
              />
            </Field>
          </div>
          <Field label="Link to Event (optional)">
            <Select value={draft.eventId} onChange={(e) => setDraft({ ...draft, eventId: e.target.value })}>
              <option value="">None</option>
              {events.map((e) => (
                <option key={e.id} value={e.id}>
                  {e.clientName ? `${e.clientName} — ` : ''}
                  {e.eventName || 'Untitled Event'}
                </option>
              ))}
            </Select>
          </Field>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="secondary" onClick={() => setOpen(false)}>
              Cancel
            </Button>
            <Button onClick={create}>Create &amp; Open</Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}

function PlanCard({
  plan,
  eventName,
  onOpen,
  onDelete,
}: {
  plan: FloorPlan
  eventName?: string
  onOpen: () => void
  onDelete: () => void
}) {
  // Mini preview scaled to fit a 220x140 box
  const pad = 6
  const boxW = 220
  const boxH = 140
  const scale = Math.min(
    (boxW - pad * 2) / plan.roomWidthFt,
    (boxH - pad * 2) / plan.roomDepthFt,
  )
  const roomW = plan.roomWidthFt * scale
  const roomH = plan.roomDepthFt * scale

  return (
    <div className="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <button onClick={onOpen} className="block w-full">
        <div className="flex h-36 items-center justify-center bg-slate-50">
          <svg width={roomW} height={roomH} className="overflow-visible">
            <rect
              x={0}
              y={0}
              width={roomW}
              height={roomH}
              fill="#ffffff"
              stroke="#94a3b8"
              strokeWidth={1.5}
            />
            {plan.items.map((it) => {
              const w = it.widthFt * scale
              const h = (it.shape === 'round' ? it.widthFt : it.depthFt) * scale
              const cx = it.x * scale
              const cy = it.y * scale
              return it.shape === 'round' ? (
                <circle key={it.id} cx={cx} cy={cy} r={w / 2} fill={it.color} opacity={0.85} />
              ) : (
                <rect
                  key={it.id}
                  x={cx - w / 2}
                  y={cy - h / 2}
                  width={w}
                  height={h}
                  fill={it.color}
                  opacity={0.85}
                  transform={`rotate(${it.rotation} ${cx} ${cy})`}
                />
              )
            })}
          </svg>
        </div>
      </button>
      <div className="flex items-start justify-between p-4">
        <button onClick={onOpen} className="text-left">
          <div className="font-semibold text-slate-800">{plan.name}</div>
          <div className="mt-0.5 text-xs text-slate-400">
            {plan.roomWidthFt}ft × {plan.roomDepthFt}ft · {plan.items.length} items
            {eventName ? ` · ${eventName}` : ''}
          </div>
        </button>
        <button
          onClick={onDelete}
          className="rounded-lg p-1.5 text-slate-300 opacity-0 transition-opacity hover:bg-red-50 hover:text-red-500 group-hover:opacity-100"
        >
          <Trash2 size={15} />
        </button>
      </div>
    </div>
  )
}
