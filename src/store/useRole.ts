import { create } from 'zustand'
import { persist } from 'zustand/middleware'

export type AppRole = 'planner' | 'bride'

interface RoleState {
  /** Who is using this browser/device. null = not chosen yet (first run). */
  role: AppRole | null
  setRole: (role: AppRole | null) => void
}

/**
 * Persisted separately from the main data store: the role belongs to the
 * device, while the lock + plan data are the shared record.
 */
export const useRoleStore = create<RoleState>()(
  persist(
    (set) => ({
      role: null,
      setRole: (role) => set({ role }),
    }),
    { name: 'agencyeagle-role-v1' },
  )
)
