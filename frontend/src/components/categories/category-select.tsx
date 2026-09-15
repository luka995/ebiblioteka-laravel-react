import { useEffect, useMemo, useRef, useState } from 'react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Plus, Search, X } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { Input } from '@/components/ui/input'
import { useAuth } from '@/hooks/useAuth'
import type { Category, PaginatedResponse } from '@/types'

export interface CategoryOption {
  id: number
  full_name: string
}

interface CategorySelectProps {
  libraryId: number | null
  value: CategoryOption | null
  onChange: (value: CategoryOption | null) => void
  placeholder?: string
  excludeId?: number
  seed?: string
  allowCreate?: boolean
  autoSelectSingle?: boolean
}

export function CategorySelect({
  libraryId,
  value,
  onChange,
  placeholder,
  excludeId,
  seed,
  allowCreate = false,
  autoSelectSingle = false,
}: CategorySelectProps) {
  const { t } = useTranslation()
  const { can } = useAuth()
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')
  const [expanded, setExpanded] = useState(false)
  const [seedActive, setSeedActive] = useState(false)
  const [isCreating, setIsCreating] = useState(false)
  const appliedSeed = useRef('')

  useEffect(() => {
    const timer = setTimeout(() => setDebouncedSearch(search.trim()), 300)
    return () => clearTimeout(timer)
  }, [search])

  const searching = debouncedSearch.length > 0
  const canCreate = allowCreate && can('categories.create')

  const categoriesQuery = useQuery({
    queryKey: ['categories', 'options', libraryId, debouncedSearch],
    queryFn: async () => {
      const params = new URLSearchParams({ all: '1', per_page: '25' })
      if (libraryId) params.set('library_id', String(libraryId))
      if (debouncedSearch) params.set('search', debouncedSearch)
      const response = await api.get<PaginatedResponse<Category>>(
        `${apiPaths.categories}?${params.toString()}`,
      )
      return response.data
    },
    enabled: Boolean(libraryId) && searching,
    placeholderData: keepPreviousData,
  })

  const results = useMemo(
    () => (categoriesQuery.data ?? []).filter((category) => category.id !== excludeId),
    [categoriesQuery.data, excludeId],
  )

  useEffect(() => {
    if (!seed || seed === appliedSeed.current) return
    appliedSeed.current = seed
    setSeedActive(true)
    setSearch(seed)
    setDebouncedSearch(seed.trim())
    setExpanded(true)
  }, [seed])

  useEffect(() => {
    if (!seedActive) return
    if (categoriesQuery.isLoading || categoriesQuery.isFetching) return

    if (autoSelectSingle && results.length === 1) {
      onChange({ id: results[0].id, full_name: results[0].full_name ?? results[0].name })
      setSearch('')
      setDebouncedSearch('')
      setExpanded(false)
    }

    setSeedActive(false)
  }, [seedActive, autoSelectSingle, results, categoriesQuery.isLoading, categoriesQuery.isFetching, onChange])

  const displayPlaceholder = value
    ? value.full_name
    : (placeholder ?? t('categories.parentPlaceholder'))

  const clearSelection = () => {
    setSearch('')
    setDebouncedSearch('')
    onChange(null)
  }

  const selectCategory = (category: Category) => {
    onChange({ id: category.id, full_name: category.full_name ?? category.name })
    setSearch('')
    setDebouncedSearch('')
    setExpanded(false)
  }

  const createCategory = async (name: string) => {
    const trimmed = name.trim()
    if (!libraryId || trimmed === '') return
    setIsCreating(true)
    try {
      const response = await api.post<{ data: Category }>(apiPaths.categories, {
        name: trimmed,
        library_id: libraryId,
        parent_id: null,
      })
      onChange({ id: response.data.id, full_name: response.data.full_name ?? response.data.name })
      setSearch('')
      setDebouncedSearch('')
      setExpanded(false)
    } catch (error) {
      if (error instanceof ApiError) toast.error(error.messageText ?? t('errors.unexpected'))
    } finally {
      setIsCreating(false)
    }
  }

  if (!libraryId) {
    return <p className="text-sm text-muted-foreground">{t('categories.selectLibraryHint')}</p>
  }

  return (
    <div
      className="relative"
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
          placeholder={displayPlaceholder}
          className="pl-8 pr-8"
        />
        {value ? (
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

      {expanded && searching ? (
        <div className="absolute top-full right-0 left-0 z-50 mt-1 max-h-64 space-y-0.5 overflow-y-auto rounded-md border border-border bg-popover p-1.5 shadow-md">
          {categoriesQuery.isLoading ? (
            <p className="px-2 py-1 text-sm text-muted-foreground">{t('common.loading')}</p>
          ) : results.length === 0 ? (
            canCreate ? (
              <button
                type="button"
                onMouseDown={(event) => event.preventDefault()}
                onClick={() => void createCategory(debouncedSearch)}
                disabled={isCreating}
                className="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm font-medium text-brand-accent transition-colors hover:bg-accent disabled:opacity-50"
              >
                <Plus className="size-4" />
                {t('categories.createNamed', { name: debouncedSearch })}
              </button>
            ) : (
              <p className="px-2 py-1 text-sm text-muted-foreground">{t('categories.noResults')}</p>
            )
          ) : (
            results.map((category) => (
              <button
                key={category.id}
                type="button"
                onMouseDown={(event) => event.preventDefault()}
                onClick={() => selectCategory(category)}
                className="flex w-full items-center rounded-sm px-2 py-1.5 text-left text-sm transition-colors hover:bg-accent hover:text-accent-foreground"
              >
                {category.full_name ?? category.name}
              </button>
            ))
          )}
        </div>
      ) : null}
    </div>
  )
}
