import { lazy, Suspense, useCallback, useEffect, useRef, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import {
  ArrowLeft,
  Box,
  Copy,
  Grid3x3,
  Maximize,
  Minus,
  Plus,
  RotateCw,
  Square as SquareIcon,
  Trash2,
  ZoomIn,
  ZoomOut,
} from 'lucide-react'
import { useStore } from '../store/useStore'
import type { CatalogItem, PlacedItem } from '../types'
import { Button, Field, TextInput } from '../components/ui'
import { CHAIR, chairPositions } from '../lib/layout'

const FloorPlan3D = lazy(() => import('../components/FloorPlan3D'))

const MIN_PX = 6
const MAX_PX = 40

type ViewMode = '2d' | '3d'

export default function FloorPlanEditorPage() {
  const { id } = useParams()
  const plan = useStore((s) => s.floorPlans.find((p) => p.id === id))
  const catalog = useStore((s) => s.catalog)
  const updateFloorPlan = useStore((s) => s.updateFloorPlan)
  const addPlacedItem = useStore((s) => s.addPlacedItem)
  const updatePlacedItem = useStore((s) => s.updatePlacedItem)
  const removePlacedItem = useStore((s) => s.removePlacedItem)

  const [pxPerFoot, setPxPerFoot] = useState(14)
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [snap, setSnap] = useState(true)
  const [view, setView] = useState<ViewMode>('2d')
  const [dragPos, setDragPos] = useState<{ id: string; x: number; y: number } | null>(null)

  const svgRef = useRef<SVGSVGElement>(null)
  const dragOffset = useRef<{ x: number; y: number } | null>(null)

  const selected = plan?.items.find((it) => it.id === selectedId) ?? null

  const clientToFeet = useCallback(
    (clientX: number, clientY: number) => {
      const rect = svgRef.current?.getBoundingClientRect()
      if (!rect) return { x: 0, y: 0 }
      return {
        x: (clientX - rect.left) / pxPerFoot,
        y: (clientY - rect.top) / pxPerFoot,
      }
    },
    [pxPerFoot],
  )

  const snapVal = useCallback((v: number) => (snap ? Math.round(v * 2) / 2 : v), [snap])

  // Drag handling
  useEffect(() => {
    if (!dragPos || !plan) return
    const onMove = (e: MouseEvent) => {
      const item = plan.items.find((it) => it.id === dragPos.id)
      if (!item || !dragOffset.current) return
      const ptr = clientToFeet(e.clientX, e.clientY)
      const halfW = item.widthFt / 2
      const halfH = (item.shape === 'round' ? item.widthFt : item.depthFt) / 2
      let nx = ptr.x - dragOffset.current.x
      let ny = ptr.y - dragOffset.current.y
      nx = Math.min(Math.max(nx, halfW), plan.roomWidthFt - halfW)
      ny = Math.min(Math.max(ny, halfH), plan.roomDepthFt - halfH)
      setDragPos({ id: dragPos.id, x: snapVal(nx), y: snapVal(ny) })
    }
    const onUp = () => {
      if (dragPos) updatePlacedItem(plan.id, dragPos.id, { x: dragPos.x, y: dragPos.y })
      dragOffset.current = null
      setDragPos(null)
    }
    window.addEventListener('mousemove', onMove)
    window.addEventListener('mouseup', onUp)
    return () => {
      window.removeEventListener('mousemove', onMove)
      window.removeEventListener('mouseup', onUp)
    }
  }, [dragPos, plan, clientToFeet, snapVal, updatePlacedItem])

  // Keyboard shortcuts
  useEffect(() => {
    if (!plan) return
    const onKey = (e: KeyboardEvent) => {
      const tag = (e.target as HTMLElement)?.tagName
      if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return
      if (!selectedId) return
      const item = plan.items.find((it) => it.id === selectedId)
      if (!item) return
      if (e.key === 'Delete' || e.key === 'Backspace') {
        e.preventDefault()
        removePlacedItem(plan.id, selectedId)
        setSelectedId(null)
      } else if (e.key === 'r' || e.key === 'R') {
        updatePlacedItem(plan.id, selectedId, { rotation: (item.rotation + 15) % 360 })
      } else if (e.key.startsWith('Arrow')) {
        e.preventDefault()
        const step = e.shiftKey ? 1 : 0.5
        const d = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }[e.key]
        if (d) updatePlacedItem(plan.id, selectedId, { x: item.x + d[0], y: item.y + d[1] })
      }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [plan, selectedId, removePlacedItem, updatePlacedItem])

  if (!plan) {
    return (
      <div className="mx-auto max-w-3xl px-6 py-16 text-center">
        <p className="text-slate-500">Floor plan not found.</p>
        <Link to="/floorplans" className="mt-3 inline-block text-teal-700 underline">
          Back to floor plans
        </Link>
      </div>
    )
  }

  const addItem = (c: CatalogItem) => {
    // Cascade new drops diagonally so they don't perfectly overlap.
    const halfW = c.widthFt / 2
    const halfH = (c.shape === 'round' ? c.widthFt : c.depthFt) / 2
    const step = ((plan.items.length % 6) - 2.5) * 2.5
    const clamp = (v: number, half: number, max: number) =>
      Math.min(Math.max(v, half), max - half)
    const placed: Omit<PlacedItem, 'id'> = {
      catalogId: c.id,
      title: c.title,
      shape: c.shape,
      widthFt: c.widthFt,
      depthFt: c.shape === 'round' ? c.widthFt : c.depthFt,
      chairs: c.chairs,
      color: c.color,
      x: clamp(plan.roomWidthFt / 2 + step, halfW, plan.roomWidthFt),
      y: clamp(plan.roomDepthFt / 2 + step, halfH, plan.roomDepthFt),
      rotation: 0,
    }
    addPlacedItem(plan.id, placed)
  }

  const startDrag = (e: React.MouseEvent, item: PlacedItem) => {
    e.stopPropagation()
    setSelectedId(item.id)
    const ptr = clientToFeet(e.clientX, e.clientY)
    dragOffset.current = { x: ptr.x - item.x, y: ptr.y - item.y }
    setDragPos({ id: item.id, x: item.x, y: item.y })
  }

  const duplicate = (item: PlacedItem) => {
    const { id: _omit, ...rest } = item
    void _omit
    addPlacedItem(plan.id, {
      ...rest,
      x: Math.min(item.x + 2, plan.roomWidthFt - item.widthFt / 2),
      y: Math.min(item.y + 2, plan.roomDepthFt - item.depthFt / 2),
    })
  }

  const canvasW = plan.roomWidthFt * pxPerFoot
  const canvasH = plan.roomDepthFt * pxPerFoot

  return (
    <div className="flex h-full flex-col">
      {/* Top bar */}
      <div className="flex flex-wrap items-center gap-3 border-b border-slate-200 bg-white px-5 py-3">
        <Link
          to="/floorplans"
          className="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700"
        >
          <ArrowLeft size={15} /> Plans
        </Link>
        <input
          value={plan.name}
          onChange={(e) => updateFloorPlan(plan.id, { name: e.target.value })}
          className="rounded-lg border border-transparent px-2 py-1 text-lg font-bold text-slate-800 hover:border-slate-200 focus:border-teal-400 focus:outline-none"
        />
        <div className="ml-2 flex items-center gap-2 text-sm text-slate-500">
          <span>Room</span>
          <DimInput value={plan.roomWidthFt} onChange={(v) => updateFloorPlan(plan.id, { roomWidthFt: v })} />
          <span>×</span>
          <DimInput value={plan.roomDepthFt} onChange={(v) => updateFloorPlan(plan.id, { roomDepthFt: v })} />
          <span>ft</span>
        </div>

        <div className="ml-auto flex items-center gap-1.5">
          {/* 2D / 3D toggle */}
          <div className="flex items-center rounded-lg border border-slate-300 p-0.5">
            <button
              onClick={() => setView('2d')}
              className={`inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-semibold transition-colors ${
                view === '2d' ? 'bg-teal-700 text-white' : 'text-slate-500 hover:text-slate-700'
              }`}
            >
              <SquareIcon size={14} /> 2D
            </button>
            <button
              onClick={() => setView('3d')}
              className={`inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-semibold transition-colors ${
                view === '3d' ? 'bg-teal-700 text-white' : 'text-slate-500 hover:text-slate-700'
              }`}
            >
              <Box size={14} /> 3D
            </button>
          </div>
          <button
            onClick={() => setSnap((s) => !s)}
            className={`inline-flex items-center gap-1 rounded-lg border px-2.5 py-1.5 text-xs font-medium ${
              view === '3d' ? 'hidden ' : ''
            }${snap ? 'border-teal-300 bg-teal-50 text-teal-700' : 'border-slate-300 text-slate-500'}`}
            title="Snap to 0.5ft grid"
          >
            <Grid3x3 size={14} /> Snap
          </button>
          <div className={`flex items-center rounded-lg border border-slate-300 ${view === '3d' ? 'hidden' : ''}`}>
            <button
              onClick={() => setPxPerFoot((p) => Math.max(MIN_PX, p - 2))}
              className="p-1.5 text-slate-500 hover:text-slate-700"
            >
              <ZoomOut size={16} />
            </button>
            <span className="w-10 text-center text-xs text-slate-500">{pxPerFoot}px</span>
            <button
              onClick={() => setPxPerFoot((p) => Math.min(MAX_PX, p + 2))}
              className="p-1.5 text-slate-500 hover:text-slate-700"
            >
              <ZoomIn size={16} />
            </button>
          </div>
          <button
            onClick={() => setPxPerFoot(14)}
            className={`rounded-lg border border-slate-300 p-1.5 text-slate-500 hover:text-slate-700 ${
              view === '3d' ? 'hidden' : ''
            }`}
            title="Reset zoom"
          >
            <Maximize size={16} />
          </button>
        </div>
      </div>

      {view === '3d' ? (
        <div className="relative min-h-0 flex-1 bg-slate-900">
          <Suspense
            fallback={
              <div className="flex h-full items-center justify-center text-sm text-slate-300">
                Loading 3D view…
              </div>
            }
          >
            <FloorPlan3D plan={plan} />
          </Suspense>
          <div className="pointer-events-none absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-slate-800/80 px-4 py-1.5 text-xs text-slate-200 shadow-lg">
            Drag to orbit · scroll to zoom · right-drag to pan · switch to 2D to edit
          </div>
          {plan.items.length === 0 && (
            <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
              <p className="rounded-lg bg-slate-800/80 px-4 py-2 text-sm text-slate-200">
                No items yet — switch to 2D to place tables, then come back to 3D.
              </p>
            </div>
          )}
        </div>
      ) : (
      <div className="flex min-h-0 flex-1">
        {/* Palette */}
        <div className="w-60 shrink-0 overflow-y-auto border-r border-slate-200 bg-white p-3">
          <div className="mb-2 px-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
            Inventory
          </div>
          <div className="space-y-1.5">
            {catalog.map((c) => (
              <button
                key={c.id}
                onClick={() => addItem(c)}
                className="flex w-full items-center gap-2 rounded-lg border border-slate-200 px-2.5 py-2 text-left text-sm hover:border-teal-300 hover:bg-teal-50/40"
              >
                <span
                  className="flex h-7 w-7 shrink-0 items-center justify-center rounded text-white"
                  style={{ backgroundColor: c.color }}
                >
                  <Plus size={14} />
                </span>
                <span className="min-w-0">
                  <span className="block truncate text-[13px] font-medium text-slate-700">
                    {c.title}
                  </span>
                  <span className="block text-[11px] text-slate-400">
                    {c.shape === 'round' ? `${c.widthFt}ft round` : `${c.widthFt}×${c.depthFt}ft`}
                    {c.chairs > 0 ? ` · ${c.chairs} ch` : ''}
                  </span>
                </span>
              </button>
            ))}
          </div>
          <Link
            to="/inventory"
            className="mt-3 block rounded-lg border border-dashed border-slate-300 px-2.5 py-2 text-center text-xs text-slate-500 hover:border-teal-300 hover:text-teal-600"
          >
            + Manage inventory
          </Link>
        </div>

        {/* Canvas */}
        <div className="min-w-0 flex-1 overflow-auto bg-slate-100 p-8" onMouseDown={() => setSelectedId(null)}>
          <svg
            ref={svgRef}
            width={canvasW}
            height={canvasH}
            className="bg-white shadow-lg"
            style={{ touchAction: 'none' }}
          >
            {/* Grid */}
            <GridLines
              widthFt={plan.roomWidthFt}
              depthFt={plan.roomDepthFt}
              px={pxPerFoot}
            />
            {/* Room border */}
            <rect x={0} y={0} width={canvasW} height={canvasH} fill="none" stroke="#334155" strokeWidth={2} />

            {plan.items.map((item) => {
              const pos = dragPos && dragPos.id === item.id ? dragPos : item
              return (
                <PlacedItemView
                  key={item.id}
                  item={item}
                  x={pos.x}
                  y={pos.y}
                  px={pxPerFoot}
                  selected={item.id === selectedId}
                  onMouseDown={(e) => startDrag(e, item)}
                />
              )
            })}
          </svg>
        </div>

        {/* Inspector */}
        <div className="w-64 shrink-0 overflow-y-auto border-l border-slate-200 bg-white p-4">
          {selected ? (
            <div className="space-y-4">
              <div>
                <div className="text-xs font-semibold uppercase tracking-wide text-slate-400">
                  Selected
                </div>
                <div className="mt-1 flex items-center gap-2">
                  <span className="h-4 w-4 rounded" style={{ backgroundColor: selected.color }} />
                  <span className="text-sm font-semibold text-slate-800">{selected.title}</span>
                </div>
                <div className="mt-1 text-xs text-slate-400">
                  {selected.shape === 'round'
                    ? `${selected.widthFt}ft round`
                    : `${selected.widthFt}ft × ${selected.depthFt}ft`}
                  {selected.chairs > 0 ? ` · ${selected.chairs} chairs` : ''}
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <Field label="X (ft)">
                  <TextInput
                    type="number"
                    step={0.5}
                    value={round1(selected.x)}
                    onChange={(e) => updatePlacedItem(plan.id, selected.id, { x: Number(e.target.value) })}
                  />
                </Field>
                <Field label="Y (ft)">
                  <TextInput
                    type="number"
                    step={0.5}
                    value={round1(selected.y)}
                    onChange={(e) => updatePlacedItem(plan.id, selected.id, { y: Number(e.target.value) })}
                  />
                </Field>
              </div>

              <Field label={`Rotation · ${selected.rotation}°`}>
                <input
                  type="range"
                  min={0}
                  max={345}
                  step={15}
                  value={selected.rotation}
                  onChange={(e) => updatePlacedItem(plan.id, selected.id, { rotation: Number(e.target.value) })}
                  className="w-full accent-teal-600"
                />
              </Field>

              <div className="flex flex-wrap gap-2">
                <Button
                  variant="secondary"
                  onClick={() =>
                    updatePlacedItem(plan.id, selected.id, { rotation: (selected.rotation + 90) % 360 })
                  }
                >
                  <RotateCw size={15} /> 90°
                </Button>
                <Button variant="secondary" onClick={() => duplicate(selected)}>
                  <Copy size={15} /> Copy
                </Button>
                <Button
                  variant="danger"
                  onClick={() => {
                    removePlacedItem(plan.id, selected.id)
                    setSelectedId(null)
                  }}
                >
                  <Trash2 size={15} /> Delete
                </Button>
              </div>

              <p className="rounded-lg bg-slate-50 p-2 text-[11px] leading-relaxed text-slate-400">
                Drag to move. Arrow keys nudge (⇧ = 1ft). Press R to rotate, Delete to remove.
              </p>
            </div>
          ) : (
            <div className="flex h-full flex-col items-center justify-center text-center text-sm text-slate-400">
              <Minus size={20} className="mb-2 opacity-40" />
              <p>Select an item to edit it, or click one in the palette to add it.</p>
              <p className="mt-3 text-xs">{plan.items.length} items placed</p>
            </div>
          )}
        </div>
      </div>
      )}
    </div>
  )
}

function round1(n: number) {
  return Math.round(n * 10) / 10
}

function DimInput({ value, onChange }: { value: number; onChange: (v: number) => void }) {
  return (
    <input
      type="number"
      min={4}
      value={value}
      onChange={(e) => onChange(Math.max(4, Number(e.target.value)))}
      className="w-14 rounded-md border border-slate-300 px-1.5 py-0.5 text-center text-sm text-slate-700 focus:border-teal-400 focus:outline-none"
    />
  )
}

function GridLines({ widthFt, depthFt, px }: { widthFt: number; depthFt: number; px: number }) {
  const lines = []
  for (let x = 1; x < widthFt; x++) {
    lines.push(
      <line
        key={`v${x}`}
        x1={x * px}
        y1={0}
        x2={x * px}
        y2={depthFt * px}
        stroke={x % 5 === 0 ? '#cbd5e1' : '#eef2f6'}
        strokeWidth={1}
      />,
    )
  }
  for (let y = 1; y < depthFt; y++) {
    lines.push(
      <line
        key={`h${y}`}
        x1={0}
        y1={y * px}
        x2={widthFt * px}
        y2={y * px}
        stroke={y % 5 === 0 ? '#cbd5e1' : '#eef2f6'}
        strokeWidth={1}
      />,
    )
  }
  return <g>{lines}</g>
}

function PlacedItemView({
  item,
  x,
  y,
  px,
  selected,
  onMouseDown,
}: {
  item: PlacedItem
  x: number
  y: number
  px: number
  selected: boolean
  onMouseDown: (e: React.MouseEvent) => void
}) {
  const w = item.widthFt * px
  const h = (item.shape === 'round' ? item.widthFt : item.depthFt) * px
  const chairs = chairPositions(item)
  const chairSize = CHAIR.size * px

  return (
    <g
      transform={`translate(${x * px} ${y * px}) rotate(${item.rotation})`}
      onMouseDown={onMouseDown}
      style={{ cursor: 'move' }}
    >
      {/* Chairs (drawn first so the table sits on top) */}
      {chairs.map((c, i) => (
        <rect
          key={i}
          x={c.x * px - chairSize / 2}
          y={c.y * px - chairSize / 2}
          width={chairSize}
          height={chairSize}
          rx={chairSize * 0.25}
          fill="#94a3b8"
          stroke="#64748b"
          strokeWidth={0.75}
        />
      ))}

      {/* Table / object */}
      {item.shape === 'round' ? (
        <circle
          r={w / 2}
          fill={item.color}
          stroke={selected ? '#0f172a' : 'rgba(0,0,0,0.15)'}
          strokeWidth={selected ? 2.5 : 1}
        />
      ) : (
        <rect
          x={-w / 2}
          y={-h / 2}
          width={w}
          height={h}
          rx={3}
          fill={item.color}
          stroke={selected ? '#0f172a' : 'rgba(0,0,0,0.15)'}
          strokeWidth={selected ? 2.5 : 1}
        />
      )}

      {/* Label (counter-rotated so it stays upright) */}
      {px >= 10 && (
        <text
          transform={`rotate(${-item.rotation})`}
          textAnchor="middle"
          dominantBaseline="middle"
          fontSize={Math.max(8, Math.min(12, px * 0.7))}
          fill="#ffffff"
          style={{ pointerEvents: 'none', fontWeight: 600 }}
        >
          {item.chairs > 0 ? item.chairs : ''}
        </text>
      )}
    </g>
  )
}
