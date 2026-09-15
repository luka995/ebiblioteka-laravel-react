import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Archive, BookOpen, Library } from 'lucide-react'
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { useAuth } from '@/hooks/useAuth'

export function BooksLandingPage() {
  const { t } = useTranslation()
  const { can } = useAuth()

  if (!can('books.viewAny')) {
    return <p className="text-sm text-muted-foreground">{t('errors.forbidden')}</p>
  }

  const cards = [
    {
      to: '/books/titles',
      icon: BookOpen,
      title: t('books.cards.titlesTitle'),
      description: t('books.cards.titlesDescription'),
    },
    {
      to: '/books/copies',
      icon: Library,
      title: t('books.cards.copiesTitle'),
      description: t('books.cards.copiesDescription'),
    },
  ]

  return (
    <div className="space-y-6">
      <div>
        <h2 className="font-brand-heading text-2xl font-bold tracking-tight">{t('books.title')}</h2>
        <p className="mt-1 text-sm text-muted-foreground">{t('books.subtitle')}</p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        {cards.map((card) => (
          <Link key={card.to} to={card.to} className="group">
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

      <Link
        to="/books/archive"
        className="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground"
      >
        <Archive className="size-4" />
        {t('books.archiveLink')}
      </Link>
    </div>
  )
}
