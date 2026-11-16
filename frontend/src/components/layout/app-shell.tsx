import { type ReactNode, useEffect, useState } from 'react'
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { BadgeCheck, BookCheck, BookOpen, Building2, ChevronDown, ClipboardList, FolderTree, KeyRound, LayoutDashboard, LogOut, Map, MapPin, Menu, Newspaper, PenLine, Settings, SlidersHorizontal, Tag, Users, X } from 'lucide-react'
import { useAuth } from '@/hooks/useAuth'
import { BrandMark } from '@/components/brand'
import { HeaderLibrarySwitcher } from '@/components/layout/library-switcher'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { ChangePasswordDialog } from '@/components/profile/change-password-dialog'
import { cn } from '@/lib/utils'

interface NavItem {
  to: string
  labelKey: string
  icon: typeof LayoutDashboard
  permission: string | null
}

const NAV_ITEMS: NavItem[] = [
  { to: '/', labelKey: 'nav.dashboard', icon: LayoutDashboard, permission: null },
  { to: '/users', labelKey: 'nav.users', icon: Users, permission: 'users.viewAny' },
  { to: '/libraries', labelKey: 'nav.libraries', icon: Building2, permission: 'libraries.viewAny' },
  { to: '/tags', labelKey: 'nav.tags', icon: Tag, permission: 'tags.viewAny' },
  { to: '/categories', labelKey: 'nav.categories', icon: FolderTree, permission: 'categories.viewAny' },
  { to: '/authors', labelKey: 'nav.authors', icon: PenLine, permission: 'authors.viewAny' },
  { to: '/books', labelKey: 'nav.books', icon: BookOpen, permission: 'books.viewAny' },
  { to: '/borrows/library', labelKey: 'nav.libraryBorrows', icon: BookCheck, permission: 'borrows.viewAny' },
]

const REFERENCE_ITEMS: NavItem[] = [
  { to: '/regions', labelKey: 'nav.regions', icon: Map, permission: 'regions.viewAny' },
  { to: '/places', labelKey: 'nav.places', icon: MapPin, permission: 'places.viewAny' },
  { to: '/news', labelKey: 'nav.news', icon: Newspaper, permission: 'news.viewAny' },
]

function navItemClass(isActive: boolean, nested = false): string {
  return cn(
    'flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium transition-colors',
    nested && 'ml-3',
    isActive
      ? 'bg-sidebar-accent text-sidebar-accent-foreground'
      : 'text-sidebar-foreground/75 hover:bg-muted hover:text-sidebar-foreground',
  )
}

function SidebarContent({ onNavigate }: { onNavigate?: () => void }) {
  const { t } = useTranslation()
  const { user, can, activeLibrary, logout } = useAuth()
  const [passwordOpen, setPasswordOpen] = useState(false)
  const location = useLocation()
  const items = NAV_ITEMS.filter((item) => item.permission === null || can(item.permission))
  const referenceItems = REFERENCE_ITEMS.filter((item) => item.permission === null || can(item.permission))

  if (user?.is_super_admin) {
    referenceItems.push({
      to: '/library-requests',
      labelKey: 'nav.libraryRequests',
      icon: ClipboardList,
      permission: null,
    })
    referenceItems.push({
      to: '/settings/system',
      labelKey: 'nav.systemSettings',
      icon: SlidersHorizontal,
      permission: null,
    })
  }

  const referenceActive = referenceItems.some((item) => location.pathname.startsWith(item.to))
  const [referenceOpen, setReferenceOpen] = useState(referenceActive)

  useEffect(() => {
    if (referenceActive) setReferenceOpen(true)
  }, [referenceActive])

  return (
    <div className="flex h-full flex-col">
      <div className="flex items-center gap-2.5 px-5 py-5">
        <Link to="/" className="flex items-center gap-2.5" onClick={onNavigate}>
          <BrandMark />
          <span className="font-brand-heading text-base font-semibold tracking-tight">{t('appName')}</span>
        </Link>
      </div>

      <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-2" aria-label="Main">
        {items.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            end={item.to === '/'}
            onClick={onNavigate}
            className={({ isActive }) => navItemClass(isActive)}
          >
            <item.icon className="size-4" />
            {t(item.labelKey)}
          </NavLink>
        ))}

        {referenceItems.length > 0 ? (
          <div className="pt-2">
            <button
              type="button"
              onClick={() => setReferenceOpen((value) => !value)}
              className="flex w-full items-center justify-between rounded-md px-3 py-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground transition-colors hover:bg-muted hover:text-sidebar-foreground"
              aria-expanded={referenceOpen}
            >
              {t('nav.reference')}
              <ChevronDown className={cn('size-4 transition-transform', referenceOpen && 'rotate-180')} />
            </button>

            {referenceOpen ? (
              <div className="mt-1 space-y-1">
                {referenceItems.map((item) => (
                  <NavLink
                    key={item.to}
                    to={item.to}
                    onClick={onNavigate}
                    className={({ isActive }) => navItemClass(isActive, true)}
                  >
                    <item.icon className="size-4" />
                    {t(item.labelKey)}
                  </NavLink>
                ))}
              </div>
            ) : null}
          </div>
        ) : null}
      </nav>

      <div className="border-t px-2 py-2">
        {user ? (
          <>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <button className="flex w-full items-center gap-3 rounded-md p-2 text-left transition-colors hover:bg-muted focus:outline-none">
                  <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-accent text-sm font-semibold text-brand">
                    {user.name.charAt(0).toUpperCase()}
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm font-medium">{user.name}</span>
                    <span className="block truncate text-xs text-muted-foreground">
                      {user.is_super_admin
                        ? t('roles.superadmin')
                        : activeLibrary?.role_label ?? user.role_label}
                    </span>
                  </span>
                </button>
              </DropdownMenuTrigger>
              <DropdownMenuContent side="right" align="end" className="w-56">
                <DropdownMenuLabel className="p-0 font-normal">
                  <div className="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                    <Avatar className="size-8 rounded-lg">
                      <AvatarFallback className="rounded-lg">
                        {user.name.charAt(0).toUpperCase()}
                      </AvatarFallback>
                    </Avatar>
                    <div className="grid flex-1 text-start text-sm leading-tight">
                      <span className="truncate font-semibold">{user.name}</span>
                      <span className="truncate text-xs text-muted-foreground">{user.email}</span>
                    </div>
                  </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                  <DropdownMenuItem asChild>
                    <Link to="/profile" onClick={onNavigate}>
                      <BadgeCheck />
                      {t('nav.account')}
                    </Link>
                  </DropdownMenuItem>
                  <DropdownMenuItem asChild>
                    <Link to="/settings" onClick={onNavigate}>
                      <Settings />
                      {t('nav.settings')}
                    </Link>
                  </DropdownMenuItem>
                  <DropdownMenuItem onSelect={() => setPasswordOpen(true)}>
                    <KeyRound />
                    {t('profile.changePassword')}
                  </DropdownMenuItem>
                </DropdownMenuGroup>
                <DropdownMenuSeparator />
                <DropdownMenuItem variant="destructive" onSelect={() => void logout()}>
                  <LogOut />
                  {t('nav.logout')}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
            <ChangePasswordDialog open={passwordOpen} onClose={() => setPasswordOpen(false)} />
          </>
        ) : null}
      </div>
    </div>
  )
}

function pageTitleFor(pathname: string): string {
  if (pathname.startsWith('/borrows/library')) return 'nav.libraryBorrows'
  if (pathname.startsWith('/borrows')) return 'nav.myBorrows'
  if (pathname.startsWith('/reservations/library')) return 'nav.libraryReservations'
  if (pathname.startsWith('/reservations')) return 'nav.myReservations'
  if (pathname.startsWith('/catalog')) return 'nav.myReservations'
  if (pathname.startsWith('/users')) return 'nav.users'
  if (pathname.startsWith('/libraries')) return 'nav.libraries'
  if (pathname.startsWith('/regions')) return 'nav.regions'
  if (pathname.startsWith('/places')) return 'nav.places'
  if (pathname.startsWith('/tags')) return 'nav.tags'
  if (pathname.startsWith('/categories')) return 'nav.categories'
  if (pathname.startsWith('/authors')) return 'nav.authors'
  if (pathname.startsWith('/books')) return 'nav.books'
  if (pathname.startsWith('/news')) return 'nav.news'
  if (pathname.startsWith('/library-requests')) return 'nav.libraryRequests'
  if (pathname.startsWith('/settings/system')) return 'nav.systemSettings'
  if (pathname.startsWith('/profile')) return 'nav.account'
  if (pathname.startsWith('/settings')) return 'nav.settings'
  return 'nav.dashboard'
}
export function AppShell() {
  const { t } = useTranslation()
  const location = useLocation()
  const [sidebarOpen, setSidebarOpen] = useState(false)

  // Dashboard ima svoj interni skrol (`main`); dokument ne sme dodatno da
  // skroluje, inace se u klasicnim scrollbar-ovima vide dva skrolbara.
  useEffect(() => {
    const html = document.documentElement
    const body = document.body
    const previousHtml = html.style.overflow
    const previousBody = body.style.overflow

    html.style.overflow = 'hidden'
    body.style.overflow = 'hidden'

    return () => {
      html.style.overflow = previousHtml
      body.style.overflow = previousBody
    }
  }, [])

  return (
    <div className="flex h-dvh overflow-hidden bg-background">
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

      <div className="flex min-h-0 min-w-0 flex-1 flex-col">
        <header className="flex h-16 shrink-0 items-center gap-4 border-b bg-card/60 px-4 backdrop-blur sm:px-6">
          <button
            type="button"
            className="shrink-0 rounded-md p-2 text-muted-foreground hover:bg-accent md:hidden"
            onClick={() => setSidebarOpen(true)}
            aria-label="Open menu"
          >
            <Menu className="size-5" />
          </button>

          <div className="flex min-w-0 items-center gap-2">
            <BookOpen className="size-5 shrink-0 text-brand-accent md:hidden" />
            <h1 className="truncate font-brand-heading text-base font-semibold tracking-tight">
              {t(pageTitleFor(location.pathname))}
            </h1>
          </div>

          <div className="ml-auto shrink-0">
            <HeaderLibrarySwitcher />
          </div>
        </header>

        <main className="flex-1 overflow-y-auto overflow-x-hidden px-4 pt-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:px-6 sm:pt-6 sm:pb-[max(1.5rem,env(safe-area-inset-bottom))] lg:px-8 lg:pt-8 lg:pb-[max(2rem,env(safe-area-inset-bottom))]">
          <Outlet />
        </main>
      </div>
    </div>
  )
}

export function ShellPlaceholder({ children }: { children: ReactNode }) {
  return <div className="h-full">{children}</div>
}
