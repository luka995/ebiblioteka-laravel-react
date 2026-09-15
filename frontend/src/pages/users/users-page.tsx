import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Barcode, Eye, Filter, Pencil, Plus, Power, Printer, Tag, UserX } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { PageLoader } from '@/components/ui/loader'
import { PaginationBar } from '@/components/pagination-bar'
import { UserFormModal } from '@/components/users/user-form-modal'
import { UserMembershipModal } from '@/components/users/user-membership-modal'
import { UsersMobileList } from '@/components/users/users-mobile-list'
import {
  UserMembershipsBulkModal,
  type BulkMembershipMode,
} from '@/components/users/user-memberships-bulk-modal'
import { UserTagsBulkModal } from '@/components/users/user-tags-bulk-modal'
import { UserBarcodeBulkModal } from '@/components/users/user-barcode-bulk-modal'
import { UserBarcodeBulkPrintModal } from '@/components/users/user-barcode-bulk-print-modal'
import {
  countActiveFilters,
  EMPTY_USER_FILTERS,
  UserFiltersPanel,
  type UserFilters,
} from '@/components/users/user-filters-panel'
import { useAuth } from '@/hooks/useAuth'
import type { PaginatedResponse, User } from '@/types'

const PAGE_SIZE = 10

const FILTER_KEYS = [
  'first_name',
  'last_name',
  'email',
  'username',
  'bar_code',
  'jmbg',
  'city',
  'role',
  'library_id',
] as const

function readFilters(params: URLSearchParams): UserFilters {
  const filters = { ...EMPTY_USER_FILTERS }
  FILTER_KEYS.forEach((key) => {
    const value = params.get(key)
    if (value) filters[key] = value
  })
  return filters
}

function useUsersQuery(page: number, filters: UserFilters) {
  return useQuery({
    queryKey: ['users', { page, filters }],
    queryFn: async () => {
      const query = new URLSearchParams({ per_page: String(PAGE_SIZE), page: String(page) })
      Object.entries(filters).forEach(([key, value]) => {
        if (value) query.set(key, String(value))
      })
      return api.get<PaginatedResponse<User>>(`${apiPaths.users}?${query.toString()}`)
    },
  })
}

export function UsersPage() {
  const { t } = useTranslation()
  const { can, user } = useAuth()
  const isSuperAdmin = user?.role === 'superadmin'
  const [searchParams, setSearchParams] = useSearchParams()

  const page = Math.max(1, Number(searchParams.get('page')) || 1)
  const filters = readFilters(searchParams)

  const [filtersOpen, setFiltersOpen] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [editingUser, setEditingUser] = useState<User | null>(null)
  const [membershipUser, setMembershipUser] = useState<User | null>(null)
  const [membershipMode, setMembershipMode] = useState<'toggle' | 'delete'>('toggle')
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())
  const [bulkTagMode, setBulkTagMode] = useState<'assign' | 'remove'>('assign')
  const [bulkTagOpen, setBulkTagOpen] = useState(false)
  const [bulkMembershipMode, setBulkMembershipMode] = useState<BulkMembershipMode | null>(null)
  const [bulkMembershipIds, setBulkMembershipIds] = useState<number[]>([])
  const [bulkBarcodeOpen, setBulkBarcodeOpen] = useState(false)
  const [bulkBarcodePrintOpen, setBulkBarcodePrintOpen] = useState(false)

  const usersQuery = useUsersQuery(page, filters)

  const activeFilters = countActiveFilters(filters)

  if (!can('users.viewAny')) {
    return <p className="text-sm text-muted-foreground">{t('errors.forbidden')}</p>
  }

  const setParams = (patch: Record<string, string | number | null>) => {
    setSearchParams(
      (previous) => {
        const next = new URLSearchParams(previous)
        Object.entries(patch).forEach(([key, value]) => {
          if (value === null || value === '') next.delete(key)
          else next.set(key, String(value))
        })
        return next
      },
      { replace: true },
    )
  }

  const applyFilters = (next: UserFilters) => {
    setParams({ ...next, page: null })
  }

  const clearFilters = () => {
    setParams({ ...EMPTY_USER_FILTERS, page: null })
  }

  const pageIds = (usersQuery.data?.data ?? []).map((item) => item.id)
  const allPageSelected = pageIds.length > 0 && pageIds.every((id) => selectedIds.has(id))
  const somePageSelected = pageIds.some((id) => selectedIds.has(id))

  const toggleSelectAll = () => {
    setSelectedIds((current) => {
      const next = new Set(current)
      if (allPageSelected) {
        pageIds.forEach((id) => next.delete(id))
      } else {
        pageIds.forEach((id) => next.add(id))
      }
      return next
    })
  }

  const toggleRow = (id: number) => {
    setSelectedIds((current) => {
      const next = new Set(current)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  }

  const clearSelection = () => setSelectedIds(new Set())

  const openBulkMembership = (mode: BulkMembershipMode) => {
    if (selectedIds.size === 0) return
    setBulkMembershipIds(Array.from(selectedIds))
    setBulkMembershipMode(mode)
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 className="font-brand-heading text-2xl font-bold tracking-tight">{t('users.title')}</h2>
        {can('users.create') ? (
          <Button
            variant="brand"
            onClick={() => {
              setEditingUser(null)
              setModalOpen(true)
            }}
          >
            <Plus />
            {t('users.add')}
          </Button>
        ) : null}
      </div>

      <div className="flex items-center gap-2">
        <Button
          variant="outline"
          onClick={() => setFiltersOpen((value) => !value)}
          aria-expanded={filtersOpen}
        >
          <Filter />
          {t('users.filters.toggle')}
          {activeFilters > 0 ? <Badge variant="secondary">{activeFilters}</Badge> : null}
        </Button>
      </div>

      {filtersOpen ? (
        <UserFiltersPanel filters={filters} onApply={applyFilters} onClear={clearFilters} />
      ) : null}

      {selectedIds.size > 0 ? (
        <div className="flex flex-wrap items-center gap-2 rounded-lg border bg-muted/40 px-3 py-2">
          <span className="text-sm text-muted-foreground">
            {t('tags.selectedCount', { count: selectedIds.size })}
          </span>
          <div className="ml-auto flex flex-wrap items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={() => {
                setBulkTagMode('assign')
                setBulkTagOpen(true)
              }}
            >
              <Tag />
              {t('tags.bulkAssign')}
            </Button>
            <Button
              variant="outline"
              size="sm"
              onClick={() => {
                setBulkTagMode('remove')
                setBulkTagOpen(true)
              }}
            >
              <Tag />
              {t('tags.bulkRemove')}
            </Button>
            <Button
              variant="outline"
              size="sm"
              onClick={() => setBulkBarcodeOpen(true)}
            >
              <Barcode />
              {t('users.bulkRegenerateBarcode')}
            </Button>
            <Button
              variant="outline"
              size="sm"
              onClick={() => setBulkBarcodePrintOpen(true)}
            >
              <Printer />
              {t('users.bulkPrintBarcode')}
            </Button>
            <Button
              variant="outline"
              size="sm"
              onClick={() => openBulkMembership('remove')}
            >
              {t('membership.removeSelected')}
            </Button>
            {isSuperAdmin ? (
              <Button
                variant="outline"
                size="sm"
                onClick={() => openBulkMembership('deactivate')}
              >
                {t('membership.deactivateSelected')}
              </Button>
            ) : null}
            {isSuperAdmin ? (
              <Button
                variant="outline"
                size="sm"
                onClick={() => openBulkMembership('activate')}
              >
                {t('membership.activateSelected')}
              </Button>
            ) : null}
            {isSuperAdmin ? (
              <Button
                variant="outline"
                size="sm"
                onClick={() => openBulkMembership('bulkDeactivateAccounts')}
              >
                {t('membership.bulkSoftDeleteAccounts')}
              </Button>
            ) : null}
            {isSuperAdmin ? (
              <Button
                variant="destructive"
                size="sm"
                onClick={() => openBulkMembership('bulkForceDeleteAccounts')}
              >
                {t('membership.bulkForceDeleteAccounts')}
              </Button>
            ) : null}
            <Button variant="ghost" size="sm" onClick={clearSelection}>
              {t('tags.clearSelection')}
            </Button>
          </div>
        </div>
      ) : null}

      {usersQuery.isLoading ? (
        <PageLoader />
      ) : (
        <div className="rounded-lg border bg-card">
          <div className="hidden lg:block">
            <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-10 px-4">
                  <Checkbox
                    checked={allPageSelected ? true : somePageSelected ? 'indeterminate' : false}
                    onCheckedChange={toggleSelectAll}
                    aria-label={t('tags.selectAll')}
                  />
                </TableHead>
                <TableHead className="px-4">{t('users.columns.user')}</TableHead>
                <TableHead className="px-4">{t('users.columns.email')}</TableHead>
                <TableHead className="px-4">{t('users.columns.role')}</TableHead>
                <TableHead className="px-4">{t('users.columns.libraries')}</TableHead>
                <TableHead className="px-4">{t('users.columns.barcode')}</TableHead>
                <TableHead className="px-4 text-right">{t('common.actions')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {usersQuery.data?.data.map((item) => (
                <TableRow key={item.id}>
                  <TableCell className="px-4">
                    <Checkbox
                      checked={selectedIds.has(item.id)}
                      onCheckedChange={() => toggleRow(item.id)}
                      aria-label={item.name}
                    />
                  </TableCell>
                  <TableCell className="px-4">
                    <Link to={`/users/${item.id}`} className="font-medium hover:underline">
                      {item.name}
                    </Link>
                    {item.username ? <p className="text-xs text-muted-foreground">@{item.username}</p> : null}
                  </TableCell>
                  <TableCell className="px-4 text-muted-foreground">{item.email}</TableCell>
                  <TableCell className="px-4">
                    <Badge className="border-transparent bg-brand-soft text-brand">{item.role_label}</Badge>
                  </TableCell>
                  <TableCell className="px-4 text-muted-foreground">
                    {item.libraries?.map((library) => library.name).join(', ') || '—'}
                  </TableCell>
                  <TableCell className="px-4 font-mono text-xs text-muted-foreground">{item.bar_code ?? '—'}</TableCell>
                  <TableCell className="px-4">
                    <div className="flex items-center justify-end gap-1">
                      <Button variant="ghost" size="icon-sm" asChild aria-label={t('common.view')}>
                        <Link to={`/users/${item.id}`}>
                          <Eye />
                        </Link>
                      </Button>
                      {item.can?.update ? (
                        <Button
                          variant="ghost"
                          size="icon-sm"
                          aria-label={t('common.edit')}
                          onClick={() => {
                            setEditingUser(item)
                            setModalOpen(true)
                          }}
                        >
                          <Pencil />
                        </Button>
                      ) : null}
                      {item.can?.manageMemberships ? (
                        <Button
                          variant="ghost"
                          size="icon-sm"
                          aria-label={t('membership.toggleTitle')}
                          onClick={() => {
                            setMembershipUser(item)
                            setMembershipMode('toggle')
                          }}
                        >
                          <Power />
                        </Button>
                      ) : null}
                      {item.can?.forceDelete || item.can?.delete || item.can?.removeMemberships ? (
                        <Button
                          variant="ghost"
                          size="icon-sm"
                          aria-label={t('membership.deleteTitle')}
                          onClick={() => {
                            setMembershipUser(item)
                            setMembershipMode('delete')
                          }}
                        >
                          <UserX />
                        </Button>
                      ) : null}
                    </div>
                  </TableCell>
                </TableRow>
              ))}
              {!usersQuery.data?.data.length ? (
                <TableRow>
                  <TableCell colSpan={7} className="h-24 text-center text-muted-foreground">
                    {t('users.empty')}
                  </TableCell>
                </TableRow>
              ) : null}
            </TableBody>
            </Table>
          </div>

          <div className="lg:hidden">
            <UsersMobileList
              users={usersQuery.data?.data ?? []}
              selectedIds={selectedIds}
              onToggleRow={toggleRow}
              onToggleAll={toggleSelectAll}
              allSelected={allPageSelected}
              someSelected={somePageSelected}
              onEdit={(item) => {
                setEditingUser(item)
                setModalOpen(true)
              }}
              onMembershipToggle={(item) => {
                setMembershipUser(item)
                setMembershipMode('toggle')
              }}
              onMembershipDelete={(item) => {
                setMembershipUser(item)
                setMembershipMode('delete')
              }}
            />
          </div>

          {usersQuery.data && usersQuery.data.meta.total > 0 ? (
            <PaginationBar
              currentPage={usersQuery.data.meta.current_page}
              lastPage={usersQuery.data.meta.last_page}
              total={usersQuery.data.meta.total}
              perPage={usersQuery.data.meta.per_page}
              onPageChange={(nextPage) => setParams({ page: nextPage })}
            />
          ) : null}
        </div>
      )}

      {modalOpen ? (
        <UserFormModal
          open={modalOpen}
          user={editingUser}
          onClose={() => {
            setModalOpen(false)
            setEditingUser(null)
          }}
          onSuccess={(label) =>
            toast.success(editingUser ? t('users.updated') : t('users.created'), { description: label })
          }
        />
      ) : null}

      {membershipUser ? (
        <UserMembershipModal
          open={Boolean(membershipUser)}
          user={membershipUser}
          mode={membershipMode}
          onClose={() => setMembershipUser(null)}
          onSuccess={(message) => {
            toast.success(message, { description: membershipUser.email })
            void usersQuery.refetch()
          }}
        />
      ) : null}

      {bulkTagOpen ? (
        <UserTagsBulkModal
          open={bulkTagOpen}
          mode={bulkTagMode}
          userIds={Array.from(selectedIds)}
          onClose={() => setBulkTagOpen(false)}
          onSuccess={(message) => {
            toast.success(message)
            clearSelection()
            void usersQuery.refetch()
          }}
        />
      ) : null}

      {bulkBarcodeOpen ? (
        <UserBarcodeBulkModal
          open={bulkBarcodeOpen}
          userIds={Array.from(selectedIds)}
          onClose={() => setBulkBarcodeOpen(false)}
          onSuccess={(message) => {
            toast.success(message)
            clearSelection()
            void usersQuery.refetch()
          }}
        />
      ) : null}

      {bulkBarcodePrintOpen ? (
        <UserBarcodeBulkPrintModal
          open={bulkBarcodePrintOpen}
          userIds={Array.from(selectedIds)}
          onClose={() => setBulkBarcodePrintOpen(false)}
        />
      ) : null}

      {bulkMembershipMode ? (
        <UserMembershipsBulkModal
          open={Boolean(bulkMembershipMode)}
          mode={bulkMembershipMode}
          userIds={bulkMembershipIds}
          onClose={() => setBulkMembershipMode(null)}
          onSuccess={(message) => {
            toast.success(message)
            clearSelection()
            void usersQuery.refetch()
          }}
        />
      ) : null}
    </div>
  )
}
