import { useTranslation } from 'react-i18next'
import { useTheme } from 'next-themes'
import { Moon, Monitor, Sun } from 'lucide-react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { cn } from '@/lib/utils'
import { setLocale, SUPPORTED_LOCALES, type AppLocale } from '@/i18n'

const THEME_OPTIONS = [
  { value: 'light', labelKey: 'settings.theme.light', icon: Sun },
  { value: 'dark', labelKey: 'settings.theme.dark', icon: Moon },
  { value: 'system', labelKey: 'settings.theme.system', icon: Monitor },
] as const

const LANGUAGE_OPTIONS = SUPPORTED_LOCALES.map((locale) => ({
  value: locale,
  labelKey: `lang.${locale}` as const,
}))

export function SettingsPage() {
  const { t, i18n } = useTranslation()
  const { theme, setTheme } = useTheme()
  const currentLocale = i18n.resolvedLanguage ?? 'sr-Cyrl'

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <div>
        <h2 className="font-brand-heading text-2xl font-bold tracking-tight">{t('settings.title')}</h2>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="font-brand-heading text-base">{t('settings.appearance')}</CardTitle>
          <CardDescription>{t('settings.appearanceDesc')}</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid gap-3 sm:grid-cols-3">
            {THEME_OPTIONS.map((option) => {
              const active = theme === option.value
              return (
                <button
                  key={option.value}
                  type="button"
                  onClick={() => setTheme(option.value)}
                  className={cn(
                    'flex flex-col items-center gap-2 rounded-lg border p-4 text-sm font-medium transition-colors',
                    active
                      ? 'border-primary bg-accent text-accent-foreground'
                      : 'border-border text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                  )}
                >
                  <option.icon className="size-5" />
                  {t(option.labelKey)}
                </button>
              )
            })}
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="font-brand-heading text-base">{t('settings.language')}</CardTitle>
          <CardDescription>{t('settings.languageDesc')}</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid gap-3 sm:grid-cols-3">
            {LANGUAGE_OPTIONS.map((option) => {
              const active = currentLocale === option.value
              return (
                <button
                  key={option.value}
                  type="button"
                  onClick={() => setLocale(option.value as AppLocale)}
                  className={cn(
                    'flex flex-col items-center gap-2 rounded-lg border p-4 text-sm font-medium transition-colors',
                    active
                      ? 'border-primary bg-accent text-accent-foreground'
                      : 'border-border text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                  )}
                >
                  {t(option.labelKey)}
                </button>
              )
            })}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
