import { useEffect, useState } from 'react'
import { Building2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useAuth } from '@/hooks/useAuth'
import { LibrarySelect, type LibraryOption } from '@/components/libraries/library-select'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

/**
 * Kartica "aktivna biblioteka" koja se prikazuje na Dashboard-u.
 * Superadmin je od ovog trenutka vodi iz header-a (HeaderLibrarySwitcher).
 */
export function LibrarySwitcher() {
  const { t } = useTranslation()
  const { user, libraries, activeLibrary, setActiveLibrary } = useAuth()

  if (!user || user.role === 'superadmin') {
    return null
  }

  if (libraries.length === 0) {
    return null
  }

  if (libraries.length === 1) {
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

  const value = activeLibrary ? String(activeLibrary.id) : ''

  const handleChange = (next: string) => {
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

/**
 * Ajax pretraga biblioteka u header navigaciji — samo za superadmin.
 * Čist izbor vraća opseg na "sve biblioteke" (aktivna = null).
 */
export function HeaderLibrarySwitcher() {
  const { t } = useTranslation()
  const { user, activeLibrary, setActiveLibrary } = useAuth()
  const [selection, setSelection] = useState<LibraryOption[]>([])

  useEffect(() => {
    setSelection(activeLibrary ? [{ id: activeLibrary.id, name: activeLibrary.name }] : [])
  }, [activeLibrary])

  if (user?.role !== 'superadmin') {
    return null
  }

  return (
    <div className="flex items-center gap-2" aria-label={t('dashboard.activeLibrary')}>
      <span className="hidden size-8 shrink-0 items-center justify-center rounded-full bg-brand-soft text-brand min-[420px]:flex">
        <Building2 className="size-4" />
      </span>
      <div className="w-44 sm:w-60 lg:w-72">
        <LibrarySelect
          compact
          multiple={false}
          value={selection}
          placeholder={t('dashboard.allLibraries')}
          onChange={(options) => {
            setSelection(options)
            void setActiveLibrary(options[0] ? options[0].id : null)
          }}
        />
      </div>
    </div>
  )
}
