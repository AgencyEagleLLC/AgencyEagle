import type { PlacedItem } from '../types'

/** Chair geometry, in feet. Shared by the 2D and 3D renderers. */
export const CHAIR = { size: 1.4, gap: 0.35 }

export interface ChairPos {
  /** Feet, relative to the table center, before the table's rotation is applied. */
  x: number
  y: number
}

/**
 * Positions of the chairs around an item, in feet relative to its center.
 * For the 3D view, treat `y` as the Z axis.
 */
export function chairPositions(
  item: Pick<PlacedItem, 'shape' | 'widthFt' | 'depthFt' | 'chairs'>,
): ChairPos[] {
  const n = item.chairs
  if (n <= 0) return []
  const out: ChairPos[] = []
  if (item.shape === 'round') {
    const r = item.widthFt / 2 + CHAIR.gap + CHAIR.size / 2
    for (let i = 0; i < n; i++) {
      const a = (i / n) * Math.PI * 2 - Math.PI / 2
      out.push({ x: Math.cos(a) * r, y: Math.sin(a) * r })
    }
  } else {
    // Distribute along the two long (top/bottom) edges.
    const top = Math.ceil(n / 2)
    const bottom = n - top
    const yOff = item.depthFt / 2 + CHAIR.gap + CHAIR.size / 2
    const place = (count: number, sign: number) => {
      for (let i = 0; i < count; i++) {
        const frac = (i + 1) / (count + 1)
        out.push({ x: (frac - 0.5) * item.widthFt, y: sign * yOff })
      }
    }
    place(top, -1)
    place(bottom, 1)
  }
  return out
}

/**
 * Height of an object in feet, used by the 3D renderer. Seated furniture sits
 * at table height; free-standing equipment (bars, DJ booths) stands taller.
 */
export function objectHeightFt(item: Pick<PlacedItem, 'title' | 'chairs' | 'shape'>): number {
  const t = item.title.toLowerCase()
  if (t.includes('bar') || t.includes('cart')) return 3.6
  if (t.includes('dj')) return 3.2
  if (t.includes('gift')) return 2.8
  if (t.includes('cake')) return 2.6
  return 2.5 // dining / round / rectangle tables
}
