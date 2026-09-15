import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Pencil, Plus, Search, Trash2 } from 'lucide-react'
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
import { BookFormModal } from '@/components/books/book-form-modal'
import { useAuth } from '@/hooks/useAuth'
import type { Book, PaginatedResponse } from '@/types'

const PAGE_SIZE = 10

export function BooksPage() {
  const { t } = useTranslation()
  const { can, activeLibrary, isLoading } = useAuth()
  const [searchParams, setSearchParams] = useSearchParams()

  const page = Math.max(1, Number(searchParams.get('page')) || 1)
  const search = searchParams.get('q') ?? ''
  const isbn = searchParams.get('isbn') ?? ''
  const libraryId = activeLibrary?.id ?? null

  const [searchInput, setSearchInput] = useState(search)
  const [isbnInput, setIsbnInput] = useState(isbn)
  const [modalOpen, setModalOpen] = useState(false)
  const [editingBook, setEditingBook] = useState<Book | null>(null)
  const [deletingBook, setDeletingBook] = useState<Book | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)

  const booksQuery = useQuery({
    queryKey: ['books', { page, search, isbn, libraryId }],
    queryFn: async () => {
      const query = new URLSearchParams({ per_page: String(PAGE_SIZE), page: String(page) })
      if (search) query.set('search', search)
      if (isbn) query.set('isbn', isbn)
      if (libraryId !== null) query.set('library_id', String(libraryId))
      return api.get<PaginatedResponse<Book>>(`${apiPaths.books}?${query.toString()}`)
    },
    enabled: libraryId !== null,
  })

  if (isLoading) {
    return <PageLoader />
  }

  if (!can('books.viewAny')) {
    return <p className="text-sm text-muted-foreground">{t('errors.forbidden')}</p>
  }

  if (libraryId === null) {
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

  const confirmDelete = async () => {
    if (!deletingBook) return
    setIsDeleting(true)
    try {
      await api.delete(apiPaths.book(deletingBook.id))
      const nextPage = booksQuery.data && booksQuery.data.data.length === 1 && page > 1 ? page - 1 : page
      setParams({ page: nextPage })
      await booksQuery.refetch()
      toast.success(t('books.deleted'), { description: deletingBook.name })
      setDeletingBook(null)
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
        <h2 className="font-brand-heading text-2xl font-bold tracking-tight">{t('books.listTitle')}</h2>
        {can('books.create') ? (
          <Button
            variant="brand"
            onClick={() => {
              setEditingBook(null)
              setModalOpen(true)
            }}
          >
            <Plus />
            {t('books.add')}
          </Button>
        ) : null}
      </div>

      <div className="flex flex-col gap-2 sm:flex-row sm:items-start">
        <form
          className="flex flex-col gap-2 sm:flex-row"
          onSubmit={(event) => {
            event.preventDefault()
            setParams({
              q: searchInput.trim() || null,
              isbn: isbnInput.trim() || null,
              page: null,
            })
          }}
        >
          <Input
            value={searchInput}
            onChange={(event) => setSearchInput(event.target.value)}
            placeholder={t('books.search')}
            className="sm:max-w-xs"
          />
          <Input
            value={isbnInput}
            onChange={(event) => setIsbnInput(event.target.value)}
            placeholder={t('books.searchIsbn')}
            className="sm:max-w-xs"
          />
          <Button variant="outline" type="submit" aria-label={t('common.search')}>
            <Search />
          </Button>
        </form>
      </div>

      {booksQuery.isLoading ? (
        <PageLoader />
      ) : (
        <div className="rounded-lg border bg-card">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="px-4">{t('books.columns.name')}</TableHead>
                <TableHead className="px-4">{t('books.columns.authors')}</TableHead>
                <TableHead className="hidden px-4 lg:table-cell">{t('books.columns.categories')}</TableHead>
                <TableHead className="px-4">{t('books.columns.copies')}</TableHead>
                <TableHead className="px-4 text-right">{t('common.actions')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {booksQuery.data?.data.map((book) => (
                <TableRow key={book.id}>
                  <TableCell className="px-4">
                    <Link to={`/books/titles/${book.id}`} className="font-medium hover:text-brand-accent">
                      {book.name}
                    </Link>
                  </TableCell>
                  <TableCell className="px-4 text-muted-foreground">
                    {book.authors?.map((author) => author.display_name).join('; ') || '—'}
                  </TableCell>
                  <TableCell className="hidden px-4 text-muted-foreground lg:table-cell">
                    {[book.category_primary_name, book.category_secondary_name].filter(Boolean).join(' / ') || '—'}
                  </TableCell>
                  <TableCell className="px-4 text-muted-foreground">
                    {book.available_count ?? 0} / {book.copies_count ?? 0}
                  </TableCell>
                  <TableCell className="px-4">
                    <div className="flex items-center justify-end gap-1">
                      {book.can?.update ? (
                        <Button
                          variant="ghost"
                          size="icon-sm"
                          aria-label={t('common.edit')}
                          onClick={() => {
                            setEditingBook(book)
                            setModalOpen(true)
                          }}
                        >
                          <Pencil />
                        </Button>
                      ) : null}
                      {book.can?.delete ? (
                        <Button
                          variant="ghost"
                          size="icon-sm"
                          aria-label={t('common.delete')}
                          onClick={() => setDeletingBook(book)}
                        >
                          <Trash2 />
                        </Button>
                      ) : null}
                    </div>
                  </TableCell>
                </TableRow>
              ))}
              {!booksQuery.data?.data.length ? (
                <TableRow>
                  <TableCell colSpan={5} className="h-24 text-center text-muted-foreground">
                    {t('books.empty')}
                  </TableCell>
                </TableRow>
              ) : null}
            </TableBody>
          </Table>

          {booksQuery.data && booksQuery.data.meta.total > 0 ? (
            <PaginationBar
              currentPage={booksQuery.data.meta.current_page}
              lastPage={booksQuery.data.meta.last_page}
              total={booksQuery.data.meta.total}
              perPage={booksQuery.data.meta.per_page}
              onPageChange={(nextPage) => setParams({ page: nextPage })}
            />
          ) : null}
        </div>
      )}

      {modalOpen ? (
        <BookFormModal
          open={modalOpen}
          book={editingBook}
          onClose={() => {
            setModalOpen(false)
            setEditingBook(null)
          }}
          onSuccess={(name) => toast.success(editingBook ? t('books.updated') : t('books.created'), { description: name })}
        />
      ) : null}

      <AlertDialog open={Boolean(deletingBook)} onOpenChange={(value) => !value && setDeletingBook(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('books.deleteConfirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('books.deleteConfirmText', { name: deletingBook?.name ?? '' })}
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
