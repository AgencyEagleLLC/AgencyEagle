/** Convert "HH:MM" (24h) into minutes from midnight. Returns null if invalid. */
export function parseTime(value: string): number | null {
  const m = /^(\d{1,2}):(\d{2})$/.exec(value.trim())
  if (!m) return null
  const h = Number(m[1])
  const min = Number(m[2])
  if (h < 0 || h > 23 || min < 0 || min > 59) return null
  return h * 60 + min
}

/** Minutes from midnight -> "4:15 PM". */
export function formatMin(total: number): string {
  const norm = ((total % 1440) + 1440) % 1440
  const h24 = Math.floor(norm / 60)
  const m = norm % 60
  const period = h24 >= 12 ? 'PM' : 'AM'
  const h12 = h24 % 12 === 0 ? 12 : h24 % 12
  return `${h12}:${String(m).padStart(2, '0')} ${period}`
}

/** Minutes -> "16:15" for <input type="time"> values. */
export function toTimeInput(total: number): string {
  const norm = ((total % 1440) + 1440) % 1440
  const h = Math.floor(norm / 60)
  const m = norm % 60
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
}

/**
 * Build an inclusive list of slot boundaries between start and end.
 * Handles events that run past midnight by adding 24h to the end.
 */
export function buildSlots(startMin: number, endMin: number, step = 15): number[] {
  let end = endMin
  if (end <= startMin) end += 1440
  const slots: number[] = []
  for (let t = startMin; t <= end; t += step) slots.push(t)
  return slots
}

export function formatDateLong(iso: string): string {
  if (!iso) return 'Date TBD'
  const d = new Date(iso + 'T00:00:00')
  if (Number.isNaN(d.getTime())) return iso
  return d.toLocaleDateString(undefined, {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}
