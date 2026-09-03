import { Outlet } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { BrandMark } from '@/components/brand'
import { LocaleSwitcher } from '@/components/locale-switcher'

export function AuthLayout() {
  const { t } = useTranslation()

  return (
    <div className="grid min-h-screen lg:grid-cols-2">
      <div className="relative hidden overflow-hidden bg-brand text-brand-foreground lg:flex lg:flex-col lg:justify-between lg:p-12">
        <div
          aria-hidden
          className="pointer-events-none absolute -right-24 -top-24 size-96 rounded-full bg-brand-accent/20 blur-2xl"
        />
        <div
          aria-hidden
          className="pointer-events-none absolute -bottom-32 -left-20 size-80 rounded-full bg-brand-accent/10 blur-2xl"
        />

        <div className="relative flex items-center gap-3">
          <BrandMark />
          <span className="font-brand-heading text-lg font-semibold tracking-tight">{t('appName')}</span>
        </div>

        <div className="relative max-w-lg space-y-5">
          <h2 className="font-brand-heading text-4xl font-bold leading-tight tracking-tight">
            {t('auth.heroTitle')}
          </h2>
          <p className="text-lg text-white/75">{t('auth.heroText')}</p>
        </div>

        <p className="relative text-sm text-white/60">{t('tagline')}</p>
      </div>

      <div className="relative flex min-h-screen items-center justify-center bg-background p-6 sm:p-10">
        <div className="absolute right-4 top-4 sm:right-6 sm:top-6">
          <LocaleSwitcher />
        </div>
        <div className="w-full max-w-md">
          <Outlet />
        </div>
      </div>
    </div>
  )
}
