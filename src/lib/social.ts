import type { EventRecord, Vendor } from '../types'
import { formatDateLong, formatMin } from './time'

/** Extract a plausible @handle from a full social URL or raw handle string. */
export function handleFromSocial(value: string): string {
  const v = value.trim()
  if (!v) return ''
  if (v.startsWith('@')) return v
  const cleaned = v
    .replace(/^https?:\/\//i, '')
    .replace(/^www\./i, '')
    .replace(/\/$/, '')
  const parts = cleaned.split('/')
  const last = parts[parts.length - 1]
  if (last && last !== cleaned) return '@' + last
  return '@' + cleaned
}

/**
 * Draft a social-media post from an event's timeline, tagging every vendor
 * that has an Instagram handle. Intended as a starting point to edit.
 */
export function generateSocialPost(event: EventRecord, vendors: Vendor[]): string {
  const byId = new Map(vendors.map((v) => [v.id, v]))
  const usedVendorIds = Array.from(new Set(event.timeline.map((t) => t.vendorId)))
  const tagged = usedVendorIds
    .map((id) => byId.get(id))
    .filter((v): v is Vendor => Boolean(v))

  const lines: string[] = []
  const client = event.clientName || 'our couple'
  lines.push(`✨ What a day! Celebrating ${client}${event.eventName ? ` — ${event.eventName}` : ''} on ${formatDateLong(event.date)}.`)
  if (event.venue) lines.push(`📍 ${event.venue}`)
  lines.push('')

  const sorted = [...event.timeline].sort((a, b) => a.startMin - b.startMin)
  if (sorted.length) {
    lines.push('Here is how the celebration flowed:')
    for (const t of sorted) {
      const v = byId.get(t.vendorId)
      const who = v ? v.businessName || v.category : 'Vendor'
      lines.push(`• ${formatMin(t.startMin)} — ${t.label || who}`)
    }
    lines.push('')
  }

  if (tagged.length) {
    lines.push('Endless thanks to our incredible vendor team:')
    for (const v of tagged) {
      const handle = handleFromSocial(v.social.instagram)
      lines.push(`${v.category}: ${v.businessName}${handle ? ` ${handle}` : ''}`)
    }
    lines.push('')
    const tags = tagged
      .map((v) => handleFromSocial(v.social.instagram))
      .filter(Boolean)
      .join(' ')
    if (tags) lines.push(tags)
  }

  lines.push('')
  lines.push('#wedding #eventplanning #AgencyEagle')
  return lines.join('\n')
}
