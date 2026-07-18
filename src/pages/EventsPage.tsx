import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { CalendarClock, ChevronRight, MapPin, Plus, Trash2, User } from 'lucide-react'
import { useStore } from '../store/useStore'
import type { EventRecord } from '../types'
import { Button, EmptyState, Field, Modal, TextInput } from '../components/ui'
import { formatDateLong, formatMin, parseTime } from '../lib/time'

export default function EventsPage() {
  const events = useStore((s) => s.events)
  const addEvent = useStore((s) => s.addEvent)
  const removeEvent = useStore((s) => s.removeEvent)
  const navigate = useNavigate()

  const [open, setOpen] = useState(false)
  const [draft, setDraft] = useState({
    clientName: '',
    eventName: '',
    date: '',
    startTime: '16:00',
    endTime: '23:00',
    venue: '',
  })

  const grouped = useMemo(() => {
    const map = new Map<string, EventRecord[]>()
    for (const e of events) {
      const key = e.clientName.trim() || 'Unassigned'
      if (!map.has(key)) map.set(key, [])
      map.get(key)!.push(e)
    }
    return Array.from(map.entries()).sort((a, b) => a[0].localeCompare(b[0]))
  }, [events])

  const create = () => {
    if (!draft.clientName.trim() && !draft.eventName.trim()) return
    const ev = addEvent(draft)
    setOpen(false)
    setDraft({ clientName: '', eventName: '', date: '', startTime: '16:00', endTime: '23:00', venue: '' })
    navigate(`/events/${ev.id}`)
  }

  return (
    <div className="mx-auto max-w-5xl px-6 py-8">
      <header className="mb-6 flex items-end justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-800">Events &amp; Timelines</h1>
          <p className="mt-1 text-sm text-slate-500">
            Grouped by client — one client can have multiple events.
          </p>
        </div>
        <Button onClick={() => setOpen(true)}>
          <Plus size={16} /> New Event
        </Button>
      </header>

      {events.length === 0 ? (
        <EmptyState
          icon={<CalendarClock size={40} />}
          title="No events yet"
          message="Create an event to start building a vendor timeline."
          action={
            <Button onClick={() => setOpen(true)}>
              <Plus size={16} /> New Event
            </Button>
          }
        />
      ) : (
        <div className="space-y-6">
          {grouped.map(([client, list]) => (
            <section key={client}>
              <div className="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-500">
                <User size={15} /> {client}
              </div>
              <div className="space-y-2">
                {list.map((ev) => (
                  <button
                    key={ev.id}
                    onClick={() => navigate(`/events/${ev.id}`)}
                    className="flex w-full items-center justify-between rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm transition-colors hover:border-teal-300 hover:bg-teal-50/30"
                  >
                    <div>
                      <div className="font-semibold text-slate-800">
                        {ev.eventName || 'Untitled Event'}
                      </div>
                      <div className="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                        <span className="flex items-center gap-1">
                          <CalendarClock size={13} /> {formatDateLong(ev.date)}
                        </span>
                        <span>
                          {formatMin(parseTime(ev.startTime) ?? 0)} – {formatMin(parseTime(ev.endTime) ?? 0)}
                        </span>
                        {ev.venue && (
                          <span className="flex items-center gap-1">
                            <MapPin size={13} /> {ev.venue}
                          </span>
                        )}
                        <span className="rounded-full bg-slate-100 px-2 py-0.5">
                          {ev.timeline.length} timeline blocks
                        </span>
                      </div>
                    </div>
                    <div className="flex items-center gap-1">
                      <span
                        role="button"
                        tabIndex={0}
                        onClick={(e) => {
                          e.stopPropagation()
                          if (confirm('Delete this event?')) removeEvent(ev.id)
                        }}
                        className="rounded-lg p-1.5 text-slate-300 hover:bg-red-50 hover:text-red-500"
                      >
                        <Trash2 size={16} />
                      </span>
                      <ChevronRight size={18} className="text-slate-300" />
                    </div>
                  </button>
                ))}
              </div>
            </section>
          ))}
        </div>
      )}

      <Modal open={open} onClose={() => setOpen(false)} title="New Event">
        <div className="space-y-4">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Field label="Client / Bride">
              <TextInput
                autoFocus
                value={draft.clientName}
                placeholder="e.g. Jordan & Alex"
                onChange={(e) => setDraft({ ...draft, clientName: e.target.value })}
              />
            </Field>
            <Field label="Event Name">
              <TextInput
                value={draft.eventName}
                placeholder="e.g. Reception"
                onChange={(e) => setDraft({ ...draft, eventName: e.target.value })}
              />
            </Field>
          </div>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <Field label="Date">
              <TextInput type="date" value={draft.date} onChange={(e) => setDraft({ ...draft, date: e.target.value })} />
            </Field>
            <Field label="Start">
              <TextInput type="time" value={draft.startTime} onChange={(e) => setDraft({ ...draft, startTime: e.target.value })} />
            </Field>
            <Field label="End">
              <TextInput type="time" value={draft.endTime} onChange={(e) => setDraft({ ...draft, endTime: e.target.value })} />
            </Field>
          </div>
          <Field label="Venue">
            <TextInput value={draft.venue} onChange={(e) => setDraft({ ...draft, venue: e.target.value })} />
          </Field>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="secondary" onClick={() => setOpen(false)}>
              Cancel
            </Button>
            <Button onClick={create}>Create &amp; Open Timeline</Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
