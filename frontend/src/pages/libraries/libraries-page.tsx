import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Building2, Eye, Pencil, Plus, RotateCcw, Search, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
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
import { LibraryFormModal } from '@/components/libraries/library-form-modal'
import { useAuth } from '@/hooks/useAuth'
import type { Library, PaginatedResponse } from '@/types'

const PAGE_SIZE = 10

function useLibrariesQuery(page: number, search: string, deleted: boolean) {
  return useQuery({
    queryKey: ['libraries', { page, search, deleted }],
    queryFn: async () => {
      const query = new URLSearchParams({ per_page: String(PAGE_SIZE), page: String(page) })
      if (search) query.set('search', search)
      if (deleted) query.set('deleted', '1')
      return api.get<PaginatedResponse<Library>>(`${apiPaths.libraries}?${query.toString()}`)
    },
  })
}

export function LibrariesPage() {
  const { t } = useTranslation()
  const { can } = useAuth()
  const [searchParams, setSearchParams] = useSearchParams()

  const page = Math.max(1, Number(searchParams.get('page')) || 1)
  const search = searchParams.get('q') ?? ''

  const [searchInput, setSearchInput] = useState(search)
  const [modalOpen, setModalOpen] = useState(false)
  const [editingLibrary, setEditingLibrary] = useState<Library | null>(null)
  const [deletingLibrary, setDeletingLibrary] = useState<Library | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [showDeleted, setShowDeleted] = useState(false)

  const librariesQuery = useLibrariesQuery(page, search, showDeleted)

  if (!can('libraries.viewAny')) {
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
    if (!deletingLibrary) return
    setIsDeleting(true)
    try {
      await api.delete(apiPaths.library(deletingLibrary.id))
      const nextPage =
        librariesQuery.data && librariesQuery.data.data.length === 1 && page > 1 ? page - 1 : page
      setParams({ page: nextPage === page ? page : nextPage })
      await librariesQuery.refetch()
      toast.success(t('libs.deleted'), { description: deletingLibrary.name })
      setDeletingLibrary(null)
    } catch (error) {
      if (error instanceof ApiError) {
        toast.error(error.messageText ?? t('errors.unexpected'))
      }
    } finally {
      setIsDeleting(false)
    }
  }

  const confirmRestore = async (library: Library) => {
    try {
      await api.post(apiPaths.libraryRestore(library.id))
      await librariesQuery.refetch()
      toast.success(t('libs.restored'), { description: library.name })
    } catch (error) {
      if (error instanceof ApiError) {
        toast.error(error.messageText ?? t('errors.unexpected'))
      }
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 className="font-brand-heading text-2xl font-bold tracking-tight">{t('libs.title')}</h2>
        {can('libraries.create') ? (
          <Button
            variant="brand"
            onClick={() => {
              setEditingLibrary(null)
              setModalOpen(true)
            }}
          >
            <Plus />
            {t('libs.add')}
          </Button>
        ) : null}
      </div>

      <div className="flex flex-wrap items-center gap-2">
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
            placeholder={t('libs.search')}
            className="max-w-xs"
          />
          <Button variant="outline" type="submit" aria-label={t('common.search')}>
            <Search />
          </Button>
        </form>
        <Button
          variant={showDeleted ? 'secondary' : 'outline'}
          onClick={() => {
            setShowDeleted((value) => !value)
            setParams({ page: null })
          }}
        >
          {t('libs.showDeleted')}
        </Button>
      </div>

      {librariesQuery.isLoading ? (
        <PageLoader />
      ) : (
        <div className="rounded-lg border bg-card">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="px-4">{t('libs.columns.name')}</TableHead>
                <TableHead className="px-4">{t('libs.columns.place')}</TableHead>
                <TableHead className="px-4">{t('libs.columns.region')}</TableHead>
                <TableHead className="px-4">{t('libs.columns.address')}</TableHead>
                <TableHead className="px-4">{t('libs.columns.workTime')}</TableHead>
                <TableHead className="px-4 text-right">{t('common.actions')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {librariesQuery.data?.data.map((library) => (
                <TableRow key={library.id}>
                  <TableCell className="px-4">
                    <div className="flex items-center gap-2 font-medium">
                      <Building2 className="size-4 text-muted-foreground" />
                      <Link to={`/libraries/${library.id}`} className="hover:underline">
                        {library.name}
                      </Link>
                      {library.deleted ? (
                        <Badge variant="destructive">{t('libs.deletedBadge')}</Badge>
                      ) : null}
                    </div>
                  </TableCell>
                  <TableCell className="px-4">{library.place?.name ?? '—'}</TableCell>
                  <TableCell className="px-4 text-muted-foreground">{library.place?.region?.name ?? '—'}</TableCell>
                  <TableCell className="px-4 text-muted-foreground">{library.address}</TableCell>
                  <TableCell className="px-4 text-muted-foreground">{library.work_time ?? '—'}</TableCell>
                  <TableCell className="px-4">
                    <div className="flex items-center justify-end gap-1">
                      {library.deleted ? (
                        library.can?.restore ? (
                          <Button
                            variant="ghost"
                            size="icon-sm"
                            aria-label={t('libs.restore')}
                            onClick={() => void confirmRestore(library)}
                          >
                            <RotateCcw />
                          </Button>
                        ) : null
                      ) : (
                        <>
                          <Button variant="ghost" size="icon-sm" asChild aria-label={t('common.view')}>
                            <Link to={`/libraries/${library.id}`}>
                              <Eye />
                            </Link>
                          </Button>
                          {library.can?.update ? (
                            <Button
                              variant="ghost"
                              size="icon-sm"
                              aria-label={t('common.edit')}
                              onClick={() => {
                                setEditingLibrary(library)
                                setModalOpen(true)
                              }}
                            >
                              <Pencil />
                            </Button>
                          ) : null}
                          {library.can?.delete ? (
                            <Button
                              variant="ghost"
                              size="icon-sm"
                              aria-label={t('common.delete')}
                              onClick={() => setDeletingLibrary(library)}
                            >
                              <Trash2 />
                            </Button>
                          ) : null}
                        </>
                      )}
                    </div>
                  </TableCell>
                </TableRow>
              ))}
              {!librariesQuery.data?.data.length ? (
                <TableRow>
                  <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                    {t('libs.empty')}
                  </TableCell>
                </TableRow>
              ) : null}
            </TableBody>
          </Table>

          {librariesQuery.data && librariesQuery.data.meta.total > 0 ? (
            <PaginationBar
              currentPage={librariesQuery.data.meta.current_page}
              lastPage={librariesQuery.data.meta.last_page}
              total={librariesQuery.data.meta.total}
              perPage={librariesQuery.data.meta.per_page}
              onPageChange={(nextPage) => setParams({ page: nextPage })}
            />
          ) : null}
        </div>
      )}

      {modalOpen ? (
        <LibraryFormModal
          open={modalOpen}
          library={editingLibrary}
          onClose={() => {
            setModalOpen(false)
            setEditingLibrary(null)
          }}
          onSuccess={(name) =>
            toast.success(editingLibrary ? t('libs.updated') : t('libs.created'), { description: name })
          }
        />
      ) : null}

      <AlertDialog open={Boolean(deletingLibrary)} onOpenChange={(value) => !value && setDeletingLibrary(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('libs.deleteConfirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('libs.deleteConfirmText', { name: deletingLibrary?.name ?? '' })}
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
