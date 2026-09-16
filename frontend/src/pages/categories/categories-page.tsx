import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { FolderTree, Pencil, Plus, Search, Trash2 } from 'lucide-react'
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
import { CategoryFormModal } from '@/components/categories/category-form-modal'
import { CategoriesMobileList } from '@/components/categories/categories-mobile-list'
import { useAuth } from '@/hooks/useAuth'
import type { Category, PaginatedResponse } from '@/types'

const PAGE_SIZE = 10

function useCategoriesQuery(page: number, search: string, libraryId: string, activeLibraryId: number | null) {
  return useQuery({
    queryKey: ['categories', { page, search, libraryId, activeLibraryId }],
    queryFn: async () => {
      const query = new URLSearchParams({ per_page: String(PAGE_SIZE), page: String(page) })
      if (search) query.set('search', search)
      if (libraryId) query.set('library_id', libraryId)
      return api.get<PaginatedResponse<Category>>(`${apiPaths.categories}?${query.toString()}`)
    },
    enabled: activeLibraryId !== null,
  })
}

export function CategoriesPage() {
  const { t } = useTranslation()
  const { can, user, activeLibrary } = useAuth()
  const isSuperAdmin = user?.role === 'superadmin'
  const [searchParams, setSearchParams] = useSearchParams()

  const page = Math.max(1, Number(searchParams.get('page')) || 1)
  const search = searchParams.get('q') ?? ''
  const libraryId = searchParams.get('library_id') ?? ''

  const [searchInput, setSearchInput] = useState(search)
  const [filterLibrary, setFilterLibrary] = useState<LibraryOption[]>([])
  const [modalOpen, setModalOpen] = useState(false)
  const [editingCategory, setEditingCategory] = useState<Category | null>(null)
  const [deletingCategory, setDeletingCategory] = useState<Category | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)

  useEffect(() => {
    const id = libraryId ? Number(libraryId) : null
    setFilterLibrary((current) => {
      if (id === null) return []
      if (current.length === 1 && current[0].id === id) return current
      return [{ id, name: '' }]
    })
  }, [libraryId])

  const categoriesQuery = useCategoriesQuery(page, search, libraryId, activeLibrary?.id ?? null)

  if (!can('categories.viewAny')) {
    return <p className="text-sm text-muted-foreground">{t('errors.forbidden')}</p>
  }

  if (activeLibrary === null) {
    return <p className="text-sm text-muted-foreground">{t('books.activeLibraryRequired')}</p>
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
    if (!deletingCategory) return
    setIsDeleting(true)
    try {
      await api.delete(apiPaths.category(deletingCategory.id))
      const nextPage =
        categoriesQuery.data && categoriesQuery.data.data.length === 1 && page > 1 ? page - 1 : page
      setParams({ page: nextPage === page ? page : nextPage })
      await categoriesQuery.refetch()
      toast.success(t('categories.deleted'), { description: deletingCategory.name })
      setDeletingCategory(null)
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
        <h2 className="font-brand-heading text-2xl font-bold tracking-tight">{t('categories.title')}</h2>
        {can('categories.create') ? (
          <Button
            variant="brand"
            onClick={() => {
              setEditingCategory(null)
              setModalOpen(true)
            }}
          >
            <Plus />
            {t('categories.add')}
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
            placeholder={t('categories.search')}
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

      {categoriesQuery.isLoading ? (
        <PageLoader />
      ) : (
        <div className="rounded-lg border bg-card">
          <div className="hidden lg:block">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="px-4">{t('categories.columns.name')}</TableHead>
                  <TableHead className="px-4">{t('categories.columns.library')}</TableHead>
                  <TableHead className="px-4">{t('categories.columns.children')}</TableHead>
                  <TableHead className="px-4 text-right">{t('common.actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {categoriesQuery.data?.data.map((category) => (
                  <TableRow key={category.id}>
                    <TableCell className="px-4">
                      <div className="flex items-center gap-2 font-medium">
                        <FolderTree className="size-4 text-muted-foreground" />
                        {category.full_name ?? category.name}
                      </div>
                    </TableCell>
                    <TableCell className="px-4 text-muted-foreground">{category.library_name ?? '—'}</TableCell>
                    <TableCell className="px-4 text-muted-foreground">{category.children_count ?? 0}</TableCell>
                    <TableCell className="px-4">
                      <div className="flex items-center justify-end gap-1">
                        {category.can?.update ? (
                          <Button
                            variant="ghost"
                            size="icon-sm"
                            aria-label={t('common.edit')}
                            onClick={() => {
                              setEditingCategory(category)
                              setModalOpen(true)
                            }}
                          >
                            <Pencil />
                          </Button>
                        ) : null}
                        {category.can?.delete ? (
                          <Button
                            variant="ghost"
                            size="icon-sm"
                            aria-label={t('common.delete')}
                            onClick={() => setDeletingCategory(category)}
                          >
                            <Trash2 />
                          </Button>
                        ) : null}
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
                {!categoriesQuery.data?.data.length ? (
                  <TableRow>
                    <TableCell colSpan={4} className="h-24 text-center text-muted-foreground">
                      {t('categories.empty')}
                    </TableCell>
                  </TableRow>
                ) : null}
              </TableBody>
            </Table>
          </div>

          <div className="lg:hidden">
            <CategoriesMobileList
              categories={categoriesQuery.data?.data ?? []}
              onEdit={(category) => {
                setEditingCategory(category)
                setModalOpen(true)
              }}
              onDelete={(category) => setDeletingCategory(category)}
            />
          </div>

          {categoriesQuery.data && categoriesQuery.data.meta.total > 0 ? (
            <PaginationBar
              currentPage={categoriesQuery.data.meta.current_page}
              lastPage={categoriesQuery.data.meta.last_page}
              total={categoriesQuery.data.meta.total}
              perPage={categoriesQuery.data.meta.per_page}
              onPageChange={(nextPage) => setParams({ page: nextPage })}
            />
          ) : null}
        </div>
      )}

      {modalOpen ? (
        <CategoryFormModal
          open={modalOpen}
          category={editingCategory}
          onClose={() => {
            setModalOpen(false)
            setEditingCategory(null)
          }}
          onSuccess={(name) =>
            toast.success(editingCategory ? t('categories.updated') : t('categories.created'), {
              description: name,
            })
          }
        />
      ) : null}

      <AlertDialog open={Boolean(deletingCategory)} onOpenChange={(value) => !value && setDeletingCategory(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('categories.deleteConfirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('categories.deleteConfirmText', { name: deletingCategory?.name ?? '' })}
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
