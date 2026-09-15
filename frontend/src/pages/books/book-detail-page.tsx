import { useMemo, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { ArchiveX, ArrowLeft, Eye, FileDown, ImagePlus, MoreHorizontal, Pencil, Plus, Printer, RotateCcw, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { storageUrl } from '@/lib/environment'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { PageLoader } from '@/components/ui/loader'
import { BookFormModal } from '@/components/books/book-form-modal'
import { BookCopiesMobileList } from '@/components/books/book-copies-mobile-list'
import { BookCopyAddModal } from '@/components/books/book-copy-add-modal'
import { BookCopyEditModal } from '@/components/books/book-copy-edit-modal'
import { BookCopyRecErrorDialog, BookCopyWriteOffDialog } from '@/components/books/book-copy-action-dialogs'
import { BookCopyBarcodePrintModal } from '@/components/books/book-copy-barcode-print-modal'
import type { Book, BookCopy, BookCopyStatus, PaginatedResponse } from '@/types'

const STATUS_VARIANTS: Record<BookCopyStatus, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  available: 'default',
  borrowed: 'secondary',
  record_error: 'outline',
  written_off: 'destructive',
  archived: 'secondary',
}

export function BookDetailPage() {
  const { t } = useTranslation()
  const params = useParams()
  const navigate = useNavigate()
  const bookId = Number(params.id)
  const queryClient = useQueryClient()

  const [bookModalOpen, setBookModalOpen] = useState(false)
  const [addModalOpen, setAddModalOpen] = useState(false)
  const [editingCopy, setEditingCopy] = useState<BookCopy | null>(null)
  const [writeOffCopy, setWriteOffCopy] = useState<BookCopy | null>(null)
  const [recErrorCopy, setRecErrorCopy] = useState<BookCopy | null>(null)
  const [selected, setSelected] = useState<number[]>([])
  const [printOpen, setPrintOpen] = useState(false)

  const bookQuery = useQuery({
    queryKey: ['book', bookId],
    queryFn: () => api.get<{ data: Book }>(apiPaths.book(bookId)),
    enabled: Number.isFinite(bookId),
  })

  const copiesQuery = useQuery({
    queryKey: ['book-copies', { bookId }],
    queryFn: () =>
      api.get<PaginatedResponse<BookCopy>>(`${apiPaths.bookCopiesList}?book_id=${bookId}&per_page=100`),
    enabled: Number.isFinite(bookId),
  })

  const book = bookQuery.data?.data
  const copies = useMemo(() => copiesQuery.data?.data ?? [], [copiesQuery.data])
  const manual = book?.inv_number_auto === false
  const cover = storageUrl(book?.image_url) ?? book?.cover_url ?? null

  const refresh = () => {
    void queryClient.invalidateQueries({ queryKey: ['book', bookId] })
    void queryClient.invalidateQueries({ queryKey: ['book-copies', { bookId }] })
  }

  const toggleAll = (checked: boolean) => {
    setSelected(checked ? copies.map((copy) => copy.id) : [])
  }

  const cancelWriteOff = async (copy: BookCopy) => {
    try {
      await api.post(apiPaths.bookCopyWriteOffCancel(copy.id))
      refresh()
      toast.success(t('books.copies.writeOffCancelled'))
    } catch (error) {
      if (error instanceof ApiError) toast.error(error.messageText ?? t('errors.unexpected'))
    }
  }

  const deleteCopy = async (copy: BookCopy) => {
    try {
      await api.delete(apiPaths.bookCopy(copy.id))
      refresh()
      toast.success(t('books.copies.deleted'))
    } catch (error) {
      if (error instanceof ApiError) toast.error(error.messageText ?? t('errors.unexpected'))
    }
  }

  const selectedIds = useMemo(() => new Set(selected), [selected])
  const allCopiesSelected = copies.length > 0 && selected.length === copies.length
  const someCopiesSelected = selected.length > 0

  const toggleRow = (id: number) => {
    setSelected((current) =>
      current.includes(id) ? current.filter((value) => value !== id) : [...current, id],
    )
  }

  const renderCopyActions = (copy: BookCopy) => (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon-sm" aria-label={t('common.actions')}>
          <MoreHorizontal />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuItem asChild>
          <Link to={`/books/copies/${copy.id}`}>
            <Eye />
            {t('common.view')}
          </Link>
        </DropdownMenuItem>
        {copy.can?.update ? (
          <DropdownMenuItem onSelect={() => setEditingCopy(copy)}>
            <Pencil />
            {t('common.edit')}
          </DropdownMenuItem>
        ) : null}
        {copy.can?.update ? (
          <DropdownMenuItem onSelect={() => setRecErrorCopy(copy)}>
            <FileDown />
            {t('books.copies.recErrorAction')}
          </DropdownMenuItem>
        ) : null}
        {copy.can?.writeOff && copy.status !== 'written_off' ? (
          <DropdownMenuItem onSelect={() => setWriteOffCopy(copy)}>
            <ArchiveX />
            {t('books.copies.writeOffAction')}
          </DropdownMenuItem>
        ) : null}
        {copy.status === 'written_off' ? (
          <DropdownMenuItem onSelect={() => void cancelWriteOff(copy)}>
            <RotateCcw />
            {t('books.copies.cancelWriteOff')}
          </DropdownMenuItem>
        ) : null}
        {copy.can?.delete && !copy.borrowed ? (
          <>
            <DropdownMenuSeparator />
            <DropdownMenuItem variant="destructive" onSelect={() => void deleteCopy(copy)}>
              <Trash2 />
              {t('common.delete')}
            </DropdownMenuItem>
          </>
        ) : null}
      </DropdownMenuContent>
    </DropdownMenu>
  )

  if (bookQuery.isLoading) return <PageLoader />
  if (!book) return <p className="text-sm text-muted-foreground">{t('books.notFound')}</p>

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-2">
        <Button asChild variant="ghost" size="icon-sm">
          <Link to="/books/titles" aria-label={t('common.back')}>
            <ArrowLeft />
          </Link>
        </Button>
        <h2 className="font-brand-heading text-2xl font-bold tracking-tight">{book.name}</h2>
      </div>

      <Card>
        <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div className="flex min-w-0 flex-col gap-4 sm:flex-row">
            {cover ? (
              <img src={cover} alt={book.name} className="h-32 w-22 rounded-md object-cover" />
            ) : book.can?.update ? (
              <button
                type="button"
                onClick={() => setBookModalOpen(true)}
                className="grid h-32 w-22 shrink-0 place-items-center rounded-md border border-dashed text-muted-foreground transition-colors hover:border-brand-accent hover:text-brand-accent"
                aria-label={t('books.fields.uploadCover')}
                title={t('books.fields.uploadCoverHint')}
              >
                <ImagePlus className="size-6" />
              </button>
            ) : null}
            <div className="min-w-0 space-y-1">
              <CardTitle className="font-brand-heading text-lg">{book.name}</CardTitle>
              <p className="text-sm text-muted-foreground">
                {book.authors?.map((author) => author.display_name).join('; ') || '—'}
              </p>
              <p className="text-sm text-muted-foreground">
                {[book.category_primary_name, book.category_secondary_name].filter(Boolean).join(' / ') || '—'}
              </p>
              <p className="text-sm text-muted-foreground">{book.library_name}</p>
              <div className="flex flex-wrap gap-2 pt-1 text-xs">
                <Badge variant="secondary">
                  {t('books.availableOf', { available: book.available_count ?? 0, total: book.copies_count ?? 0 })}
                </Badge>
                <Badge variant="outline">{manual ? t('books.manualMode') : t('books.autoMode')}</Badge>
              </div>
            </div>
          </div>
          {book.can?.update ? (
            <Button variant="outline" className="self-start sm:self-auto" onClick={() => setBookModalOpen(true)}>
              <Pencil />
              {t('common.edit')}
            </Button>
          ) : null}
        </CardHeader>
        {book.description ? (
          <CardContent>
            <p className="text-sm text-muted-foreground">{book.description}</p>
          </CardContent>
        ) : null}
      </Card>

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h3 className="font-brand-heading text-lg font-semibold">{t('books.copies.title')}</h3>
        <div className="flex flex-wrap items-center gap-2">
          {selected.length ? (
            <Button variant="outline" onClick={() => setPrintOpen(true)}>
              <Printer />
              {t('books.copies.printSelected', { count: selected.length })}
            </Button>
          ) : null}
          {book.can?.update ? (
            <Button variant="brand" onClick={() => setAddModalOpen(true)}>
              <Plus />
              {t('books.copies.add')}
            </Button>
          ) : null}
        </div>
      </div>

      {copiesQuery.isLoading ? (
        <PageLoader />
      ) : (
        <div className="rounded-lg border bg-card">
          <div className="hidden lg:block">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-10 px-4">
                  <Checkbox
                    checked={selected.length > 0 && selected.length === copies.length}
                    onCheckedChange={(value) => toggleAll(value === true)}
                    aria-label={t('books.copies.selectAll')}
                  />
                </TableHead>
                <TableHead className="px-4">{t('books.copies.invNumber')}</TableHead>
                <TableHead className="px-4">{t('books.copies.barcode')}</TableHead>
                <TableHead className="px-4">{t('books.copies.status')}</TableHead>
                <TableHead className="hidden px-4 lg:table-cell">{t('books.copies.isbn')}</TableHead>
                <TableHead className="px-4 text-right">{t('common.actions')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {copies.map((copy) => (
                <TableRow key={copy.id}>
                  <TableCell className="px-4">
                    <Checkbox
                      checked={selected.includes(copy.id)}
                      onCheckedChange={(value) =>
                        setSelected((current) =>
                          value === true ? [...current, copy.id] : current.filter((id) => id !== copy.id),
                        )
                      }
                      aria-label={t('books.copies.select')}
                    />
                  </TableCell>
                  <TableCell className="px-4 font-medium">
                    <Link to={`/books/copies/${copy.id}`} className="hover:text-brand-accent">
                      {copy.order_number ?? '—'}
                    </Link>
                  </TableCell>
                  <TableCell className="px-4 font-mono text-xs text-muted-foreground">{copy.barcode ?? '—'}</TableCell>
                  <TableCell className="px-4">
                    <Badge variant={STATUS_VARIANTS[copy.status]}>{t(`books.status.${copy.status}`)}</Badge>
                  </TableCell>
                  <TableCell className="hidden px-4 text-muted-foreground lg:table-cell">{copy.isbn ?? '—'}</TableCell>
                  <TableCell className="px-4">
                    <div className="flex justify-end">{renderCopyActions(copy)}</div>
                  </TableCell>
                </TableRow>
              ))}
              {!copies.length ? (
                <TableRow>
                  <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                    {t('books.copies.empty')}
                  </TableCell>
                </TableRow>
              ) : null}
            </TableBody>
          </Table>
          </div>

          <div className="lg:hidden">
            <BookCopiesMobileList
              copies={copies}
              selectedIds={selectedIds}
              onToggleRow={toggleRow}
              onToggleAll={() => toggleAll(!allCopiesSelected)}
              allSelected={allCopiesSelected}
              someSelected={someCopiesSelected}
              renderActions={renderCopyActions}
            />
          </div>
        </div>
      )}

      {bookModalOpen ? (
        <BookFormModal
          open={bookModalOpen}
          book={book}
          onClose={() => setBookModalOpen(false)}
          onSuccess={() => {
            void queryClient.invalidateQueries({ queryKey: ['book', bookId] })
            toast.success(t('books.updated'))
          }}
        />
      ) : null}

      {addModalOpen ? (
        <BookCopyAddModal
          open={addModalOpen}
          book={book}
          onClose={() => setAddModalOpen(false)}
          onSuccess={refresh}
          onUseExistingTitle={(target) => {
            setAddModalOpen(false)
            navigate(`/books/titles/${target.id}`)
          }}
        />
      ) : null}

      {editingCopy ? (
        <BookCopyEditModal
          open
          copy={editingCopy}
          bookId={bookId}
          manual={manual}
          onClose={() => setEditingCopy(null)}
          onSuccess={refresh}
        />
      ) : null}

      {writeOffCopy ? (
        <BookCopyWriteOffDialog
          open
          copy={writeOffCopy}
          bookId={bookId}
          onClose={() => setWriteOffCopy(null)}
          onSuccess={refresh}
        />
      ) : null}

      {recErrorCopy ? (
        <BookCopyRecErrorDialog
          open
          copy={recErrorCopy}
          bookId={bookId}
          onClose={() => setRecErrorCopy(null)}
          onSuccess={refresh}
        />
      ) : null}

      {printOpen ? (
        <BookCopyBarcodePrintModal
          open
          copyIds={selected}
          onClose={() => setPrintOpen(false)}
          onSuccess={() => setSelected([])}
        />
      ) : null}
    </div>
  )
}
