import { NavLink, Navigate, Route, Routes } from 'react-router-dom'
import { CalendarClock, LayoutGrid, Package, Users } from 'lucide-react'
import type { ReactNode } from 'react'
import FloorPlansPage from './pages/FloorPlansPage'
import FloorPlanEditorPage from './pages/FloorPlanEditorPage'
import InventoryPage from './pages/InventoryPage'
import VendorsPage from './pages/VendorsPage'
import EventsPage from './pages/EventsPage'
import EventTimelinePage from './pages/EventTimelinePage'

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
      <div className="px-5 py-4 text-[11px] leading-relaxed text-slate-400">
        Data is saved in this browser. Export PDFs to share.
      </div>
    </aside>
  )
}

export default function App() {
  return (
    <div className="flex h-full bg-slate-50 text-slate-900">
      <Sidebar />
      <main className="flex-1 overflow-y-auto">
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
      </main>
    </div>
  )
}
