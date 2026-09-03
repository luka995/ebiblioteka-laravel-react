import { useTranslation } from 'react-i18next'
import { Languages } from 'lucide-react'
import { setLocale, SUPPORTED_LOCALES } from '@/i18n'
import { cn } from '@/lib/utils'

export function LocaleSwitcher({ className }: { className?: string }) {
  const { t, i18n } = useTranslation()
  const current = i18n.resolvedLanguage ?? 'sr-Cyrl'

  return (
    <div className={cn('relative', className)}>
      <Languages className="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
      <select
        aria-label="Language"
        value={current}
        onChange={(event) => {
          const value = event.target.value as (typeof SUPPORTED_LOCALES)[number]
          setLocale(value)
        }}
        className="h-9 appearance-none rounded-md border border-input bg-background pl-8 pr-6 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
      >
        {SUPPORTED_LOCALES.map((locale) => (
          <option key={locale} value={locale}>
            {t(`lang.${locale}`)}
          </option>
        ))}
      </select>
      <span className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] text-muted-foreground">
        ▾
      </span>
    </div>
  )
}
