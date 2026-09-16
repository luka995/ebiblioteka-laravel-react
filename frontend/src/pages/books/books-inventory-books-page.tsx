import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Download, FileText, Plus } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError, downloadBlob } from '@/lib/api'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { PageLoader, Spinner } from '@/components/ui/loader'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useInventoryBookStatus, isInventoryBookPending } from '@/hooks/useInventoryBookStatus'
import { useAuth } from '@/hooks/useAuth'
import type { InventoryBook, InventoryBookStatus } from '@/types'

const STATUS_VARIANTS: Record<InventoryBookStatus, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  pending: 'secondary',
  processing: 'secondary',
  completed: 'default',
  failed: 'destructive',
}

export function BooksInventoryBooksPage() {
  const { t } = useTranslation()
  const { can, activeLibrary } = useAuth()
  const query = useInventoryBookStatus(activeLibrary?.id ?? null)
  const [isGenerating, setIsGenerating] = useState(false)
  const [downloadingId, setDownloadingId] = useState<number | null>(null)

  if (!can('inventory_books.viewAny')) {
    return <p className="text-sm text-muted-foreground">{t('errors.forbidden')}</p>
  }

  if (activeLibrary === null) {
    return <p className="text-sm text-muted-foreground">{t('books.activeLibraryRequired')}</p>
  }

  const items = query.data?.data ?? []

  const generate = async () => {
    setIsGenerating(true)
    try {
      await api.post(apiPaths.inventoryBooks)
      await query.refetch()
      toast.success(t('books.inventory.started'))
    } catch (error) {
      if (error instanceof ApiError) toast.error(error.messageText ?? t('errors.unexpected'))
    } finally {
      setIsGenerating(false)
    }
  }

  const download = async (book: InventoryBook) => {
    setDownloadingId(book.id)
    try {
      const blob = await api.download(apiPaths.inventoryBookDownload(book.id))
      downloadBlob(blob, `inventarna-knjiga-${book.library_id}-${book.id}.pdf`)
    } catch (error) {
      if (error instanceof ApiError) toast.error(error.messageText ?? t('errors.unexpected'))
    } finally {
      setDownloadingId(null)
    }
  }

  const statusCell = (book: InventoryBook) => {
    if (isInventoryBookPending(book)) {
      return (
        <span className="inline-flex items-center gap-2 text-muted-foreground">
          <Spinner className="size-4" />
          {t(`books.inventory.status.${book.status}`)}
        </span>
      )
    }

    return <Badge variant={STATUS_VARIANTS[book.status]}>{t(`books.inventory.status.${book.status}`)}</Badge>
  }

  const actionCell = (book: InventoryBook) => {
    if (book.status === 'completed' && book.can?.download) {
      return (
        <Button
          variant="outline"
          size="sm"
          disabled={downloadingId === book.id}
          onClick={() => void download(book)}
        >
          {downloadingId === book.id ? <Spinner className="size-4" /> : <Download />}
          {t('books.inventory.download')}
        </Button>
      )
    }

    if (book.status === 'failed' && book.failure_reason) {
      return <span className="text-xs text-destructive">{book.failure_reason}</span>
    }

    return <span className="text-xs text-muted-foreground">—</span>
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="font-brand-heading text-2xl font-bold tracking-tight">{t('books.inventory.title')}</h2>
          <p className="mt-1 text-sm text-muted-foreground">{t('books.inventory.subtitle')}</p>
        </div>
        {can('inventory_books.create') ? (
          <Button onClick={() => void generate()} disabled={isGenerating}>
            {isGenerating ? <Spinner className="size-4" /> : <Plus />}
            {t('books.inventory.generate')}
          </Button>
        ) : null}
      </div>

      {query.isLoading ? (
        <PageLoader />
      ) : (
        <div className="rounded-lg border bg-card">
          <div className="hidden lg:block">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="px-4">{t('books.inventory.columns.date')}</TableHead>
                  <TableHead className="px-4">{t('books.inventory.columns.status')}</TableHead>
                  <TableHead className="px-4 text-right">{t('books.inventory.columns.rows')}</TableHead>
                  <TableHead className="px-4">{t('books.inventory.columns.requestedBy')}</TableHead>
                  <TableHead className="px-4 text-right">{t('common.actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {items.map((book) => (
                  <TableRow key={book.id}>
                    <TableCell className="px-4 font-medium">{book.created_at}</TableCell>
                    <TableCell className="px-4">{statusCell(book)}</TableCell>
                    <TableCell className="px-4 text-right text-muted-foreground">{book.rows_count ?? '—'}</TableCell>
                    <TableCell className="px-4 text-muted-foreground">{book.requested_by ?? '—'}</TableCell>
                    <TableCell className="px-4">
                      <div className="flex justify-end">{actionCell(book)}</div>
                    </TableCell>
                  </TableRow>
                ))}
                {!items.length ? (
                  <TableRow>
                    <TableCell colSpan={5} className="h-24 text-center text-muted-foreground">
                      {t('books.inventory.empty')}
                    </TableCell>
                  </TableRow>
                ) : null}
              </TableBody>
            </Table>
          </div>

          <div className="divide-y lg:hidden">
            {items.map((book) => (
              <div key={book.id} className="space-y-2 p-4">
                <div className="flex items-center justify-between gap-2">
                  <span className="font-medium">{book.created_at}</span>
                  {statusCell(book)}
                </div>
                <div className="flex items-center justify-between gap-2 text-sm text-muted-foreground">
                  <span>{t('books.inventory.columns.rows')}: {book.rows_count ?? '—'}</span>
                  <span>{book.requested_by ?? '—'}</span>
                </div>
                {book.status === 'completed' || book.status === 'failed' ? (
                  <div className="flex justify-end">{actionCell(book)}</div>
                ) : null}
              </div>
            ))}
            {!items.length ? (
              <p className="p-8 text-center text-sm text-muted-foreground">{t('books.inventory.empty')}</p>
            ) : null}
          </div>
        </div>
      )}

      <p className="flex items-center gap-2 text-xs text-muted-foreground">
        <FileText className="size-3.5" />
        {t('books.inventory.hint')}
      </p>
    </div>
  )
}
