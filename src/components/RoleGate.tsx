import { Heart, Lock, UserCog } from 'lucide-react'
import { useRoleStore } from '../store/useRole'
import { useStore } from '../store/useStore'
import { Button } from './ui'

/** True when this browser may only view, not edit. */
export function useReadOnly(): boolean {
  const locked = useStore((s) => s.locked)
  const role = useRoleStore((s) => s.role)
  return locked && role !== 'planner'
}

/** First-run overlay: choose whether this device belongs to the planner or the bride. */
export function RolePicker() {
  const role = useRoleStore((s) => s.role)
  const setRole = useRoleStore((s) => s.setRole)
  if (role !== null) return null

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
      <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <h2 className="text-lg font-bold text-slate-800">Welcome to AgencyEagle</h2>
        <p className="mt-1 text-sm text-slate-500">
          Who's using this device? This sets what you can do — the planner can lock
          the plan so it's view-only for the bride.
        </p>
        <div className="mt-5 grid grid-cols-2 gap-3">
          <button
            onClick={() => setRole('planner')}
            className="flex flex-col items-center gap-2 rounded-xl border-2 border-slate-200 p-5 text-slate-700 transition-colors hover:border-teal-500 hover:bg-teal-50"
          >
            <UserCog size={28} className="text-teal-700" />
            <span className="text-sm font-semibold">I'm the Planner</span>
          </button>
          <button
            onClick={() => setRole('bride')}
            className="flex flex-col items-center gap-2 rounded-xl border-2 border-slate-200 p-5 text-slate-700 transition-colors hover:border-pink-400 hover:bg-pink-50"
          >
            <Heart size={28} className="text-pink-500" />
            <span className="text-sm font-semibold">I'm the Bride</span>
          </button>
        </div>
        <p className="mt-4 text-center text-[11px] text-slate-400">
          You can switch later from the bottom of the sidebar.
        </p>
      </div>
    </div>
  )
}

/** Banner shown to a locked (read-only) bride, with the unlock-request flow. */
export function LockBanner() {
  const readOnly = useReadOnly()
  const requests = useStore((s) => s.unlockRequests)
  const requestUnlock = useStore((s) => s.requestUnlock)
  if (!readOnly) return null

  const alreadyRequested = requests.length > 0

  return (
    <div className="sticky top-0 z-40 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800">
      <span className="inline-flex items-center gap-1.5 font-medium">
        <Lock size={14} /> The planner has locked this plan — you're in view-only mode.
      </span>
      {alreadyRequested ? (
        <span className="text-amber-600">Unlock requested — waiting for the planner.</span>
      ) : (
        <Button
          variant="secondary"
          className="!py-0.5 text-xs"
          onClick={() => {
            const note = window.prompt('Add a note for the planner (optional):') ?? ''
            requestUnlock(note.trim())
          }}
        >
          Request unlock
        </Button>
      )}
    </div>
  )
}
