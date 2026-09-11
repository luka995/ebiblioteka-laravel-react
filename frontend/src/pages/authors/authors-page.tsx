import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { PenLine, Pencil, Plus, Search, Trash2 } from 'lucide-react'
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
import { LibrarySelect, type LibraryOption } from '@/components/libraries/library-select'
import { AuthorFormModal } from '@/components/authors/author-form-modal'
import { AuthorsMobileList } from '@/components/authors/authors-mobile-list'
import { useAuth } from '@/hooks/useAuth'
import type { Author, PaginatedResponse } from '@/types'

const PAGE_SIZE = 10

function useAuthorsQuery(page: number, search: string, libraryId: string) {
  return useQuery({
    queryKey: ['authors', { page, search, libraryId }],
    queryFn: async () => {
      const query = new URLSearchParams({ per_page: String(PAGE_SIZE), page: String(page) })
      if (search) query.set('search', search)
      if (libraryId) query.set('library_id', libraryId)
      return api.get<PaginatedResponse<Author>>(`${apiPaths.authors}?${query.toString()}`)
    },
  })
}

export function AuthorsPage() {
  const { t } = useTranslation()
  const { can, user } = useAuth()
  const isSuperAdmin = user?.role === 'superadmin'
  const [searchParams, setSearchParams] = useSearchParams()

  const page = Math.max(1, Number(searchParams.get('page')) || 1)
  const search = searchParams.get('q') ?? ''
  const libraryId = searchParams.get('library_id') ?? ''

  const [searchInput, setSearchInput] = useState(search)
  const [filterLibrary, setFilterLibrary] = useState<LibraryOption[]>([])
  const [modalOpen, setModalOpen] = useState(false)
  const [editingAuthor, setEditingAuthor] = useState<Author | null>(null)
  const [deletingAuthor, setDeletingAuthor] = useState<Author | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)

  useEffect(() => {
    const id = libraryId ? Number(libraryId) : null
    setFilterLibrary((current) => {
      if (id === null) return []
      if (current.length === 1 && current[0].id === id) return current
      return [{ id, name: '' }]
    })
  }, [libraryId])

  const authorsQuery = useAuthorsQuery(page, search, libraryId)

  if (!can('authors.viewAny')) {
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

  const applyLibraryFilter = (options: LibraryOption[]) => {
    setFilterLibrary(options)
    setParams({ library_id: options[0] ? String(options[0].id) : null, page: null })
  }

  const confirmDelete = async () => {
    if (!deletingAuthor) return
    setIsDeleting(true)
    try {
      await api.delete(apiPaths.author(deletingAuthor.id))
      const nextPage =
        authorsQuery.data && authorsQuery.data.data.length === 1 && page > 1 ? page - 1 : page
      setParams({ page: nextPage === page ? page : nextPage })
      await authorsQuery.refetch()
      toast.success(t('authors.deleted'), { description: deletingAuthor.name })
      setDeletingAuthor(null)
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
        <h2 className="font-brand-heading text-2xl font-bold tracking-tight">{t('authors.title')}</h2>
        {can('authors.create') ? (
          <Button
            variant="brand"
            onClick={() => {
              setEditingAuthor(null)
              setModalOpen(true)
            }}
          >
            <Plus />
            {t('authors.add')}
          </Button>
        ) : null}
      </div>

      <div className="flex flex-col gap-2 sm:flex-row sm:items-start">
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
            placeholder={t('authors.search')}
            className="max-w-xs"
          />
          <Button variant="outline" type="submit" aria-label={t('common.search')}>
            <Search />
          </Button>
        </form>

        {isSuperAdmin ? (
          <div className="w-full sm:w-72">
            <LibrarySelect
              multiple={false}
              clearable
              value={filterLibrary}
              onChange={applyLibraryFilter}
            />
          </div>
        ) : null}
      </div>

      {authorsQuery.isLoading ? (
        <PageLoader />
      ) : (
        <div className="rounded-lg border bg-card">
          <div className="hidden lg:block">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="px-4">{t('authors.columns.name')}</TableHead>
                  <TableHead className="px-4">{t('authors.columns.library')}</TableHead>
                  <TableHead className="px-4 text-right">{t('common.actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {authorsQuery.data?.data.map((author) => (
                  <TableRow key={author.id}>
                    <TableCell className="px-4">
                      <div className="flex items-center gap-2 font-medium">
                        <PenLine className="size-4 text-muted-foreground" />
                        {author.display_name}
                      </div>
                    </TableCell>
                    <TableCell className="px-4 text-muted-foreground">{author.library_name ?? '—'}</TableCell>
                    <TableCell className="px-4">
                      <div className="flex items-center justify-end gap-1">
                        {author.can?.update ? (
                          <Button
                            variant="ghost"
                            size="icon-sm"
                            aria-label={t('common.edit')}
                            onClick={() => {
                              setEditingAuthor(author)
                              setModalOpen(true)
                            }}
                          >
                            <Pencil />
                          </Button>
                        ) : null}
                        {author.can?.delete ? (
                          <Button
                            variant="ghost"
                            size="icon-sm"
                            aria-label={t('common.delete')}
                            onClick={() => setDeletingAuthor(author)}
                          >
                            <Trash2 />
                          </Button>
                        ) : null}
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
                {!authorsQuery.data?.data.length ? (
                  <TableRow>
                    <TableCell colSpan={3} className="h-24 text-center text-muted-foreground">
                      {t('authors.empty')}
                    </TableCell>
                  </TableRow>
                ) : null}
              </TableBody>
            </Table>
          </div>

          <div className="lg:hidden">
            <AuthorsMobileList
              authors={authorsQuery.data?.data ?? []}
              onEdit={(author) => {
                setEditingAuthor(author)
                setModalOpen(true)
              }}
              onDelete={(author) => setDeletingAuthor(author)}
            />
          </div>

          {authorsQuery.data && authorsQuery.data.meta.total > 0 ? (
            <PaginationBar
              currentPage={authorsQuery.data.meta.current_page}
              lastPage={authorsQuery.data.meta.last_page}
              total={authorsQuery.data.meta.total}
              perPage={authorsQuery.data.meta.per_page}
              onPageChange={(nextPage) => setParams({ page: nextPage })}
            />
          ) : null}
        </div>
      )}

      {modalOpen ? (
        <AuthorFormModal
          open={modalOpen}
          author={editingAuthor}
          onClose={() => {
            setModalOpen(false)
            setEditingAuthor(null)
          }}
          onSuccess={(name) =>
            toast.success(editingAuthor ? t('authors.updated') : t('authors.created'), {
              description: name,
            })
          }
        />
      ) : null}

      <AlertDialog open={Boolean(deletingAuthor)} onOpenChange={(value) => !value && setDeletingAuthor(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('authors.deleteConfirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('authors.deleteConfirmText', { name: deletingAuthor?.name ?? '' })}
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
