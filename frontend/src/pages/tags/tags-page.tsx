import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Pencil, Plus, Search, Tag as TagIcon, Trash2 } from 'lucide-react'
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
import { TagFormModal } from '@/components/tags/tag-form-modal'
import { useAuth } from '@/hooks/useAuth'
import type { Library, PaginatedResponse, Tag } from '@/types'

const PAGE_SIZE = 10

function useTagsQuery(page: number, search: string, libraryId: string) {
  return useQuery({
    queryKey: ['tags', { page, search, libraryId }],
    queryFn: async () => {
      const query = new URLSearchParams({ per_page: String(PAGE_SIZE), page: String(page) })
      if (search) query.set('search', search)
      if (libraryId) query.set('library_id', libraryId)
      return api.get<PaginatedResponse<Tag>>(`${apiPaths.tags}?${query.toString()}`)
    },
  })
}

export function TagsPage() {
  const { t } = useTranslation()
  const { can } = useAuth()
  const [searchParams, setSearchParams] = useSearchParams()

  const page = Math.max(1, Number(searchParams.get('page')) || 1)
  const search = searchParams.get('q') ?? ''
  const libraryId = searchParams.get('library_id') ?? ''

  const [searchInput, setSearchInput] = useState(search)
  const [modalOpen, setModalOpen] = useState(false)
  const [editingTag, setEditingTag] = useState<Tag | null>(null)
  const [deletingTag, setDeletingTag] = useState<Tag | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)

  const tagsQuery = useTagsQuery(page, search, libraryId)

  const librariesQuery = useQuery({
    queryKey: ['libraries', 'options'],
    queryFn: async () => {
      const response = await api.get<PaginatedResponse<Library>>(`${apiPaths.libraries}?all=1`)
      return response.data
    },
  })

  if (!can('tags.viewAny')) {
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
    if (!deletingTag) return
    setIsDeleting(true)
    try {
      await api.delete(apiPaths.tag(deletingTag.id))
      const nextPage =
        tagsQuery.data && tagsQuery.data.data.length === 1 && page > 1 ? page - 1 : page
      setParams({ page: nextPage === page ? page : nextPage })
      await tagsQuery.refetch()
      toast.success(t('tags.deleted'), { description: deletingTag.name })
      setDeletingTag(null)
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
        <h2 className="font-brand-heading text-2xl font-bold tracking-tight">{t('tags.title')}</h2>
        {can('tags.create') ? (
          <Button
            variant="brand"
            onClick={() => {
              setEditingTag(null)
              setModalOpen(true)
            }}
          >
            <Plus />
            {t('tags.add')}
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
            placeholder={t('tags.search')}
            className="max-w-xs"
          />
          <Button variant="outline" type="submit" aria-label={t('common.search')}>
            <Search />
          </Button>
        </form>

        <Select
          value={libraryId || 'all'}
          onValueChange={(value) => setParams({ library_id: value === 'all' ? null : value, page: null })}
        >
          <SelectTrigger className="w-full sm:w-52">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">{t('tags.filterLibrary')}</SelectItem>
            {librariesQuery.data?.map((library) => (
              <SelectItem key={library.id} value={String(library.id)}>
                {library.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {tagsQuery.isLoading ? (
        <PageLoader />
      ) : (
        <div className="rounded-lg border bg-card">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="px-4">{t('tags.columns.name')}</TableHead>
                <TableHead className="px-4">{t('tags.columns.library')}</TableHead>
                <TableHead className="px-4">{t('tags.columns.usersCount')}</TableHead>
                <TableHead className="px-4 text-right">{t('common.actions')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {tagsQuery.data?.data.map((tag) => (
                <TableRow key={tag.id}>
                  <TableCell className="px-4">
                    <div className="flex items-center gap-2 font-medium">
                      <TagIcon className="size-4 text-muted-foreground" />
                      {tag.name}
                    </div>
                  </TableCell>
                  <TableCell className="px-4 text-muted-foreground">{tag.library_name ?? '—'}</TableCell>
                  <TableCell className="px-4 text-muted-foreground">{tag.users_count ?? 0}</TableCell>
                  <TableCell className="px-4">
                    <div className="flex items-center justify-end gap-1">
                      {tag.can?.update ? (
                        <Button
                          variant="ghost"
                          size="icon-sm"
                          aria-label={t('common.edit')}
                          onClick={() => {
                            setEditingTag(tag)
                            setModalOpen(true)
                          }}
                        >
                          <Pencil />
                        </Button>
                      ) : null}
                      {tag.can?.delete ? (
                        <Button
                          variant="ghost"
                          size="icon-sm"
                          aria-label={t('common.delete')}
                          onClick={() => setDeletingTag(tag)}
                        >
                          <Trash2 />
                        </Button>
                      ) : null}
                    </div>
                  </TableCell>
                </TableRow>
              ))}
              {!tagsQuery.data?.data.length ? (
                <TableRow>
                  <TableCell colSpan={4} className="h-24 text-center text-muted-foreground">
                    {t('tags.empty')}
                  </TableCell>
                </TableRow>
              ) : null}
            </TableBody>
          </Table>

          {tagsQuery.data && tagsQuery.data.meta.total > 0 ? (
            <PaginationBar
              currentPage={tagsQuery.data.meta.current_page}
              lastPage={tagsQuery.data.meta.last_page}
              total={tagsQuery.data.meta.total}
              perPage={tagsQuery.data.meta.per_page}
              onPageChange={(nextPage) => setParams({ page: nextPage })}
            />
          ) : null}
        </div>
      )}

      {modalOpen ? (
        <TagFormModal
          open={modalOpen}
          tag={editingTag}
          onClose={() => {
            setModalOpen(false)
            setEditingTag(null)
          }}
          onSuccess={(name) =>
            toast.success(editingTag ? t('tags.updated') : t('tags.created'), { description: name })
          }
        />
      ) : null}

      <AlertDialog open={Boolean(deletingTag)} onOpenChange={(value) => !value && setDeletingTag(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('tags.deleteConfirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('tags.deleteConfirmText', { name: deletingTag?.name ?? '' })}
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
