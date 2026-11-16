import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { BookCheck, BookMarked, BookOpen, Building2, Users, type LucideIcon } from 'lucide-react'
import { useAuth } from '@/hooks/useAuth'
import { LibrarySwitcher } from '@/components/layout/library-switcher'
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

interface DashboardCard {
  to: string
  icon: LucideIcon
  title: string
  description: string
  external?: boolean
}

export function DashboardPage() {
  const { t } = useTranslation()
  const { user, can, publicUrl } = useAuth()

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

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <div>
        <p className="text-sm font-medium uppercase tracking-wider text-muted-foreground">{t('dashboard.kicker')}</p>
        <h2 className="font-brand-heading mt-1 text-2xl font-bold tracking-tight sm:text-3xl">
          {t('dashboard.welcome', { name: user?.name ?? '' })}
        </h2>
      </div>

      <LibrarySwitcher />

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
