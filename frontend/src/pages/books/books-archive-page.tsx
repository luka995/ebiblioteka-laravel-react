import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { ArchiveRestore, RefreshCw, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { PageLoader } from '@/components/ui/loader'
import {
  ArchivedBooksMobileList,
  ArchivedCopiesMobileList,
} from '@/components/books/books-archive-mobile-lists'
import { useAuth } from '@/hooks/useAuth'
import type { Book, BookCopy, PaginatedResponse } from '@/types'

export function BooksArchivePage() {
  const { t } = useTranslation()
  const { can, activeLibrary } = useAuth()
  const queryClient = useQueryClient()
  const [isSyncing, setIsSyncing] = useState(false)

  const booksQuery = useQuery({
    queryKey: ['books', 'archive', activeLibrary?.id ?? null],
    queryFn: () => api.get<PaginatedResponse<Book>>(`${apiPaths.bookArchive}?per_page=50`),
    enabled: activeLibrary !== null,
  })

  const copiesQuery = useQuery({
    queryKey: ['book-copies', 'archive', activeLibrary?.id ?? null],
    queryFn: () => api.get<PaginatedResponse<BookCopy>>(`${apiPaths.bookCopiesArchive}?per_page=50`),
    enabled: activeLibrary !== null,
  })

  if (!can('books.viewAny')) {
    return <p className="text-sm text-muted-foreground">{t('errors.forbidden')}</p>
  }

  if (activeLibrary === null) {
    return <p className="text-sm text-muted-foreground">{t('books.activeLibraryRequired')}</p>
  }

  const refresh = () => {
    void queryClient.invalidateQueries({ queryKey: ['books'] })
    void queryClient.invalidateQueries({ queryKey: ['book-copies'] })
  }

  const restoreBook = async (book: Book) => {
    try {
      await api.post(apiPaths.bookRestore(book.id))
      refresh()
      toast.success(t('books.archive.restored'))
    } catch (error) {
      if (error instanceof ApiError) toast.error(error.messageText ?? t('errors.unexpected'))
    }
  }

  const forceBook = async (book: Book) => {
    try {
      await api.delete(apiPaths.bookForce(book.id))
      refresh()
      toast.success(t('books.archive.forceDeleted'))
    } catch (error) {
      if (error instanceof ApiError) toast.error(error.messageText ?? t('errors.unexpected'))
    }
  }

  const restoreCopy = async (copy: BookCopy) => {
    try {
      await api.post(apiPaths.bookCopyRestore(copy.id))
      refresh()
      toast.success(t('books.archive.restored'))
    } catch (error) {
      if (error instanceof ApiError) toast.error(error.messageText ?? t('errors.unexpected'))
    }
  }

  const forceCopy = async (copy: BookCopy) => {
    try {
      await api.delete(apiPaths.bookCopyForce(copy.id))
      refresh()
      toast.success(t('books.archive.forceDeleted'))
    } catch (error) {
      if (error instanceof ApiError) toast.error(error.messageText ?? t('errors.unexpected'))
    }
  }

  const syncSequence = async () => {
    setIsSyncing(true)
    try {
      const result = await api.post<{ last_number: number; next_auto: number }>(apiPaths.inventorySequenceSync)
      toast.success(t('books.archive.synced', { next: result.next_auto }))
    } catch (error) {
      if (error instanceof ApiError) toast.error(error.messageText ?? t('errors.unexpected'))
    } finally {
      setIsSyncing(false)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="font-brand-heading text-2xl font-bold tracking-tight">{t('books.archive.title')}</h2>
          <p className="mt-1 text-sm text-muted-foreground">{t('books.archive.subtitle')}</p>
        </div>
        <Button variant="outline" onClick={() => void syncSequence()} disabled={isSyncing}>
          <RefreshCw />
          {t('books.archive.sync')}
        </Button>
      </div>

      <Tabs defaultValue="books">
        <TabsList>
          <TabsTrigger value="books">{t('books.cards.titlesTitle')}</TabsTrigger>
          <TabsTrigger value="copies">{t('books.cards.copiesTitle')}</TabsTrigger>
        </TabsList>

        <TabsContent value="books" className="mt-4">
          {booksQuery.isLoading ? (
            <PageLoader />
          ) : (
            <div className="rounded-lg border bg-card">
              <div className="hidden lg:block">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="px-4">{t('books.columns.name')}</TableHead>
                    <TableHead className="px-4">{t('books.columns.authors')}</TableHead>
                    <TableHead className="px-4 text-right">{t('common.actions')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {booksQuery.data?.data.map((book) => (
                    <TableRow key={book.id}>
                      <TableCell className="px-4 font-medium">{book.name}</TableCell>
                      <TableCell className="px-4 text-muted-foreground">
                        {book.authors?.map((author) => author.display_name).join('; ') || '—'}
                      </TableCell>
                      <TableCell className="px-4">
                        <div className="flex justify-end gap-1">
                          {book.can?.restore ? (
                            <Button variant="ghost" size="icon-sm" aria-label={t('books.archive.restore')} onClick={() => void restoreBook(book)}>
                              <ArchiveRestore />
                            </Button>
                          ) : null}
                          {book.can?.forceDelete ? (
                            <Button variant="ghost" size="icon-sm" aria-label={t('books.archive.forceDelete')} onClick={() => void forceBook(book)}>
                              <Trash2 />
                            </Button>
                          ) : null}
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                  {!booksQuery.data?.data.length ? (
                    <TableRow>
                      <TableCell colSpan={3} className="h-24 text-center text-muted-foreground">
                        {t('books.archive.empty')}
                      </TableCell>
                    </TableRow>
                  ) : null}
                </TableBody>
              </Table>
              </div>

              <div className="lg:hidden">
                <ArchivedBooksMobileList
                  books={booksQuery.data?.data ?? []}
                  onRestore={(book) => void restoreBook(book)}
                  onForceDelete={(book) => void forceBook(book)}
                />
              </div>
            </div>
          )}
        </TabsContent>

        <TabsContent value="copies" className="mt-4">
          {copiesQuery.isLoading ? (
            <PageLoader />
          ) : (
            <div className="rounded-lg border bg-card">
              <div className="hidden lg:block">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="px-4">{t('books.copies.invNumber')}</TableHead>
                    <TableHead className="px-4">{t('books.columns.name')}</TableHead>
                    <TableHead className="px-4">{t('books.copies.barcode')}</TableHead>
                    <TableHead className="px-4 text-right">{t('common.actions')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {copiesQuery.data?.data.map((copy) => (
                    <TableRow key={copy.id}>
                      <TableCell className="px-4 font-medium">{copy.order_number ?? '—'}</TableCell>
                      <TableCell className="px-4">{copy.book_name ?? '—'}</TableCell>
                      <TableCell className="px-4 font-mono text-xs text-muted-foreground">{copy.barcode ?? '—'}</TableCell>
                      <TableCell className="px-4">
                        <div className="flex justify-end gap-1">
                          {copy.can?.restore ? (
                            <Button variant="ghost" size="icon-sm" aria-label={t('books.archive.restore')} onClick={() => void restoreCopy(copy)}>
                              <ArchiveRestore />
                            </Button>
                          ) : null}
                          {copy.can?.forceDelete ? (
                            <Button variant="ghost" size="icon-sm" aria-label={t('books.archive.forceDelete')} onClick={() => void forceCopy(copy)}>
                              <Trash2 />
                            </Button>
                          ) : null}
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                  {!copiesQuery.data?.data.length ? (
                    <TableRow>
                      <TableCell colSpan={4} className="h-24 text-center text-muted-foreground">
                        {t('books.archive.empty')}
                      </TableCell>
                    </TableRow>
                  ) : null}
                </TableBody>
              </Table>
              </div>

              <div className="lg:hidden">
                <ArchivedCopiesMobileList
                  copies={copiesQuery.data?.data ?? []}
                  onRestore={(copy) => void restoreCopy(copy)}
                  onForceDelete={(copy) => void forceCopy(copy)}
                />
              </div>
            </div>
          )}
        </TabsContent>
      </Tabs>
    </div>
  )
}
