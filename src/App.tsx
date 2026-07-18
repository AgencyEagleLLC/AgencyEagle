import { NavLink, Navigate, Route, Routes } from 'react-router-dom'
import {
  BellRing,
  CalendarClock,
  Heart,
  LayoutGrid,
  Lock,
  LockOpen,
  Package,
  UserCog,
  Users,
} from 'lucide-react'
import type { ReactNode } from 'react'
import FloorPlansPage from './pages/FloorPlansPage'
import FloorPlanEditorPage from './pages/FloorPlanEditorPage'
import InventoryPage from './pages/InventoryPage'
import VendorsPage from './pages/VendorsPage'
import EventsPage from './pages/EventsPage'
import EventTimelinePage from './pages/EventTimelinePage'
import { LockBanner, RolePicker } from './components/RoleGate'
import { useRoleStore } from './store/useRole'
import { useStore } from './store/useStore'

interface NavItem {
  to: string
  label: string
  icon: ReactNode
}

const NAV: NavItem[] = [
  { to: '/floorplans', label: 'Floor Plans', icon: <LayoutGrid size={18} /> },
  { to: '/inventory', label: 'Inventory', icon: <Package size={18} /> },
  { to: '/vendors', label: 'Vendors', icon: <Users size={18} /> },
  { to: '/events', label: 'Events & Timelines', icon: <CalendarClock size={18} /> },
]

function RoleBar() {
  const role = useRoleStore((s) => s.role)
  const setRole = useRoleStore((s) => s.setRole)
  const locked = useStore((s) => s.locked)
  const lockPin = useStore((s) => s.lockPin)
  const unlockRequests = useStore((s) => s.unlockRequests)
  const setLocked = useStore((s) => s.setLocked)
  const dismissUnlockRequests = useStore((s) => s.dismissUnlockRequests)

  const switchRole = () => {
    if (role === 'bride') {
      // Switching to planner is what the lock protects — require the PIN if one is set.
      if (lockPin) {
        const pin = window.prompt('Enter the planner PIN:')
        if (pin !== lockPin) {
          if (pin !== null) window.alert('Wrong PIN.')
          return
        }
      }
      setRole('planner')
    } else {
      setRole('bride')
    }
  }

  const toggleLock = () => {
    if (locked) {
      setLocked(false)
    } else {
      const pin =
        window.prompt('Set a PIN the bride would need to unlock or switch roles (optional — leave blank for none):') ?? ''
      setLocked(true, pin.trim() || null)
    }
  }

  return (
    <div className="border-t border-slate-200 px-4 py-3">
      <div className="flex items-center justify-between">
        <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600">
          {role === 'planner' ? (
            <>
              <UserCog size={14} className="text-teal-700" /> Planner
            </>
          ) : (
            <>
              <Heart size={14} className="text-pink-500" /> Bride
            </>
          )}
        </span>
        <button
          onClick={switchRole}
          className="text-[11px] text-slate-400 underline hover:text-slate-600"
        >
          Switch
        </button>
      </div>

      {role === 'planner' && (
        <>
          <button
            onClick={toggleLock}
            className={`mt-2 flex w-full items-center justify-center gap-1.5 rounded-lg border px-2 py-1.5 text-xs font-medium transition-colors ${
              locked
                ? 'border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100'
                : 'border-slate-300 text-slate-600 hover:bg-slate-50'
            }`}
          >
            {locked ? (
              <>
                <Lock size={13} /> Locked — bride is view-only
              </>
            ) : (
              <>
                <LockOpen size={13} /> Lock plan for bride
              </>
            )}
          </button>

          {unlockRequests.length > 0 && (
            <div className="mt-2 rounded-lg border border-pink-200 bg-pink-50 p-2">
              <div className="flex items-center gap-1.5 text-xs font-semibold text-pink-700">
                <BellRing size={13} /> Bride requested unlock
              </div>
              {unlockRequests[unlockRequests.length - 1].note && (
                <p className="mt-1 text-[11px] italic text-pink-600">
                  “{unlockRequests[unlockRequests.length - 1].note}”
                </p>
              )}
              <div className="mt-2 flex gap-1.5">
                <button
                  onClick={() => setLocked(false)}
                  className="flex-1 rounded-md bg-teal-700 px-2 py-1 text-[11px] font-semibold text-white hover:bg-teal-800"
                >
                  Unlock
                </button>
                <button
                  onClick={dismissUnlockRequests}
                  className="flex-1 rounded-md border border-slate-300 bg-white px-2 py-1 text-[11px] font-medium text-slate-600 hover:bg-slate-50"
                >
                  Dismiss
                </button>
              </div>
            </div>
          )}
        </>
      )}

      {role === 'bride' && locked && (
        <p className="mt-2 flex items-center gap-1.5 text-[11px] text-amber-600">
          <Lock size={12} /> View-only until the planner unlocks.
        </p>
      )}
    </div>
  )
}

function Sidebar() {
  return (
    <aside className="flex w-56 shrink-0 flex-col border-r border-slate-200 bg-white">
      <div className="flex items-center gap-2 px-5 py-5">
        <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-teal-700 text-white">
          <svg viewBox="0 0 32 32" className="h-6 w-6">
            <path
              d="M16 6l3.2 6.5L26 13l-5 4.6L22.4 25 16 21.2 9.6 25 11 17.6 6 13l6.8-.5z"
              fill="currentColor"
            />
          </svg>
        </div>
        <div>
          <div className="text-sm font-bold leading-tight text-slate-800">AgencyEagle</div>
          <div className="text-[11px] leading-tight text-slate-400">Event Suite</div>
        </div>
      </div>
      <nav className="flex flex-1 flex-col gap-1 px-3">
        {NAV.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            className={({ isActive }) =>
              `flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                isActive
                  ? 'bg-teal-50 text-teal-800'
                  : 'text-slate-600 hover:bg-slate-100 hover:text-slate-800'
              }`
            }
          >
            {item.icon}
            {item.label}
          </NavLink>
        ))}
      </nav>
      <div className="px-5 py-3 text-[11px] leading-relaxed text-slate-400">
        Data is saved in this browser. Export PDFs to share.
      </div>
      <RoleBar />
    </aside>
  )
}

export default function App() {
  return (
    <div className="flex h-full bg-slate-50 text-slate-900">
      <Sidebar />
      <main className="flex flex-1 flex-col overflow-y-auto">
        <LockBanner />
        <div className="min-h-0 flex-1">
          <Routes>
            <Route path="/" element={<Navigate to="/floorplans" replace />} />
            <Route path="/floorplans" element={<FloorPlansPage />} />
            <Route path="/floorplans/:id" element={<FloorPlanEditorPage />} />
            <Route path="/inventory" element={<InventoryPage />} />
            <Route path="/vendors" element={<VendorsPage />} />
            <Route path="/events" element={<EventsPage />} />
            <Route path="/events/:id" element={<EventTimelinePage />} />
            <Route path="*" element={<Navigate to="/floorplans" replace />} />
          </Routes>
        </div>
      </main>
      <RolePicker />
    </div>
  )
}
