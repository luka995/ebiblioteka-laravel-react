import { Building2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useAuth } from '@/hooks/useAuth'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

const ALL_LIBRARIES = 'all'

export function LibrarySwitcher() {
  const { t } = useTranslation()
  const { user, libraries, activeLibrary, setActiveLibrary } = useAuth()

  if (!user) {
    return null
  }

  const isSuperAdmin = user.role === 'superadmin'

  if (!isSuperAdmin && libraries.length === 0) {
    return null
  }

  if (!isSuperAdmin && libraries.length === 1) {
    return (
      <div className="flex items-center gap-3 rounded-xl border bg-card px-4 py-3">
        <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-soft text-brand">
          <Building2 className="size-5" />
        </span>
        <div className="min-w-0">
          <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
            {t('dashboard.activeLibrary')}
          </p>
          <p className="truncate text-sm font-semibold">{libraries[0].name}</p>
        </div>
      </div>
    )
  }

  const value = activeLibrary
    ? String(activeLibrary.id)
    : isSuperAdmin
      ? ALL_LIBRARIES
      : ''

  const handleChange = (next: string) => {
    if (next === ALL_LIBRARIES) {
      void setActiveLibrary(null)
      return
    }
    void setActiveLibrary(Number(next))
  }

  return (
    <div className="flex flex-col gap-3 rounded-xl border bg-card px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
      <div className="flex items-center gap-3">
        <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-soft text-brand">
          <Building2 className="size-5" />
        </span>
        <div>
          <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
            {t('dashboard.activeLibrary')}
          </p>
          <p className="text-sm text-muted-foreground">{t('dashboard.chooseLibraryHint')}</p>
        </div>
      </div>

      <Select value={value} onValueChange={handleChange}>
        <SelectTrigger className="w-full sm:w-64">
          <SelectValue placeholder={t('dashboard.selectLibrary')} />
        </SelectTrigger>
        <SelectContent>
          {isSuperAdmin ? <SelectItem value={ALL_LIBRARIES}>{t('dashboard.allLibraries')}</SelectItem> : null}
          {libraries.map((library) => (
            <SelectItem key={library.id} value={String(library.id)}>
              {library.name}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  )
}
