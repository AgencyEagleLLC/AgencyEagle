import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import type {
  CatalogItem,
  EventRecord,
  FloorPlan,
  PlacedItem,
  TimelineEntry,
  Vendor,
} from '../types'
import { DEFAULT_CATALOG, DEFAULT_VENDOR_CATEGORIES } from '../data/defaults'

const uid = () =>
  typeof crypto !== 'undefined' && 'randomUUID' in crypto
    ? crypto.randomUUID()
    : Math.random().toString(36).slice(2) + Date.now().toString(36)

const now = () => new Date().toISOString()

interface StoreState {
  catalog: CatalogItem[]
  vendorCategories: string[]
  vendors: Vendor[]
  floorPlans: FloorPlan[]
  events: EventRecord[]

  // ----- Catalog -----
  addCatalogItem: (item: Omit<CatalogItem, 'id'>) => CatalogItem
  updateCatalogItem: (id: string, patch: Partial<CatalogItem>) => void
  removeCatalogItem: (id: string) => void

  // ----- Vendor categories -----
  addVendorCategory: (name: string) => void
  removeVendorCategory: (name: string) => void

  // ----- Vendors -----
  addVendor: (vendor?: Partial<Vendor>) => Vendor
  updateVendor: (id: string, patch: Partial<Vendor>) => void
  removeVendor: (id: string) => void

  // ----- Floor plans -----
  addFloorPlan: (plan?: Partial<FloorPlan>) => FloorPlan
  updateFloorPlan: (id: string, patch: Partial<FloorPlan>) => void
  removeFloorPlan: (id: string) => void
  addPlacedItem: (planId: string, item: Omit<PlacedItem, 'id'>) => void
  updatePlacedItem: (planId: string, itemId: string, patch: Partial<PlacedItem>) => void
  removePlacedItem: (planId: string, itemId: string) => void

  // ----- Events -----
  addEvent: (event?: Partial<EventRecord>) => EventRecord
  updateEvent: (id: string, patch: Partial<EventRecord>) => void
  removeEvent: (id: string) => void
  addTimelineEntry: (eventId: string, entry: Omit<TimelineEntry, 'id'>) => void
  updateTimelineEntry: (eventId: string, entryId: string, patch: Partial<TimelineEntry>) => void
  removeTimelineEntry: (eventId: string, entryId: string) => void
}

const emptyVendor = (): Omit<Vendor, 'id'> => ({
  businessName: '',
  category: DEFAULT_VENDOR_CATEGORIES[0],
  contactName: '',
  phone: '',
  email: '',
  website: '',
  address: '',
  ownerName: '',
  ownerCell: '',
  emergencyContact: '',
  social: { instagram: '', facebook: '', tiktok: '', other: '' },
  paymentNotes: '',
  notes: '',
})

const emptyEvent = (): Omit<EventRecord, 'id'> => ({
  clientName: '',
  eventName: '',
  date: '',
  startTime: '16:00',
  endTime: '23:00',
  venue: '',
  notes: '',
  timeline: [],
  floorPlanId: null,
})

export const useStore = create<StoreState>()(
  persist(
    (set) => ({
      catalog: DEFAULT_CATALOG,
      vendorCategories: DEFAULT_VENDOR_CATEGORIES,
      vendors: [],
      floorPlans: [],
      events: [],

      // ----- Catalog -----
      addCatalogItem: (item) => {
        const created: CatalogItem = { ...item, id: uid() }
        set((s) => ({ catalog: [...s.catalog, created] }))
        return created
      },
      updateCatalogItem: (id, patch) =>
        set((s) => ({
          catalog: s.catalog.map((c) => (c.id === id ? { ...c, ...patch } : c)),
        })),
      removeCatalogItem: (id) =>
        set((s) => ({ catalog: s.catalog.filter((c) => c.id !== id) })),

      // ----- Vendor categories -----
      addVendorCategory: (name) => {
        const trimmed = name.trim()
        if (!trimmed) return
        set((s) =>
          s.vendorCategories.some((c) => c.toLowerCase() === trimmed.toLowerCase())
            ? s
            : { vendorCategories: [...s.vendorCategories, trimmed] },
        )
      },
      removeVendorCategory: (name) =>
        set((s) => ({ vendorCategories: s.vendorCategories.filter((c) => c !== name) })),

      // ----- Vendors -----
      addVendor: (vendor) => {
        const created: Vendor = { ...emptyVendor(), ...vendor, id: uid() }
        set((s) => ({ vendors: [...s.vendors, created] }))
        return created
      },
      updateVendor: (id, patch) =>
        set((s) => ({
          vendors: s.vendors.map((v) => (v.id === id ? { ...v, ...patch } : v)),
        })),
      removeVendor: (id) =>
        set((s) => ({
          vendors: s.vendors.filter((v) => v.id !== id),
          // Cascade: drop timeline entries that referenced this vendor.
          events: s.events.map((e) => ({
            ...e,
            timeline: e.timeline.filter((t) => t.vendorId !== id),
          })),
        })),

      // ----- Floor plans -----
      addFloorPlan: (plan) => {
        const created: FloorPlan = {
          id: uid(),
          name: plan?.name ?? 'Untitled Plan',
          eventId: plan?.eventId ?? null,
          roomWidthFt: plan?.roomWidthFt ?? 40,
          roomDepthFt: plan?.roomDepthFt ?? 30,
          items: plan?.items ?? [],
          createdAt: now(),
          updatedAt: now(),
        }
        set((s) => ({ floorPlans: [...s.floorPlans, created] }))
        return created
      },
      updateFloorPlan: (id, patch) =>
        set((s) => ({
          floorPlans: s.floorPlans.map((p) =>
            p.id === id ? { ...p, ...patch, updatedAt: now() } : p,
          ),
        })),
      removeFloorPlan: (id) =>
        set((s) => ({
          floorPlans: s.floorPlans.filter((p) => p.id !== id),
          events: s.events.map((e) =>
            e.floorPlanId === id ? { ...e, floorPlanId: null } : e,
          ),
        })),
      addPlacedItem: (planId, item) =>
        set((s) => ({
          floorPlans: s.floorPlans.map((p) =>
            p.id === planId
              ? { ...p, items: [...p.items, { ...item, id: uid() }], updatedAt: now() }
              : p,
          ),
        })),
      updatePlacedItem: (planId, itemId, patch) =>
        set((s) => ({
          floorPlans: s.floorPlans.map((p) =>
            p.id === planId
              ? {
                  ...p,
                  items: p.items.map((it) =>
                    it.id === itemId ? { ...it, ...patch } : it,
                  ),
                  updatedAt: now(),
                }
              : p,
          ),
        })),
      removePlacedItem: (planId, itemId) =>
        set((s) => ({
          floorPlans: s.floorPlans.map((p) =>
            p.id === planId
              ? { ...p, items: p.items.filter((it) => it.id !== itemId), updatedAt: now() }
              : p,
          ),
        })),

      // ----- Events -----
      addEvent: (event) => {
        const created: EventRecord = { ...emptyEvent(), ...event, id: uid() }
        set((s) => ({ events: [...s.events, created] }))
        return created
      },
      updateEvent: (id, patch) =>
        set((s) => ({
          events: s.events.map((e) => (e.id === id ? { ...e, ...patch } : e)),
        })),
      removeEvent: (id) =>
        set((s) => ({
          events: s.events.filter((e) => e.id !== id),
          floorPlans: s.floorPlans.map((p) =>
            p.eventId === id ? { ...p, eventId: null } : p,
          ),
        })),
      addTimelineEntry: (eventId, entry) =>
        set((s) => ({
          events: s.events.map((e) =>
            e.id === eventId
              ? { ...e, timeline: [...e.timeline, { ...entry, id: uid() }] }
              : e,
          ),
        })),
      updateTimelineEntry: (eventId, entryId, patch) =>
        set((s) => ({
          events: s.events.map((e) =>
            e.id === eventId
              ? {
                  ...e,
                  timeline: e.timeline.map((t) =>
                    t.id === entryId ? { ...t, ...patch } : t,
                  ),
                }
              : e,
          ),
        })),
      removeTimelineEntry: (eventId, entryId) =>
        set((s) => ({
          events: s.events.map((e) =>
            e.id === eventId
              ? { ...e, timeline: e.timeline.filter((t) => t.id !== entryId) }
              : e,
          ),
        })),
    }),
    {
      name: 'agencyeagle-store-v1',
      version: 1,
      // Merge seeded defaults back in if a returning user has an empty catalog
      // or category list (e.g. after clearing them all).
      merge: (persisted, current) => {
        const p = (persisted ?? {}) as Partial<StoreState>
        return {
          ...current,
          ...p,
          catalog: p.catalog?.length ? p.catalog : current.catalog,
          vendorCategories: p.vendorCategories?.length
            ? p.vendorCategories
            : current.vendorCategories,
        }
      },
    },
  ),
)
