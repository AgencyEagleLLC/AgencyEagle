import { jsPDF } from 'jspdf'
import type { EventRecord, TimelineEntry, Vendor } from '../types'
import { formatDateLong, formatMin } from './time'

const MARGIN = 48
const TEAL: [number, number, number] = [15, 118, 110]
const GRAY: [number, number, number] = [107, 114, 128]
const LIGHT: [number, number, number] = [237, 242, 244]

interface Column {
  header: string
  width: number
  get: (row: RenderRow) => string
}

interface RenderRow {
  entry: TimelineEntry
  vendor?: Vendor
}

function drawHeader(doc: jsPDF, event: EventRecord, subtitle: string): number {
  const pageWidth = doc.internal.pageSize.getWidth()
  doc.setFillColor(...TEAL)
  doc.rect(0, 0, pageWidth, 96, 'F')

  doc.setTextColor(255, 255, 255)
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(20)
  doc.text('AgencyEagle', MARGIN, 40)

  doc.setFontSize(13)
  doc.setFont('helvetica', 'normal')
  doc.text(subtitle, MARGIN, 62)

  doc.setFontSize(10)
  const right = `${event.clientName || 'Client'}${event.eventName ? ' — ' + event.eventName : ''}`
  doc.text(right, MARGIN, 80)

  // Event meta block below the banner
  let y = 128
  doc.setTextColor(...GRAY)
  doc.setFontSize(11)
  doc.setFont('helvetica', 'bold')
  doc.text(event.eventName || 'Event', MARGIN, y)
  y += 18
  doc.setFont('helvetica', 'normal')
  doc.text(formatDateLong(event.date), MARGIN, y)
  y += 15
  const timeLine = `${formatMin(hhmmToMin(event.startTime))} – ${formatMin(hhmmToMin(event.endTime))}`
  doc.text(timeLine, MARGIN, y)
  if (event.venue) {
    y += 15
    doc.text(event.venue, MARGIN, y)
  }
  return y + 26
}

function hhmmToMin(v: string): number {
  const m = /^(\d{1,2}):(\d{2})$/.exec((v || '').trim())
  if (!m) return 0
  return Number(m[1]) * 60 + Number(m[2])
}

function drawTable(doc: jsPDF, columns: Column[], rows: RenderRow[], startY: number): number {
  const pageWidth = doc.internal.pageSize.getWidth()
  const pageHeight = doc.internal.pageSize.getHeight()
  const tableWidth = pageWidth - MARGIN * 2
  const totalUnits = columns.reduce((a, c) => a + c.width, 0)
  const colX: number[] = []
  let x = MARGIN
  for (const c of columns) {
    colX.push(x)
    x += (c.width / totalUnits) * tableWidth
  }
  const colW = columns.map((c) => (c.width / totalUnits) * tableWidth)

  let y = startY
  const rowHeight = 26

  const drawHeaderRow = () => {
    doc.setFillColor(...TEAL)
    doc.rect(MARGIN, y, tableWidth, rowHeight, 'F')
    doc.setTextColor(255, 255, 255)
    doc.setFont('helvetica', 'bold')
    doc.setFontSize(10)
    columns.forEach((c, i) => doc.text(c.header, colX[i] + 6, y + 17))
    y += rowHeight
  }

  drawHeaderRow()

  doc.setFont('helvetica', 'normal')
  doc.setFontSize(10)
  rows.forEach((row, idx) => {
    if (y + rowHeight > pageHeight - MARGIN) {
      doc.addPage()
      y = MARGIN
      drawHeaderRow()
      doc.setFont('helvetica', 'normal')
      doc.setFontSize(10)
    }
    if (idx % 2 === 1) {
      doc.setFillColor(...LIGHT)
      doc.rect(MARGIN, y, tableWidth, rowHeight, 'F')
    }
    doc.setTextColor(31, 41, 55)
    columns.forEach((c, i) => {
      const text = c.get(row)
      const lines = doc.splitTextToSize(text, colW[i] - 12)
      doc.text(lines[0] ?? '', colX[i] + 6, y + 17)
    })
    y += rowHeight
  })

  return y
}

function sortByTime(timeline: TimelineEntry[]): TimelineEntry[] {
  return [...timeline].sort((a, b) => a.startMin - b.startMin || a.endMin - b.endMin)
}

function slug(s: string): string {
  return (s || 'event').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')
}

/** Full event schedule across all vendors. */
export function exportGlobalTimelinePDF(event: EventRecord, vendors: Vendor[]): void {
  const doc = new jsPDF({ unit: 'pt', format: 'letter' })
  const byId = new Map(vendors.map((v) => [v.id, v]))
  const startY = drawHeader(doc, event, 'Master Event Timeline')

  const rows: RenderRow[] = sortByTime(event.timeline).map((entry) => ({
    entry,
    vendor: byId.get(entry.vendorId),
  }))

  const columns: Column[] = [
    { header: 'Start', width: 12, get: (r) => formatMin(r.entry.startMin) },
    { header: 'End', width: 12, get: (r) => formatMin(r.entry.endMin) },
    { header: 'Vendor', width: 22, get: (r) => r.vendor?.businessName || '—' },
    { header: 'Category', width: 16, get: (r) => r.vendor?.category || '—' },
    { header: 'Activity / Notes', width: 38, get: (r) => [r.entry.label, r.entry.notes].filter(Boolean).join(' — ') },
  ]

  if (rows.length === 0) {
    doc.setTextColor(...GRAY)
    doc.text('No timeline entries yet.', MARGIN, startY + 10)
  } else {
    drawTable(doc, columns, rows, startY)
  }

  doc.save(`${slug(event.clientName)}-${slug(event.eventName)}-master-timeline.pdf`)
}

/** Schedule for a single vendor — their blocks only. */
export function exportVendorTimelinePDF(
  event: EventRecord,
  vendors: Vendor[],
  vendorId: string,
): void {
  const doc = new jsPDF({ unit: 'pt', format: 'letter' })
  const vendor = vendors.find((v) => v.id === vendorId)
  const startY = drawHeader(
    doc,
    event,
    `Vendor Schedule — ${vendor?.businessName || vendor?.category || 'Vendor'}`,
  )

  let y = startY
  if (vendor) {
    doc.setTextColor(...GRAY)
    doc.setFontSize(10)
    const contact = [
      vendor.contactName && `Contact: ${vendor.contactName}`,
      vendor.phone && `Phone: ${vendor.phone}`,
      vendor.ownerCell && `Owner cell: ${vendor.ownerCell}`,
    ]
      .filter(Boolean)
      .join('    ')
    if (contact) {
      doc.text(contact, MARGIN, y)
      y += 22
    }
  }

  const rows: RenderRow[] = sortByTime(
    event.timeline.filter((t) => t.vendorId === vendorId),
  ).map((entry) => ({ entry, vendor }))

  const columns: Column[] = [
    { header: 'Start', width: 16, get: (r) => formatMin(r.entry.startMin) },
    { header: 'End', width: 16, get: (r) => formatMin(r.entry.endMin) },
    { header: 'Activity', width: 30, get: (r) => r.entry.label || '—' },
    { header: 'Notes', width: 38, get: (r) => r.entry.notes || '' },
  ]

  if (rows.length === 0) {
    doc.setTextColor(...GRAY)
    doc.text('This vendor has no scheduled blocks yet.', MARGIN, y + 10)
  } else {
    drawTable(doc, columns, rows, y)
  }

  doc.save(
    `${slug(event.clientName)}-${slug(vendor?.businessName || 'vendor')}-schedule.pdf`,
  )
}
