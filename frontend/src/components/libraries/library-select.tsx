import { useEffect, useMemo, useState } from 'react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Search, X } from 'lucide-react'
import { api, apiPaths } from '@/lib/api'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { cn } from '@/lib/utils'
import type { Library, PaginatedResponse } from '@/types'

export interface LibraryOption {
  id: number
  name: string
}

interface LibrarySelectProps {
  value: LibraryOption[]
  onChange: (value: LibraryOption[]) => void
  multiple?: boolean
  clearable?: boolean
  compact?: boolean
  placeholder?: string
  localOptions?: LibraryOption[]
}

function displayNameOf(library: LibraryOption, resolved: Record<number, string>): string {
  if (library.name) return library.name
  const name = resolved[library.id]
  return name || String(library.id)
}

export function LibrarySelect({
  value,
  onChange,
  multiple = true,
  clearable = false,
  compact = false,
  placeholder,
  localOptions,
}: LibrarySelectProps) {
  const { t } = useTranslation()
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')
  const [expanded, setExpanded] = useState(false)

  useEffect(() => {
    const timer = setTimeout(() => setDebouncedSearch(search.trim()), 300)
    return () => clearTimeout(timer)
  }, [search])

  const server = !Array.isArray(localOptions)

  const selectedIds = useMemo(() => new Set(value.map((library) => library.id)), [value])

  const unnamed = useMemo(
    () => value.filter((library) => library.id > 0 && !library.name),
    [value],
  )
  const resolveKey = unnamed.map((library) => library.id).join(',')

  const namesQuery = useQuery({
    queryKey: ['libraries', 'resolve', resolveKey],
    queryFn: async () => {
      const entries = await Promise.all(
        unnamed.map(async (library) => {
          try {
            const response = await api.get<{ data: Library }>(apiPaths.library(library.id))
            return { id: library.id, name: response.data.name }
          } catch {
            return { id: library.id, name: '' }
          }
        }),
      )
      const resolved: Record<number, string> = {}
      entries.forEach((entry) => {
        if (entry.name) resolved[entry.id] = entry.name
      })
      return resolved
    },
    enabled: unnamed.length > 0,
    retry: false,
  })

  const resolvedNames = namesQuery.data ?? {}

  const librariesQuery = useQuery({
    queryKey: ['libraries', 'options', debouncedSearch],
    queryFn: async () => {
      const query = new URLSearchParams({ per_page: '25' })
      if (debouncedSearch) query.set('search', debouncedSearch)
      const response = await api.get<PaginatedResponse<Library>>(`${apiPaths.libraries}?${query.toString()}`)
      return response.data
    },
    enabled: server && debouncedSearch.length > 0,
    placeholderData: keepPreviousData,
  })

  const serverResults = useMemo(
    () => (librariesQuery.data ?? []).filter((library) => !selectedIds.has(library.id)),
    [librariesQuery.data, selectedIds],
  )

  const localResults = useMemo(() => {
    if (server) return []
    const term = debouncedSearch.toLowerCase()
    return (localOptions ?? []).filter(
      (library) => !selectedIds.has(library.id) && (!term || library.name.toLowerCase().includes(term)),
    )
  }, [server, localOptions, debouncedSearch, selectedIds])

  const results = server ? serverResults : localResults
  const isLoading = server && !librariesQuery.data

  const searching = debouncedSearch.length > 0
  const nameOf = (library: LibraryOption) => displayNameOf(library, resolvedNames)

  const clearSelection = () => {
    setSearch('')
    setDebouncedSearch('')
    onChange([])
  }

  const toggleLibrary = (library: LibraryOption) => {
    if (selectedIds.has(library.id)) {
      onChange(value.filter((item) => item.id !== library.id))
      return
    }
    onChange(multiple ? [...value, library] : [library])
    if (compact) {
      setSearch('')
      setDebouncedSearch('')
    }
  }

  const effectivePlaceholder =
    compact && value.length > 0 && multiple === false ? nameOf(value[0]) : (placeholder ?? t('libs.search'))

  const emptyResults = results.length === 0

  return (
    <div
      className={cn('space-y-2', compact && 'relative')}
      onBlur={(event) => {
        if (!event.currentTarget.contains(event.relatedTarget as Node | null)) {
          setExpanded(false)
        }
      }}
    >
      <div className="relative">
        <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          onFocus={() => setExpanded(true)}
          placeholder={effectivePlaceholder}
          className={cn('pl-8', compact && value.length > 0 && multiple === false ? 'pr-8' : '')}
        />
        {compact && value.length > 0 && multiple === false ? (
          <button
            type="button"
            aria-label={t('common.clear')}
            onClick={clearSelection}
            className="absolute top-1/2 right-2 flex -translate-y-1/2 rounded p-0.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
          >
            <X className="size-4" />
          </button>
        ) : null}
      </div>

      {compact ? (
        expanded && searching ? (
          <div className="absolute top-full right-0 left-0 z-50 mt-1 max-h-64 space-y-0.5 overflow-y-auto rounded-md border border-border bg-popover p-1.5 shadow-md">
            {isLoading ? (
              <p className="px-2 py-1 text-sm text-muted-foreground">{t('common.loading')}</p>
            ) : emptyResults ? (
              <p className="px-2 py-1 text-sm text-muted-foreground">{t('libs.noResults')}</p>
            ) : (
              results.map((library) => (
                <button
                  key={library.id}
                  type="button"
                  onMouseDown={(event) => event.preventDefault()}
                  onClick={() => toggleLibrary(library)}
                  className="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-sm transition-colors hover:bg-accent hover:text-accent-foreground"
                >
                  {library.name}
                </button>
              ))
            )}
          </div>
        ) : null
      ) : (
        <>
          {value.length > 0 ? (
            <div className="space-y-1">
              <div className="flex items-center justify-between pr-1">
                <p className="text-xs font-medium text-muted-foreground">{t('libs.selected')}</p>
                {clearable ? (
                  <button
                    type="button"
                    onClick={clearSelection}
                    className="inline-flex items-center gap-0.5 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground"
                  >
                    <X className="size-3.5" />
                    {t('common.clear')}
                  </button>
                ) : null}
              </div>
              <div className="max-h-40 space-y-1 overflow-y-auto rounded-md border p-2">
                {value.map((library) => (
                  <label
                    key={library.id}
                    className="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-muted"
                  >
                    <Checkbox
                      id={`library-selected-${library.id}`}
                      checked
                      onCheckedChange={() => toggleLibrary(library)}
                    />
                    {nameOf(library)}
                  </label>
                ))}
              </div>
            </div>
          ) : null}

          {!searching ? (
            <p className="px-2 py-1 text-sm text-muted-foreground">{t('libs.typeToSearch')}</p>
          ) : isLoading ? (
            <p className="px-2 py-1 text-sm text-muted-foreground">{t('common.loading')}</p>
          ) : (
            <div className="max-h-40 space-y-1 overflow-y-auto rounded-md border p-2">
              {emptyResults ? (
                <p className="px-2 py-1 text-sm text-muted-foreground">{t('libs.noResults')}</p>
              ) : (
                results.map((library) => (
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
        </>
      )}
    </div>
  )
}
