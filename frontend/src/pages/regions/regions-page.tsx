import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Map, Pencil, Plus, Search, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { PageLoader } from '@/components/ui/loader'
import { PaginationBar } from '@/components/pagination-bar'
import { RegionFormModal } from '@/components/regions/region-form-modal'
import { RegionsMobileList } from '@/components/regions/regions-mobile-list'
import { useAuth } from '@/hooks/useAuth'
import type { PaginatedResponse, Region } from '@/types'

const PAGE_SIZE = 10

function useRegionsQuery(page: number, search: string) {
  return useQuery({
    queryKey: ['regions', { page, search }],
    queryFn: async () => {
      const query = new URLSearchParams({ per_page: String(PAGE_SIZE), page: String(page) })
      if (search) query.set('search', search)
      return api.get<PaginatedResponse<Region>>(`${apiPaths.regions}?${query.toString()}`)
    },
  })
}

export function RegionsPage() {
  const { t } = useTranslation()
  const { can } = useAuth()
  const [searchParams, setSearchParams] = useSearchParams()

  const page = Math.max(1, Number(searchParams.get('page')) || 1)
  const search = searchParams.get('q') ?? ''

  const [searchInput, setSearchInput] = useState(search)
  const [modalOpen, setModalOpen] = useState(false)
  const [editingRegion, setEditingRegion] = useState<Region | null>(null)
  const [deletingRegion, setDeletingRegion] = useState<Region | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)

  const regionsQuery = useRegionsQuery(page, search)

  if (!can('regions.viewAny')) {
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

  const applySearch = (value: string) => {
    setSearchInput(value)
    setParams({ q: value.trim() || null, page: null })
  }

  const confirmDelete = async () => {
    if (!deletingRegion) return
    setIsDeleting(true)
    try {
      await api.delete(apiPaths.region(deletingRegion.id))
      const nextPage =
        regionsQuery.data && regionsQuery.data.data.length === 1 && page > 1 ? page - 1 : page
      setParams({ page: nextPage === page ? page : nextPage })
      await regionsQuery.refetch()
      toast.success(t('regions.deleted'), { description: deletingRegion.name })
      setDeletingRegion(null)
    } catch (error) {
      if (error instanceof ApiError) {
        toast.error(error.messageText ?? t('errors.unexpected'))
      }
    } finally {
      setIsDeleting(false)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 className="font-brand-heading text-2xl font-bold tracking-tight">{t('regions.title')}</h2>
        {can('regions.create') ? (
          <Button
            variant="brand"
            onClick={() => {
              setEditingRegion(null)
              setModalOpen(true)
            }}
          >
            <Plus />
            {t('regions.add')}
          </Button>
        ) : null}
      </div>

      <form
        className="flex gap-2"
        onSubmit={(event) => {
          event.preventDefault()
          applySearch(searchInput)
        }}
      >
        <Input
          value={searchInput}
          onChange={(event) => setSearchInput(event.target.value)}
          placeholder={t('regions.search')}
          className="max-w-xs"
        />
        <Button variant="outline" type="submit" aria-label={t('common.search')}>
          <Search />
        </Button>
      </form>

      {regionsQuery.isLoading ? (
        <PageLoader />
      ) : (
        <div className="rounded-lg border bg-card">
          <div className="hidden lg:block">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="px-4">{t('regions.columns.name')}</TableHead>
                <TableHead className="px-4">{t('regions.columns.placesCount')}</TableHead>
                <TableHead className="px-4 text-right">{t('common.actions')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {regionsQuery.data?.data.map((region) => (
                <TableRow key={region.id}>
                  <TableCell className="px-4">
                    <div className="flex items-center gap-2 font-medium">
                      <Map className="size-4 text-muted-foreground" />
                      {region.name}
                    </div>
                  </TableCell>
                  <TableCell className="px-4 text-muted-foreground">{region.places_count ?? 0}</TableCell>
                  <TableCell className="px-4">
                    <div className="flex items-center justify-end gap-1">
                      {region.can?.update ? (
                        <Button
                          variant="ghost"
                          size="icon-sm"
                          aria-label={t('common.edit')}
                          onClick={() => {
                            setEditingRegion(region)
                            setModalOpen(true)
                          }}
                        >
                          <Pencil />
                        </Button>
                      ) : null}
                      {region.can?.delete ? (
                        <Button
                          variant="ghost"
                          size="icon-sm"
                          aria-label={t('common.delete')}
                          onClick={() => setDeletingRegion(region)}
                        >
                          <Trash2 />
                        </Button>
                      ) : null}
                    </div>
                  </TableCell>
                </TableRow>
              ))}
              {!regionsQuery.data?.data.length ? (
                <TableRow>
                  <TableCell colSpan={3} className="h-24 text-center text-muted-foreground">
                    {t('regions.empty')}
                  </TableCell>
                </TableRow>
              ) : null}
            </TableBody>
          </Table>
          </div>

          <div className="lg:hidden">
            <RegionsMobileList
              regions={regionsQuery.data?.data ?? []}
              onEdit={(region) => {
                setEditingRegion(region)
                setModalOpen(true)
              }}
              onDelete={(region) => setDeletingRegion(region)}
            />
          </div>

          {regionsQuery.data && regionsQuery.data.meta.total > 0 ? (
            <PaginationBar
              currentPage={regionsQuery.data.meta.current_page}
              lastPage={regionsQuery.data.meta.last_page}
              total={regionsQuery.data.meta.total}
              perPage={regionsQuery.data.meta.per_page}
              onPageChange={(nextPage) => setParams({ page: nextPage })}
            />
          ) : null}
        </div>
      )}

      {modalOpen ? (
        <RegionFormModal
          open={modalOpen}
          region={editingRegion}
          onClose={() => {
            setModalOpen(false)
            setEditingRegion(null)
          }}
          onSuccess={(name) =>
            toast.success(editingRegion ? t('regions.updated') : t('regions.created'), { description: name })
          }
        />
      ) : null}

      <AlertDialog open={Boolean(deletingRegion)} onOpenChange={(value) => !value && setDeletingRegion(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('regions.deleteConfirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('regions.deleteConfirmText', { name: deletingRegion?.name ?? '' })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={isDeleting}>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction variant="destructive" disabled={isDeleting} onClick={() => void confirmDelete()}>
              {t('common.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
