import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { api, apiPaths } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { LibrarySelect, type LibraryOption } from '@/components/libraries/library-select'
import { useAuth } from '@/hooks/useAuth'
import type { RoleOption } from '@/types'

export interface UserFilters {
  first_name: string
  last_name: string
  email: string
  username: string
  bar_code: string
  jmbg: string
  city: string
  role: string
  library_id: string
}

export const EMPTY_USER_FILTERS: UserFilters = {
  first_name: '',
  last_name: '',
  email: '',
  username: '',
  bar_code: '',
  jmbg: '',
  city: '',
  role: '',
  library_id: '',
}

const TEXT_FIELDS: Array<{ key: keyof UserFilters; label: string }> = [
  { key: 'first_name', label: 'fields.firstName' },
  { key: 'last_name', label: 'fields.lastName' },
  { key: 'email', label: 'fields.email' },
  { key: 'username', label: 'fields.username' },
  { key: 'bar_code', label: 'fields.barcode' },
  { key: 'jmbg', label: 'fields.jmbg' },
  { key: 'city', label: 'fields.city' },
]

const ALL_VALUE = 'all'

export function countActiveFilters(filters: UserFilters): number {
  return Object.values(filters).filter((value) => value !== '').length
}

interface UserFiltersPanelProps {
  filters: UserFilters
  onApply: (filters: UserFilters) => void
  onClear: () => void
}

export function UserFiltersPanel({ filters, onApply, onClear }: UserFiltersPanelProps) {
  const { t } = useTranslation()
  const { user } = useAuth()
  const isSuperAdmin = user?.role === 'superadmin'
  const [draft, setDraft] = useState<UserFilters>(filters)
  const [selectedLibrary, setSelectedLibrary] = useState<LibraryOption[]>([])

  useEffect(() => {
    setDraft(filters)
    const id = filters.library_id ? Number(filters.library_id) : null
    setSelectedLibrary((current) => {
      if (id === null) return []
      if (current.length === 1 && current[0].id === id) return current
      return [{ id, name: '' }]
    })
  }, [filters])

  const rolesQuery = useQuery({
    queryKey: ['roles', 'all'],
    queryFn: async () => {
      const response = await api.get<{ roles: RoleOption[] }>(apiPaths.roles)
      return response.roles
    },
  })

  const setField = (key: keyof UserFilters, value: string) => {
    setDraft((previous) => ({ ...previous, [key]: value }))
  }

  const apply = () => {
    onApply(isSuperAdmin ? draft : { ...draft, library_id: '' })
  }

  return (
    <div className="rounded-lg border bg-card p-4">
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {TEXT_FIELDS.map(({ key, label }) => (
          <div key={key} className="space-y-1.5">
            <Label htmlFor={`filter-${key}`}>{t(label)}</Label>
            <Input
              id={`filter-${key}`}
              value={draft[key]}
              onChange={(event) => setField(key, event.target.value)}
            />
          </div>
        ))}

        <div className="space-y-1.5">
          <Label>{t('fields.role')}</Label>
          <Select
            value={draft.role || ALL_VALUE}
            onValueChange={(value) => setField('role', value === ALL_VALUE ? '' : value)}
          >
            <SelectTrigger className="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={ALL_VALUE}>{t('users.filters.roleAll')}</SelectItem>
              {rolesQuery.data?.map((role) => (
                <SelectItem key={role.value} value={role.value}>
                  {role.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {isSuperAdmin ? (
          <div className="space-y-1.5">
            <Label>{t('users.filters.library')}</Label>
            <LibrarySelect
              multiple={false}
              value={selectedLibrary}
              onChange={(options) => {
                setSelectedLibrary(options)
                setField('library_id', options[0] ? String(options[0].id) : '')
              }}
            />
          </div>
        ) : null}
      </div>

      <div className="mt-4 flex items-center justify-end gap-2">
        <Button variant="ghost" onClick={onClear}>
          {t('users.filters.clear')}
        </Button>
        <Button variant="brand" onClick={apply}>
          {t('users.filters.apply')}
        </Button>
      </div>
    </div>
  )
}
