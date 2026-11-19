import { useTranslation } from 'react-i18next'
import { ShieldCheck } from 'lucide-react'
import { SchoolYearSettingsCard } from '@/components/settings/school-year-settings-card'
import { SuperAdminSettingsCard } from '@/components/settings/super-admin-settings-card'
import { useAuth } from '@/hooks/useAuth'

export function SystemSettingsPage() {
  const { t } = useTranslation()
  const { user } = useAuth()

  if (user?.is_super_admin !== true) {
    return <p className="text-sm text-muted-foreground">{t('errors.forbidden')}</p>
  }

  return (
    <div className="mx-auto max-w-4xl space-y-6">
      <div>
        <h2 className="font-brand-heading flex items-center gap-2 text-2xl font-bold tracking-tight">
          <ShieldCheck className="size-6 text-brand" />
          {t('settings.system.title')}
        </h2>
        <p className="mt-1 text-sm text-muted-foreground">{t('settings.system.subtitle')}</p>
      </div>

      <SchoolYearSettingsCard />
      <SuperAdminSettingsCard />
    </div>
  )
}
