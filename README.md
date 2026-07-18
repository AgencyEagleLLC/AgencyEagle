# AgencyEagle — Event Planning Suite

A browser-based toolkit for event planners covering three connected workflows:

1. **Floor Plan Designer** — enter your room dimensions and lay out furniture to
   scale on a top-down, grid-snapped canvas.
2. **Inventory** — a reusable catalog of tables, bars, and equipment you drop onto
   floor plans (pre-seeded with common items; add your own).
3. **Vendors + Events & Timelines** — a vendor directory plus a 15-minute-increment
   timeline builder that exports PDFs and drafts social posts.

Everything is stored locally in the browser (no backend required) and can be
deployed as a static site.

## Getting started

```bash
pnpm install
pnpm dev          # start the dev server
pnpm build        # type-check + production build into dist/
pnpm preview      # preview the production build
```

Open the printed local URL (default http://localhost:5173).

## Modules

### Floor Plan Designer (`/floorplans`)
- Create a plan and set the room's width × depth in feet.
- The canvas renders the room to scale with a 1 ft / 5 ft grid.
- Click any inventory item in the left palette to drop it in; drag to move.
- Select an item to rotate (slider, 90° button, or `R`), duplicate, delete, or
  type exact X/Y coordinates. Arrow keys nudge (Shift = 1 ft steps).
- Round and rectangular tables render their chairs automatically.
- "Snap" locks movement to a 0.5 ft grid. Zoom with the toolbar controls.
- Plans can be linked to an event.

**2D / 3D views** — a toggle in the toolbar flips any plan between the flat 2D
editor (where all placing/editing happens) and an orbit-controllable **3D
walk-through** rendered from the exact same layout — ideal for presenting to
higher-end clients. Both views share one data model, so a plan built in 2D is
instantly viewable in 3D (drag to orbit, scroll to zoom, right-drag to pan).
The 3D bundle is code-split and only downloads when the 3D view is opened.

### Inventory (`/inventory`)
Pre-seeded with:
- 6 ft rectangle table · 8 chairs and · 6 chairs
- 60" round table · 6 chairs and · 7 chairs
- Bartender cart (10 ft), Gift table (4×2), Cake table (4 ft round),
  DJ station (10×2)

Add unlimited custom items with a title, shape (rectangle/round), dimensions,
chair count, and color.

### Vendors (`/vendors`)
- Categories are pre-filled (Bartender, DJ, Dessert, Food Cater 1/2, Live Music,
  and more) and you can add or remove your own.
- Each vendor records primary contact info, **emergency / owner contact**
  (including an owner cell), social media links, a free-form **payment
  preferences** notepad, and general notes.
- Search and filter by category.

### Events & Timelines (`/events`)
- Events are grouped by client, so one client (e.g. a bride) can have multiple
  events.
- Each event has a date, start/end time, and venue.
- Build a **timeline** by assigning vendors to blocks in 15-minute increments;
  blocks appear on a per-vendor lane grid and a chronological run-of-show.
- Export a **Master PDF** (whole event) or a **per-vendor PDF** (that vendor's
  blocks only, with their contact info) to share.
- Generate an editable **social media post** drafted from the timeline and the
  vendors' Instagram handles.

## Tech

React + TypeScript + Vite, Tailwind CSS v4, Zustand (with `localStorage`
persistence), jsPDF for exports. No server or database required.

## Notes & roadmap

- Data currently lives in the browser's local storage. A shared backend
  (multi-user, cloud sync) and direct vendor payment integration are natural
  next steps.
