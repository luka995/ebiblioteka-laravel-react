import { useEffect, useMemo, useState } from 'react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Search } from 'lucide-react'
import { api, apiPaths } from '@/lib/api'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import type { Library, PaginatedResponse } from '@/types'

export interface LibraryOption {
  id: number
  name: string
}

interface LibrarySelectProps {
  value: LibraryOption[]
  onChange: (value: LibraryOption[]) => void
  multiple?: boolean
}

export function LibrarySelect({ value, onChange, multiple = true }: LibrarySelectProps) {
  const { t } = useTranslation()
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')

  useEffect(() => {
    const timer = setTimeout(() => setDebouncedSearch(search.trim()), 300)
    return () => clearTimeout(timer)
  }, [search])

  const librariesQuery = useQuery({
    queryKey: ['libraries', 'options', debouncedSearch],
    queryFn: async () => {
      const query = new URLSearchParams({ per_page: '25' })
      if (debouncedSearch) query.set('search', debouncedSearch)
      const response = await api.get<PaginatedResponse<Library>>(`${apiPaths.libraries}?${query.toString()}`)
      return response.data
    },
    enabled: debouncedSearch.length > 0,
    placeholderData: keepPreviousData,
  })

  const selectedIds = useMemo(() => new Set(value.map((library) => library.id)), [value])

  const searchResults = useMemo(
    () => (librariesQuery.data ?? []).filter((library) => !selectedIds.has(library.id)),
    [librariesQuery.data, selectedIds],
  )

  const toggleLibrary = (library: LibraryOption) => {
    if (selectedIds.has(library.id)) {
      onChange(value.filter((item) => item.id !== library.id))
      return
    }
    onChange(multiple ? [...value, library] : [library])
  }

  return (
    <div className="space-y-2">
      <div className="relative">
        <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          placeholder={t('libs.search')}
          className="pl-8"
        />
      </div>

      {value.length > 0 ? (
        <div className="space-y-1">
          <p className="text-xs font-medium text-muted-foreground">{t('libs.selected')}</p>
          <div className="max-h-40 space-y-1 overflow-y-auto rounded-md border p-2">
            {value.map((library) => (
              <label
                key={library.id}
                className="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-muted"
              >
                <Checkbox
                  id={`library-${library.id}`}
                  checked
                  onCheckedChange={() => toggleLibrary(library)}
                />
                {library.name || String(library.id)}
              </label>
            ))}
          </div>
        </div>
      ) : null}

      {debouncedSearch.length === 0 ? (
        <p className="px-2 py-1 text-sm text-muted-foreground">{t('libs.typeToSearch')}</p>
      ) : librariesQuery.isLoading && !librariesQuery.data ? (
        <p className="px-2 py-1 text-sm text-muted-foreground">{t('common.loading')}</p>
      ) : (
        <div className="max-h-40 space-y-1 overflow-y-auto rounded-md border p-2">
          {searchResults.length === 0 ? (
            <p className="px-2 py-1 text-sm text-muted-foreground">{t('libs.noResults')}</p>
          ) : (
            searchResults.map((library) => (
              <label
                key={library.id}
                className="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-muted"
              >
                <Checkbox
                  id={`library-${library.id}`}
                  checked={false}
                  onCheckedChange={() => toggleLibrary(library)}
                />
                {library.name}
              </label>
            ))
          )}
        </div>
      )}
    </div>
  )
}
