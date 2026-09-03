import { useTranslation } from 'react-i18next'
import { Sparkles } from 'lucide-react'
import { useAuth } from '@/hooks/useAuth'

export function DashboardPage() {
  const { t } = useTranslation()
  const { user } = useAuth()

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <div>
        <p className="text-sm font-medium uppercase tracking-wider text-muted-foreground">{t('dashboard.kicker')}</p>
        <h2 className="font-brand-heading mt-1 text-2xl font-bold tracking-tight sm:text-3xl">
          {t('dashboard.welcome', { name: user?.name ?? '' })}
        </h2>
      </div>

      <div className="flex flex-col items-center justify-center rounded-xl border border-dashed bg-card px-6 py-16 text-center">
        <span className="flex size-12 items-center justify-center rounded-full bg-brand-soft text-brand">
          <Sparkles className="size-6" />
        </span>
        <h3 className="font-brand-heading mt-4 text-lg font-semibold">{t('dashboard.emptyTitle')}</h3>
        <p className="mt-1 max-w-sm text-sm text-muted-foreground">{t('dashboard.emptyText')}</p>
      </div>
    </div>
  )
}
