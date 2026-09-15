import { useState, type ReactNode } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { ArchiveX, ArrowLeft, FileDown, Pencil, Printer, RotateCcw, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError, downloadBlob } from '@/lib/api'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
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
import { PageLoader } from '@/components/ui/loader'
import { BookCopyEditModal } from '@/components/books/book-copy-edit-modal'
import { BookCopyRecErrorDialog, BookCopyWriteOffDialog } from '@/components/books/book-copy-action-dialogs'
import type { BookCopy, BookCopyStatus } from '@/types'

const STATUS_VARIANTS: Record<BookCopyStatus, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  available: 'default',
  borrowed: 'secondary',
  record_error: 'outline',
  written_off: 'destructive',
  archived: 'secondary',
}

const BINDING_LABELS: Record<string, string> = {
  t: 'hard',
  b: 'paperback',
  k: 'carton',
  ko: 'leather',
  l: 'luxury',
}

const ORIGIN_LABELS: Record<string, string> = {
  ob: 'mandatory',
  ku: 'purchase',
  ra: 'exchange',
  po: 'gift',
}

function Field({ label, value }: { label: string; value: ReactNode }) {
  const empty = value === null || value === undefined || value === ''
  return (
    <div className="space-y-0.5">
      <dt className="text-xs text-muted-foreground">{label}</dt>
      <dd className="text-sm font-medium break-words">{empty ? '—' : value}</dd>
    </div>
  )
}

export function BookCopyDetailPage() {
  const { t } = useTranslation()
  const params = useParams()
  const copyId = Number(params.id)
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const [editOpen, setEditOpen] = useState(false)
  const [writeOffOpen, setWriteOffOpen] = useState(false)
  const [recErrorOpen, setRecErrorOpen] = useState(false)
  const [deleteOpen, setDeleteOpen] = useState(false)
  const [isDeleting, setIsDeleting] = useState(false)
  const [isPrinting, setIsPrinting] = useState(false)

  const copyQuery = useQuery({
    queryKey: ['book-copy', copyId],
    queryFn: () => api.get<{ data: BookCopy }>(apiPaths.bookCopy(copyId)),
    enabled: Number.isFinite(copyId),
  })

  const copy = copyQuery.data?.data

  const refresh = () => {
    void queryClient.invalidateQueries({ queryKey: ['book-copy', copyId] })
    void queryClient.invalidateQueries({ queryKey: ['book-copies'] })
  }

  const cancelWriteOff = async () => {
    if (!copy) return
    try {
      await api.post(apiPaths.bookCopyWriteOffCancel(copy.id))
      refresh()
      toast.success(t('books.copies.writeOffCancelled'))
    } catch (error) {
      if (error instanceof ApiError) toast.error(error.messageText ?? t('errors.unexpected'))
    }
  }

  const print = async () => {
    if (!copy) return
    setIsPrinting(true)
    try {
      const blob = await api.download(apiPaths.bookCopiesBulkPrint, {
        method: 'POST',
        body: { ids: [copy.id], format: 'label' },
      })
      downloadBlob(blob, 'barkod-knjige.pdf')
    } catch (error) {
      if (error instanceof ApiError) toast.error(error.messageText ?? t('errors.unexpected'))
    } finally {
      setIsPrinting(false)
    }
  }

  const confirmDelete = async () => {
    if (!copy) return
    setIsDeleting(true)
    try {
      await api.delete(apiPaths.bookCopy(copy.id))
      await queryClient.invalidateQueries({ queryKey: ['book-copies'] })
      toast.success(t('books.copies.deleted'))
      navigate('/books/copies')
    } catch (error) {
      if (error instanceof ApiError) toast.error(error.messageText ?? t('errors.unexpected'))
    } finally {
      setIsDeleting(false)
    }
  }

  if (copyQuery.isLoading) return <PageLoader />
  if (!copy) return <p className="text-sm text-muted-foreground">{t('books.copies.notFound')}</p>

  const manual = copy.inv_number_auto === false
  const canUpdate = copy.can?.update ?? false
  const canWriteOff = copy.can?.writeOff ?? false
  const canDelete = copy.can?.delete ?? false
  const bindingLabel = copy.binding && BINDING_LABELS[copy.binding] ? t(`books.binding.${BINDING_LABELS[copy.binding]}`) : copy.binding
  const originLabel = copy.origin && ORIGIN_LABELS[copy.origin] ? t(`books.origin.${ORIGIN_LABELS[copy.origin]}`) : copy.origin
  const yesNo = (value: boolean) => (value ? t('common.yes') : t('common.no'))

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-2">
          <Button asChild variant="ghost" size="icon-sm">
            <Link to="/books/copies" aria-label={t('common.back')}>
              <ArrowLeft />
            </Link>
          </Button>
          <div>
            <h2 className="font-brand-heading text-2xl font-bold tracking-tight">
              {copy.book_name ?? t('books.copies.detailTitle')}
            </h2>
            <p className="text-sm text-muted-foreground">
              {t('books.copies.invNumber')}: {copy.order_number ?? '—'}
            </p>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          {copy.barcode ? (
            <Button variant="outline" onClick={() => void print()} disabled={isPrinting}>
              <Printer />
              {t('books.copies.printBarcode')}
            </Button>
          ) : null}
          {canUpdate ? (
            <Button variant="outline" onClick={() => setEditOpen(true)}>
              <Pencil />
              {t('common.edit')}
            </Button>
          ) : null}
          {canUpdate ? (
            <Button variant="outline" onClick={() => setRecErrorOpen(true)}>
              <FileDown />
              {t('books.copies.recErrorAction')}
            </Button>
          ) : null}
          {canWriteOff && copy.status !== 'written_off' ? (
            <Button variant="outline" onClick={() => setWriteOffOpen(true)}>
              <ArchiveX />
              {t('books.copies.writeOffAction')}
            </Button>
          ) : null}
          {canWriteOff && copy.status === 'written_off' ? (
            <Button variant="outline" onClick={() => void cancelWriteOff()}>
              <RotateCcw />
              {t('books.copies.cancelWriteOff')}
            </Button>
          ) : null}
          {canDelete && !copy.borrowed ? (
            <Button variant="destructive" onClick={() => setDeleteOpen(true)}>
              <Trash2 />
              {t('common.delete')}
            </Button>
          ) : null}
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="font-brand-heading text-lg">{t('books.copies.sections.identification')}</CardTitle>
        </CardHeader>
        <CardContent>
          <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Field
              label={t('books.copies.status')}
              value={<Badge variant={STATUS_VARIANTS[copy.status]}>{t(`books.status.${copy.status}`)}</Badge>}
            />
            <Field label={t('books.copies.orderNumber')} value={copy.order_number} />
            <Field label={t('books.copies.barcode')} value={copy.barcode} />
            <Field label={t('books.fields.seqNumber')} value={copy.seq_number} />
            <Field
              label={t('books.columns.name')}
              value={
                <Link to={`/books/titles/${copy.book_id}`} className="hover:text-brand-accent">
                  {copy.book_name ?? '—'}
                </Link>
              }
            />
            <Field label={t('books.fields.library')} value={copy.library_name} />
          </dl>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="font-brand-heading text-lg">{t('books.copies.sections.bibliographic')}</CardTitle>
        </CardHeader>
        <CardContent>
          <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Field label={t('books.fields.isbn')} value={copy.isbn} />
            <Field label={t('books.fields.publisher')} value={copy.publisher} />
            <Field label={t('books.fields.publishPlace')} value={copy.publish_place} />
            <Field label={t('books.fields.publishYear')} value={copy.publish_year} />
            <Field label={t('books.fields.issueNumber')} value={copy.issue_number} />
            <Field label={t('books.fields.pages')} value={copy.num_of_pages} />
            <Field label={t('books.fields.dimension')} value={copy.dimension} />
            <Field label={t('books.fields.part')} value={copy.part} />
            <Field label={t('books.fields.udk')} value={copy.udk} />
            <Field label={t('books.fields.binding')} value={bindingLabel} />
            <Field label={t('books.fields.origin')} value={originLabel} />
            <Field label={t('books.fields.bookNumber')} value={copy.book_number} />
          </dl>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="font-brand-heading text-lg">{t('books.copies.sections.location')}</CardTitle>
        </CardHeader>
        <CardContent>
          <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Field label={t('books.fields.placeOnShelf')} value={copy.place_on_shelf} />
            <Field label={t('books.fields.price')} value={copy.price} />
            <Field label={t('books.fields.dateAdd')} value={copy.date_add_formatted} />
            <Field label={t('books.fields.notice')} value={copy.notice} />
          </dl>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="font-brand-heading text-lg">{t('books.copies.sections.state')}</CardTitle>
        </CardHeader>
        <CardContent>
          <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Field label={t('books.copies.borrowed')} value={yesNo(copy.borrowed)} />
            <Field label={t('books.copies.reserved')} value={yesNo(copy.reserved)} />
            <Field label={t('books.copies.recError')} value={yesNo(copy.rec_error)} />
            <Field label={t('books.copies.recErrorNotice')} value={copy.rec_error_notice} />
          </dl>
        </CardContent>
      </Card>

      {copy.active_write_off ? (
        <Card>
          <CardHeader>
            <CardTitle className="font-brand-heading text-lg">{t('books.copies.sections.writeOff')}</CardTitle>
          </CardHeader>
          <CardContent>
            <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <Field
                label={t('books.copies.reason')}
                value={t(`books.writeOffReasons.${copy.active_write_off.reason}`)}
              />
              <Field label={t('books.copies.occurredAt')} value={copy.active_write_off.occurred_at} />
              <Field label={t('books.fields.notice')} value={copy.active_write_off.notice} />
            </dl>
          </CardContent>
        </Card>
      ) : null}

      <Card>
        <CardHeader>
          <CardTitle className="font-brand-heading text-lg">{t('books.copies.sections.timestamps')}</CardTitle>
        </CardHeader>
        <CardContent>
          <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Field label={t('books.copies.createdAt')} value={copy.created_at} />
            <Field label={t('books.copies.updatedAt')} value={copy.updated_at} />
          </dl>
        </CardContent>
      </Card>

      {editOpen ? (
        <BookCopyEditModal
          open
          copy={copy}
          bookId={copy.book_id}
          manual={manual}
          onClose={() => setEditOpen(false)}
          onSuccess={refresh}
        />
      ) : null}

      {writeOffOpen ? (
        <BookCopyWriteOffDialog
          open
          copy={copy}
          bookId={copy.book_id}
          onClose={() => setWriteOffOpen(false)}
          onSuccess={refresh}
        />
      ) : null}

      {recErrorOpen ? (
        <BookCopyRecErrorDialog
          open
          copy={copy}
          bookId={copy.book_id}
          onClose={() => setRecErrorOpen(false)}
          onSuccess={refresh}
        />
      ) : null}

      <AlertDialog open={deleteOpen} onOpenChange={(value) => !value && setDeleteOpen(false)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('books.copies.deleteConfirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('books.copies.deleteConfirmText', { number: copy.order_number ?? '' })}
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
