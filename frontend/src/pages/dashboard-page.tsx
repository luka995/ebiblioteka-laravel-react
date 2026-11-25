import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import {
  BookCheck,
  BookMarked,
  BookOpen,
  Building2,
  CalendarClock,
  Users,
  type LucideIcon,
} from 'lucide-react'
import { api, apiPaths } from '@/lib/api'
import { useAuth } from '@/hooks/useAuth'
import { LibrarySwitcher } from '@/components/layout/library-switcher'
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { cn } from '@/lib/utils'
import type { DashboardStats } from '@/types'

interface DashboardCard {
  to: string
  icon: LucideIcon
  title: string
  description: string
  external?: boolean
}

interface DashboardStatCard {
  key: string
  to?: string
  icon: LucideIcon
  label: string
  value: string
}

export function DashboardPage() {
  const { t, i18n } = useTranslation()
  const { user, can, publicUrl, activeLibrary } = useAuth()

  const canViewStats = activeLibrary !== null
    && (user?.is_super_admin === true
      || activeLibrary.role === 'librarian'
      || activeLibrary.role === 'library_admin')

  const statsQuery = useQuery({
    queryKey: ['dashboard-stats', activeLibrary?.id ?? null],
    queryFn: () => api.get<{ data: DashboardStats }>(apiPaths.dashboardStats),
    enabled: canViewStats,
  })

  const stats = statsQuery.data?.data
  const locale = i18n.resolvedLanguage ?? i18n.language
  const numberFormat = new Intl.NumberFormat(locale)

  const booksUrl = can('books.viewAny')
    ? '/books'
    : publicUrl
      ? `${publicUrl}/biblioteke`
      : '/reservations'

  const cards: DashboardCard[] = [
    {
      to: '/reservations',
      icon: BookMarked,
      title: t('dashboard.cards.reservationsTitle'),
      description: t('dashboard.cards.reservationsDescription'),
    },
    {
      to: '/borrows',
      icon: BookCheck,
      title: t('dashboard.cards.borrowsTitle'),
      description: t('dashboard.cards.borrowsDescription'),
    },
    {
      to: booksUrl,
      icon: BookOpen,
      title: t('dashboard.cards.booksTitle'),
      description: t('dashboard.cards.booksDescription'),
      external: !booksUrl.startsWith('/'),
    },
    ...(can('users.viewAny')
      ? [{
          to: '/users',
          icon: Users,
          title: t('dashboard.cards.usersTitle'),
          description: t('dashboard.cards.usersDescription'),
        }]
      : []),
    ...(user?.is_super_admin
      ? [{
          to: '/libraries',
          icon: Building2,
          title: t('dashboard.cards.librariesTitle'),
          description: t('dashboard.cards.librariesDescription'),
        }]
      : []),
  ]

  const statCards: DashboardStatCard[] = [
    {
      key: 'members',
      to: can('users.viewAny') ? '/users' : undefined,
      icon: Users,
      label: t('dashboard.stats.members'),
      value: stats ? numberFormat.format(stats.members_count) : '—',
    },
    {
      key: 'books',
      to: can('book_copies.viewAny') ? '/books/copies' : can('books.viewAny') ? '/books' : undefined,
      icon: BookOpen,
      label: t('dashboard.stats.books'),
      value: stats ? numberFormat.format(stats.books_count) : '—',
    },
    {
      key: 'active-borrows',
      to: can('borrows.viewAny') ? '/borrows/library' : undefined,
      icon: BookCheck,
      label: t('dashboard.stats.activeBorrows'),
      value: stats ? numberFormat.format(stats.active_borrows_count) : '—',
    },
    {
      key: 'overdue-borrows',
      to: can('borrows.viewAny') ? '/borrows/library?status=overdue' : undefined,
      icon: CalendarClock,
      label: t('dashboard.stats.overdueBorrows'),
      value: stats ? numberFormat.format(stats.overdue_borrows_count) : '—',
    },
  ]

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <div>
        <p className="text-sm font-medium uppercase tracking-wider text-muted-foreground">{t('dashboard.kicker')}</p>
        <h2 className="font-brand-heading mt-1 text-2xl font-bold tracking-tight sm:text-3xl">
          {t('dashboard.welcome', { name: user?.name ?? '' })}
        </h2>
      </div>

      <LibrarySwitcher />

      {canViewStats ? (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {statCards.map((card) => {
            const content = (
              <Card
                className={cn(
                  'h-full gap-1 py-4 transition-colors',
                  card.to && 'group-hover:border-brand-accent',
                )}
              >
                <CardHeader className="px-4">
                  <div className="flex items-start justify-between gap-3">
                    <CardDescription>{card.label}</CardDescription>
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-accent/25 text-brand">
                      <card.icon className="size-4" />
                    </span>
                  </div>
                  <CardTitle className="mt-1 text-2xl tabular-nums">{card.value}</CardTitle>
                </CardHeader>
              </Card>
            )

            return card.to ? (
              <Link key={card.key} to={card.to} className="group">
                {content}
              </Link>
            ) : (
              <div key={card.key}>{content}</div>
            )
          })}
        </div>
      ) : null}

      <div className="grid gap-4 sm:grid-cols-2">
        {cards.map((card) => (
          <Link
            key={card.to}
            to={card.to}
            target={card.external ? '_blank' : undefined}
            rel={card.external ? 'noreferrer' : undefined}
            className="group"
          >
            <Card className="h-full transition-colors group-hover:border-brand-accent">
              <CardHeader>
                <span className="flex size-11 items-center justify-center rounded-lg bg-brand-accent/25 text-brand">
                  <card.icon className="size-5" />
                </span>
                <CardTitle className="mt-3 font-brand-heading text-lg">{card.title}</CardTitle>
                <CardDescription>{card.description}</CardDescription>
              </CardHeader>
            </Card>
          </Link>
        ))}
      </div>
    </div>
  )
}
