import { type ReactNode, useState } from 'react'
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { BookOpen, Building2, LayoutDashboard, LogOut, Menu, Users, X } from 'lucide-react'
import { useAuth } from '@/hooks/useAuth'
import { BrandMark } from '@/components/brand'
import { LocaleSwitcher } from '@/components/locale-switcher'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import type { RoleValue } from '@/types'

interface NavItem {
  to: string
  labelKey: string
  icon: typeof LayoutDashboard
  roles: RoleValue[]
}

const NAV_ITEMS: NavItem[] = [
  { to: '/', labelKey: 'nav.dashboard', icon: LayoutDashboard, roles: ['superadmin', 'library_admin', 'librarian', 'user'] },
  { to: '/users', labelKey: 'nav.users', icon: Users, roles: ['superadmin'] },
  { to: '/libraries', labelKey: 'nav.libraries', icon: Building2, roles: ['superadmin'] },
]

function navItemsFor(role: RoleValue): NavItem[] {
  return NAV_ITEMS.filter((item) => item.roles.includes(role))
}

function SidebarContent({ onNavigate }: { onNavigate?: () => void }) {
  const { t } = useTranslation()
  const { user } = useAuth()
  const items = user ? navItemsFor(user.role) : []

  return (
    <div className="flex h-full flex-col">
      <div className="flex items-center gap-2.5 px-5 py-5">
        <Link to="/" className="flex items-center gap-2.5" onClick={onNavigate}>
          <BrandMark />
          <span className="font-brand-heading text-base font-semibold tracking-tight">{t('appName')}</span>
        </Link>
      </div>

      <nav className="flex-1 space-y-1 px-3 py-2" aria-label="Main">
        {items.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            end={item.to === '/'}
            onClick={onNavigate}
            className={({ isActive }) =>
              cn(
                'flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                isActive
                  ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                  : 'text-sidebar-foreground/75 hover:bg-muted hover:text-sidebar-foreground',
              )
            }
          >
            <item.icon className="size-4" />
            {t(item.labelKey)}
          </NavLink>
        ))}
      </nav>

      <div className="border-t px-4 py-4">
        {user ? (
          <div className="flex items-center gap-3">
            <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-accent text-sm font-semibold text-brand">
              {user.name.charAt(0).toUpperCase()}
            </span>
            <div className="min-w-0 flex-1">
              <p className="truncate text-sm font-medium">{user.name}</p>
              <p className="truncate text-xs text-muted-foreground">{user.role_label}</p>
            </div>
            <LogoutButton />
          </div>
        ) : null}
      </div>
    </div>
  )
}

function LogoutButton() {
  const { t } = useTranslation()
  const { logout } = useAuth()

  return (
    <button
      type="button"
      title={t('nav.logout')}
      onClick={() => void logout()}
      className="rounded-md p-2 text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
    >
      <LogOut className="size-4" />
    </button>
  )
}

function pageTitleFor(pathname: string): string {
  if (pathname.startsWith('/users')) return 'nav.users'
  if (pathname.startsWith('/libraries')) return 'nav.libraries'
  return 'nav.dashboard'
}
export function AppShell() {
  const { t } = useTranslation()
  const location = useLocation()
  const [sidebarOpen, setSidebarOpen] = useState(false)

  return (
    <div className="flex h-screen overflow-hidden bg-background">
      <aside className="hidden w-64 shrink-0 border-r bg-sidebar text-sidebar-foreground md:block">
        <SidebarContent />
      </aside>

      {sidebarOpen ? (
        <div className="fixed inset-0 z-40 md:hidden">
          <div className="absolute inset-0 bg-black/50" onClick={() => setSidebarOpen(false)} />
          <aside className="absolute inset-y-0 left-0 w-64 bg-sidebar text-sidebar-foreground shadow-xl">
            <button
              type="button"
              onClick={() => setSidebarOpen(false)}
              className="absolute right-3 top-4 rounded-md p-1 text-muted-foreground hover:bg-accent"
              aria-label="Close menu"
            >
              <X className="size-5" />
            </button>
            <SidebarContent onNavigate={() => setSidebarOpen(false)} />
          </aside>
        </div>
      ) : null}

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex h-16 shrink-0 items-center gap-4 border-b bg-card/60 px-4 backdrop-blur sm:px-6">
          <button
            type="button"
            className="rounded-md p-2 text-muted-foreground hover:bg-accent md:hidden"
            onClick={() => setSidebarOpen(true)}
            aria-label="Open menu"
          >
            <Menu className="size-5" />
          </button>

          <div className="flex items-center gap-2">
            <BookOpen className="size-5 text-brand-accent md:hidden" />
            <h1 className="font-brand-heading text-base font-semibold tracking-tight">
              {t(pageTitleFor(location.pathname))}
            </h1>
          </div>

          <div className="ml-auto flex items-center gap-2">
            <LocaleSwitcher />
          </div>
        </header>

        <main className="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
          <Outlet />
        </main>
      </div>
    </div>
  )
}

export function ShellPlaceholder({ children }: { children: ReactNode }) {
  return <div className="h-full">{children}</div>
}
