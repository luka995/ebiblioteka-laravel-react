import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { MapPin, Pencil, Plus, Search, Trash2 } from 'lucide-react'
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { PlaceFormModal } from '@/components/places/place-form-modal'
import { PlacesMobileList } from '@/components/places/places-mobile-list'
import { useAuth } from '@/hooks/useAuth'
import type { PaginatedResponse, Place, Region } from '@/types'

const PAGE_SIZE = 10

function usePlacesQuery(page: number, search: string, regionId: string) {
  return useQuery({
    queryKey: ['places', { page, search, regionId }],
    queryFn: async () => {
      const query = new URLSearchParams({ per_page: String(PAGE_SIZE), page: String(page) })
      if (search) query.set('search', search)
      if (regionId) query.set('region_id', regionId)
      return api.get<PaginatedResponse<Place>>(`${apiPaths.places}?${query.toString()}`)
    },
  })
}

export function PlacesPage() {
  const { t } = useTranslation()
  const { can } = useAuth()
  const [searchParams, setSearchParams] = useSearchParams()

  const page = Math.max(1, Number(searchParams.get('page')) || 1)
  const search = searchParams.get('q') ?? ''
  const regionId = searchParams.get('region_id') ?? ''

  const [searchInput, setSearchInput] = useState(search)
  const [modalOpen, setModalOpen] = useState(false)
  const [editingPlace, setEditingPlace] = useState<Place | null>(null)
  const [deletingPlace, setDeletingPlace] = useState<Place | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)

  const placesQuery = usePlacesQuery(page, search, regionId)

  const regionsQuery = useQuery({
    queryKey: ['regions', 'options'],
    queryFn: async () => {
      const response = await api.get<PaginatedResponse<Region>>(`${apiPaths.regions}?all=1`)
      return response.data
    },
  })

  if (!can('places.viewAny')) {
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
    if (!deletingPlace) return
    setIsDeleting(true)
    try {
      await api.delete(apiPaths.place(deletingPlace.id))
      const nextPage =
        placesQuery.data && placesQuery.data.data.length === 1 && page > 1 ? page - 1 : page
      setParams({ page: nextPage === page ? page : nextPage })
      await placesQuery.refetch()
      toast.success(t('places.deleted'), { description: deletingPlace.name })
      setDeletingPlace(null)
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
        <h2 className="font-brand-heading text-2xl font-bold tracking-tight">{t('places.title')}</h2>
        {can('places.create') ? (
          <Button
            variant="brand"
            onClick={() => {
              setEditingPlace(null)
              setModalOpen(true)
            }}
          >
            <Plus />
            {t('places.add')}
          </Button>
        ) : null}
      </div>

      <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
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
            placeholder={t('places.search')}
            className="max-w-xs"
          />
          <Button variant="outline" type="submit" aria-label={t('common.search')}>
            <Search />
          </Button>
        </form>

        <Select
          value={regionId || 'all'}
          onValueChange={(value) => setParams({ region_id: value === 'all' ? null : value, page: null })}
        >
          <SelectTrigger className="w-full sm:w-52">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">{t('places.filterRegion')}</SelectItem>
            {regionsQuery.data?.map((region) => (
              <SelectItem key={region.id} value={String(region.id)}>
                {region.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {placesQuery.isLoading ? (
        <PageLoader />
      ) : (
        <div className="rounded-lg border bg-card">
          <div className="hidden lg:block">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="px-4">{t('places.columns.name')}</TableHead>
                <TableHead className="px-4">{t('places.columns.region')}</TableHead>
                <TableHead className="px-4 text-right">{t('common.actions')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {placesQuery.data?.data.map((place) => (
                <TableRow key={place.id}>
                  <TableCell className="px-4">
                    <div className="flex items-center gap-2 font-medium">
                      <MapPin className="size-4 text-muted-foreground" />
                      {place.name}
                    </div>
                  </TableCell>
                  <TableCell className="px-4 text-muted-foreground">{place.region_name ?? '—'}</TableCell>
                  <TableCell className="px-4">
                    <div className="flex items-center justify-end gap-1">
                      {place.can?.update ? (
                        <Button
                          variant="ghost"
                          size="icon-sm"
                          aria-label={t('common.edit')}
                          onClick={() => {
                            setEditingPlace(place)
                            setModalOpen(true)
                          }}
                        >
                          <Pencil />
                        </Button>
                      ) : null}
                      {place.can?.delete ? (
                        <Button
                          variant="ghost"
                          size="icon-sm"
                          aria-label={t('common.delete')}
                          onClick={() => setDeletingPlace(place)}
                        >
                          <Trash2 />
                        </Button>
                      ) : null}
                    </div>
                  </TableCell>
                </TableRow>
              ))}
              {!placesQuery.data?.data.length ? (
                <TableRow>
                  <TableCell colSpan={3} className="h-24 text-center text-muted-foreground">
                    {t('places.empty')}
                  </TableCell>
                </TableRow>
              ) : null}
            </TableBody>
          </Table>
          </div>

          <div className="lg:hidden">
            <PlacesMobileList
              places={placesQuery.data?.data ?? []}
              onEdit={(place) => {
                setEditingPlace(place)
                setModalOpen(true)
              }}
              onDelete={(place) => setDeletingPlace(place)}
            />
          </div>

          {placesQuery.data && placesQuery.data.meta.total > 0 ? (
            <PaginationBar
              currentPage={placesQuery.data.meta.current_page}
              lastPage={placesQuery.data.meta.last_page}
              total={placesQuery.data.meta.total}
              perPage={placesQuery.data.meta.per_page}
              onPageChange={(nextPage) => setParams({ page: nextPage })}
            />
          ) : null}
        </div>
      )}

      {modalOpen ? (
        <PlaceFormModal
          open={modalOpen}
          place={editingPlace}
          onClose={() => {
            setModalOpen(false)
            setEditingPlace(null)
          }}
          onSuccess={(name) =>
            toast.success(editingPlace ? t('places.updated') : t('places.created'), { description: name })
          }
        />
      ) : null}

      <AlertDialog open={Boolean(deletingPlace)} onOpenChange={(value) => !value && setDeletingPlace(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('places.deleteConfirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('places.deleteConfirmText', { name: deletingPlace?.name ?? '' })}
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
