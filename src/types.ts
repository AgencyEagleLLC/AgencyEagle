// ---------- Inventory / Catalog ----------

export type ItemShape = 'rect' | 'round'

/** A reusable definition of a piece of event furniture / equipment. */
export interface CatalogItem {
  id: string
  title: string
  shape: ItemShape
  /** Width in feet. For round items this is the diameter. */
  widthFt: number
  /** Depth in feet. Ignored for round items (kept equal to width). */
  depthFt: number
  /** Number of chairs drawn around the table (0 = none). */
  chairs: number
  color: string
  /** Seeded defaults are flagged so we can distinguish them in the UI. */
  isDefault?: boolean
}

/** An instance of a catalog item placed onto a floor plan. */
export interface PlacedItem {
  id: string
  catalogId: string
  // Geometry is snapshotted at placement time so edits to the catalog
  // never silently mutate an already-designed plan.
  title: string
  shape: ItemShape
  widthFt: number
  depthFt: number
  chairs: number
  color: string
  /** Center position in feet, relative to the room's top-left corner. */
  x: number
  y: number
  /** Rotation in degrees, clockwise. */
  rotation: number
}

export interface FloorPlan {
  id: string
  name: string
  eventId: string | null
  roomWidthFt: number
  roomDepthFt: number
  items: PlacedItem[]
  createdAt: string
  updatedAt: string
}

// ---------- Vendors ----------

export interface VendorSocial {
  instagram: string
  facebook: string
  tiktok: string
  other: string
}

export interface Vendor {
  id: string
  businessName: string
  category: string
  contactName: string
  phone: string
  email: string
  website: string
  address: string
  // Emergency / owner details
  ownerName: string
  ownerCell: string
  emergencyContact: string
  social: VendorSocial
  /** Free-form notepad for how the vendor prefers to be paid. */
  paymentNotes: string
  notes: string
}

// ---------- Events & Timeline ----------

export interface TimelineEntry {
  id: string
  vendorId: string
  /** What the vendor is doing in this block, e.g. "Bar service" or "First dance". */
  label: string
  /** Minutes from midnight (0-1439), always a multiple of 15. */
  startMin: number
  endMin: number
  notes: string
}

export interface EventRecord {
  id: string
  /** The client — e.g. the bride. One client may have multiple events. */
  clientName: string
  /** Name of this specific event, e.g. "Ceremony & Reception". */
  eventName: string
  date: string
  startTime: string
  endTime: string
  venue: string
  notes: string
  timeline: TimelineEntry[]
  floorPlanId: string | null
}
