import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Pencil, Plus, Search, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { laravelBaseUrl, storageUrl } from '@/lib/environment'
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
import { NewsFormModal } from '@/components/news/news-form-modal'
import { NewsMobileList } from '@/components/news/news-mobile-list'
import { useAuth } from '@/hooks/useAuth'
import type { News, PaginatedResponse } from '@/types'

const PAGE_SIZE = 10

function publicNewsUrl(slug: string): string {
  return `${laravelBaseUrl()}/novosti/${slug}`
}

function useNewsQuery(page: number, search: string) {
  return useQuery({
    queryKey: ['news', { page, search }],
    queryFn: async () => {
      const query = new URLSearchParams({ per_page: String(PAGE_SIZE), page: String(page) })
      if (search) query.set('search', search)
      return api.get<PaginatedResponse<News>>(`${apiPaths.news}?${query.toString()}`)
    },
  })
}

export function NewsPage() {
  const { t } = useTranslation()
  const { can } = useAuth()
  const [searchParams, setSearchParams] = useSearchParams()

  const page = Math.max(1, Number(searchParams.get('page')) || 1)
  const search = searchParams.get('q') ?? ''

  const [searchInput, setSearchInput] = useState(search)
  const [modalOpen, setModalOpen] = useState(false)
  const [editingNews, setEditingNews] = useState<News | null>(null)
  const [deletingNews, setDeletingNews] = useState<News | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)

  useEffect(() => {
    setSearchInput(search)
  }, [search])

  const newsQuery = useNewsQuery(page, search)

  if (!can('news.viewAny')) {
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
    if (!deletingNews) return
    setIsDeleting(true)
    try {
      await api.delete(apiPaths.newsItem(deletingNews.id))
      const nextPage =
        newsQuery.data && newsQuery.data.data.length === 1 && page > 1 ? page - 1 : page
      setParams({ page: nextPage === page ? page : nextPage })
      await newsQuery.refetch()
      toast.success(t('news.deleted'), { description: deletingNews.title })
      setDeletingNews(null)
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
        <h2 className="font-brand-heading text-2xl font-bold tracking-tight">{t('news.title')}</h2>
        {can('news.create') ? (
          <Button
            variant="brand"
            onClick={() => {
              setEditingNews(null)
              setModalOpen(true)
            }}
          >
            <Plus />
            {t('news.add')}
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
            placeholder={t('news.search')}
            className="max-w-xs"
          />
          <Button variant="outline" type="submit" aria-label={t('common.search')}>
            <Search />
          </Button>
        </form>
      </div>

      {newsQuery.isLoading ? (
        <PageLoader />
      ) : (
        <div className="rounded-lg border bg-card">
          <div className="hidden lg:block">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="px-4">{t('news.columns.image')}</TableHead>
                  <TableHead className="px-4">{t('news.columns.title')}</TableHead>
                  <TableHead className="px-4">{t('news.columns.date')}</TableHead>
                  <TableHead className="px-4 text-right">{t('common.actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {newsQuery.data?.data.map((news) => (
                  <TableRow key={news.id}>
                    <TableCell className="px-4">
                      {news.image_url ? (
                        <img src={storageUrl(news.image_url) ?? undefined} alt="" className="h-10 w-16 rounded object-cover" />
                      ) : (
                        <div className="h-10 w-16 rounded bg-muted" />
                      )}
                    </TableCell>
                    <TableCell className="px-4">
                      <a
                        href={publicNewsUrl(news.slug)}
                        target="_blank"
                        rel="noreferrer"
                        className="font-medium text-brand underline-offset-4 hover:underline"
                        title={t('news.openPublic')}
                      >
                        {news.title}
                      </a>
                    </TableCell>
                    <TableCell className="px-4 text-muted-foreground">{news.date_formatted}</TableCell>
                    <TableCell className="px-4">
                      <div className="flex items-center justify-end gap-1">
                        {news.can?.update ? (
                          <Button
                            variant="ghost"
                            size="icon-sm"
                            aria-label={t('common.edit')}
                            onClick={() => {
                              setEditingNews(news)
                              setModalOpen(true)
                            }}
                          >
                            <Pencil />
                          </Button>
                        ) : null}
                        {news.can?.delete ? (
                          <Button
                            variant="ghost"
                            size="icon-sm"
                            aria-label={t('common.delete')}
                            onClick={() => setDeletingNews(news)}
                          >
                            <Trash2 />
                          </Button>
                        ) : null}
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
                {!newsQuery.data?.data.length ? (
                  <TableRow>
                    <TableCell colSpan={4} className="h-24 text-center text-muted-foreground">
                      {t('news.empty')}
                    </TableCell>
                  </TableRow>
                ) : null}
              </TableBody>
            </Table>
          </div>

          <div className="lg:hidden">
            <NewsMobileList
              news={newsQuery.data?.data ?? []}
              onEdit={(item) => {
                setEditingNews(item)
                setModalOpen(true)
              }}
              onDelete={(item) => setDeletingNews(item)}
            />
          </div>

          {newsQuery.data && newsQuery.data.meta.total > 0 ? (
            <PaginationBar
              currentPage={newsQuery.data.meta.current_page}
              lastPage={newsQuery.data.meta.last_page}
              total={newsQuery.data.meta.total}
              perPage={newsQuery.data.meta.per_page}
              onPageChange={(nextPage) => setParams({ page: nextPage })}
            />
          ) : null}
        </div>
      )}

      {modalOpen ? (
        <NewsFormModal
          open={modalOpen}
          news={editingNews}
          onClose={() => {
            setModalOpen(false)
            setEditingNews(null)
          }}
          onSuccess={(title) =>
            toast.success(editingNews ? t('news.updated') : t('news.created'), { description: title })
          }
        />
      ) : null}

      <AlertDialog open={Boolean(deletingNews)} onOpenChange={(value) => !value && setDeletingNews(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('news.deleteConfirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('news.deleteConfirmText', { title: deletingNews?.title ?? '' })}
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
