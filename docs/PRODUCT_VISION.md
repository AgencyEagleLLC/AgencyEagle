# AgencyEagle — Commercial Vision (living doc)

Captured 2026-07-18 from the owner's direction. Nothing here is final; it
records intent so product and architecture decisions can point the same way.

## The idea

Sell AgencyEagle to other venues/planners as a subscription, priced modularly:

- **Base package — ~$99/month**: the core a venue needs day one.
- **Add-on modules** purchased separately, e.g.:
  - **Event outlines/timelines** — vendor scheduling, run-of-show, PDF exports.
  - **3D layouts** — the walk-through view on top of 2D floor plans.
  - **Vendor add-ons** — vendor directory extras: self-onboarding invites,
    client-portal vendor browsing, social-post generation.

## How today's code maps to modules

The app is already split along the same seams, which makes gating cheap:

| Module | Existing surface |
| --- | --- |
| Base | 2D floor plans, inventory catalog, vendor contact book |
| 3D layouts | `FloorPlan3D` (already lazy-loaded as its own bundle — natural paywall seam) |
| Event outlines | Events & Timelines pages, PDF export (`lib/pdf.ts`), social posts |
| Vendor add-ons | vendor categories/socials/payment notes; future invite + approval flow |
| Collaboration | planner/bride roles + lock (foundation for per-venue multi-user) |

## What selling it requires (sequencing)

1. **Backend + accounts** — multi-tenant workspaces (one per venue), cloud data
   instead of per-browser localStorage. Prerequisite for everything below.
2. **Entitlements** — a per-workspace `features` set checked in the UI
   (e.g. `has('3d')`, `has('timelines')`). Keep checks coarse — module-level,
   not per-button.
3. **Billing** — Stripe subscription with add-on line items mapped 1:1 to the
   entitlement flags.
4. **White-labeling (later)** — venue name/logo/colors per workspace.

## Notes

- The planner/bride role split and the lock system were deliberately built with
  the role stored per-device and the lock stored in shared data, so they will
  survive the move to a real backend unchanged.
- Cedar Pond Farms' own admin portal (separate codebase) overlaps on vendors and
  client portals; if AgencyEagle becomes the sellable product, decide which
  system is the source of truth for vendors before building more there.
