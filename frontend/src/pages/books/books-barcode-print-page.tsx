import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Download, Plus, Trash2, TriangleAlert } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError, downloadBlob } from '@/lib/api'
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
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { PageLoader, Spinner } from '@/components/ui/loader'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import {
  BarcodePrintFormatPicker,
  type BarcodePrintFormat,
} from '@/components/barcode/barcode-print-format-picker'
import { useBarcodePrintJobStatus, isBarcodePrintJobPending } from '@/hooks/useBarcodePrintJobStatus'
import { useAuth } from '@/hooks/useAuth'
import type { BarcodePrintJob, BarcodePrintJobStatus } from '@/types'

const STATUS_VARIANTS: Record<BarcodePrintJobStatus, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  pending: 'secondary',
  processing: 'secondary',
  completed: 'default',
  failed: 'destructive',
}

export function BooksBarcodePrintPage() {
  const { t } = useTranslation()
  const { can, activeLibrary } = useAuth()
  const query = useBarcodePrintJobStatus(activeLibrary?.id ?? null)
  const [format, setFormat] = useState<BarcodePrintFormat>('a4')
  const [isGenerating, setIsGenerating] = useState(false)
  const [downloadingId, setDownloadingId] = useState<number | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<BarcodePrintJob | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)

  if (!can('barcode_print_jobs.viewAny')) {
    return <p className="text-sm text-muted-foreground">{t('errors.forbidden')}</p>
  }

  if (activeLibrary === null) {
    return <p className="text-sm text-muted-foreground">{t('books.activeLibraryRequired')}</p>
  }

  const items = query.data?.data ?? []

  const formatLabel = (value: string) =>
    value === 'a4' ? t('barcode.formats.a4') : t('barcode.formats.label')

  const generate = async () => {
    setIsGenerating(true)
    try {
      await api.post(apiPaths.barcodePrintJobs, { format })
      await query.refetch()
      toast.success(t('books.barcodePrint.started'))
    } catch (error) {
      if (error instanceof ApiError) toast.error(error.messageText ?? t('errors.unexpected'))
    } finally {
      setIsGenerating(false)
    }
  }

  const download = async (job: BarcodePrintJob) => {
    setDownloadingId(job.id)
    try {
      const blob = await api.download(apiPaths.barcodePrintJobDownload(job.id))
      downloadBlob(blob, `barkodovi-knjiga-${job.library_id}-${job.id}.pdf`)
    } catch (error) {
      if (error instanceof ApiError) toast.error(error.messageText ?? t('errors.unexpected'))
    } finally {
      setDownloadingId(null)
    }
  }

  const confirmDelete = async () => {
    if (!deleteTarget) return

    setIsDeleting(true)
    try {
      await api.delete(apiPaths.barcodePrintJob(deleteTarget.id))
      await query.refetch()
      setDeleteTarget(null)
    } catch (error) {
      if (error instanceof ApiError) toast.error(error.messageText ?? t('errors.unexpected'))
    } finally {
      setIsDeleting(false)
    }
  }

  const statusCell = (job: BarcodePrintJob) => {
    if (isBarcodePrintJobPending(job)) {
      return (
        <span className="inline-flex items-center gap-2 text-muted-foreground">
          <Spinner className="size-4" />
          {t(`books.barcodePrint.status.${job.status}`)}
        </span>
      )
    }

    return <Badge variant={STATUS_VARIANTS[job.status]}>{t(`books.barcodePrint.status.${job.status}`)}</Badge>
  }

  const actionCell = (job: BarcodePrintJob) => (
    <div className="flex justify-end gap-2">
      {job.status === 'completed' && job.can?.download ? (
        <Button
          variant="outline"
          size="sm"
          disabled={downloadingId === job.id}
          onClick={() => void download(job)}
        >
          {downloadingId === job.id ? <Spinner className="size-4" /> : <Download />}
          {t('books.barcodePrint.download')}
        </Button>
      ) : null}
      {job.can?.delete ? (
        <Button
          variant="ghost"
          size="icon"
          onClick={() => setDeleteTarget(job)}
          aria-label={t('common.delete')}
        >
          <Trash2 />
        </Button>
      ) : null}
    </div>
  )

  return (
    <div className="space-y-6">
      <div>
        <h2 className="font-brand-heading text-2xl font-bold tracking-tight">{t('books.barcodePrint.title')}</h2>
        <p className="mt-1 text-sm text-muted-foreground">{t('books.barcodePrint.subtitle')}</p>
      </div>

      <div className="flex items-start gap-3 rounded-lg border border-amber-300/60 bg-amber-50 p-4 text-sm text-amber-900">
        <TriangleAlert className="mt-0.5 size-4 shrink-0" />
        <p>{t('books.barcodePrint.notice')}</p>
      </div>

      <div className="space-y-4 rounded-lg border bg-card p-4">
        <BarcodePrintFormatPicker value={format} onChange={setFormat} name="whole-fund-barcode-format" />
        {can('barcode_print_jobs.create') ? (
          <Button onClick={() => void generate()} disabled={isGenerating}>
            {isGenerating ? <Spinner className="size-4" /> : <Plus />}
            {t('books.barcodePrint.generate')}
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
                  <TableHead className="px-4">{t('books.barcodePrint.columns.date')}</TableHead>
                  <TableHead className="px-4">{t('books.barcodePrint.columns.format')}</TableHead>
                  <TableHead className="px-4 text-right">{t('books.barcodePrint.columns.items')}</TableHead>
                  <TableHead className="px-4 text-right">{t('books.barcodePrint.columns.invalid')}</TableHead>
                  <TableHead className="px-4">{t('books.barcodePrint.columns.status')}</TableHead>
                  <TableHead className="px-4">{t('books.barcodePrint.columns.requestedBy')}</TableHead>
                  <TableHead className="px-4 text-right">{t('common.actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {items.map((job) => (
                  <TableRow key={job.id}>
                    <TableCell className="px-4 font-medium">{job.created_at}</TableCell>
                    <TableCell className="px-4 text-muted-foreground">{formatLabel(job.format)}</TableCell>
                    <TableCell className="px-4 text-right text-muted-foreground">{job.items_count ?? '—'}</TableCell>
                    <TableCell className="px-4 text-right">
                      {job.invalid_count > 0 ? (
                        <span className="text-amber-600">{job.invalid_count}</span>
                      ) : (
                        <span className="text-muted-foreground">—</span>
                      )}
                    </TableCell>
                    <TableCell className="px-4">
                      {statusCell(job)}
                      {job.status === 'failed' && job.failure_reason ? (
                        <p className="mt-1 text-xs text-destructive">{job.failure_reason}</p>
                      ) : null}
                    </TableCell>
                    <TableCell className="px-4 text-muted-foreground">{job.requested_by ?? '—'}</TableCell>
                    <TableCell className="px-4">{actionCell(job)}</TableCell>
                  </TableRow>
                ))}
                {!items.length ? (
                  <TableRow>
                    <TableCell colSpan={7} className="h-24 text-center text-muted-foreground">
                      {t('books.barcodePrint.empty')}
                    </TableCell>
                  </TableRow>
                ) : null}
              </TableBody>
            </Table>
          </div>

          <div className="divide-y lg:hidden">
            {items.map((job) => (
              <div key={job.id} className="space-y-2 p-4">
                <div className="flex items-center justify-between gap-2">
                  <span className="font-medium">{job.created_at}</span>
                  {statusCell(job)}
                </div>
                <div className="flex items-center justify-between gap-2 text-sm text-muted-foreground">
                  <span>{formatLabel(job.format)}</span>
                  <span>
                    {t('books.barcodePrint.columns.items')}: {job.items_count ?? '—'}
                    {job.invalid_count > 0 ? ` · ${t('books.barcodePrint.columns.invalid')}: ${job.invalid_count}` : ''}
                  </span>
                </div>
                {job.status === 'failed' && job.failure_reason ? (
                  <p className="text-xs text-destructive">{job.failure_reason}</p>
                ) : null}
                <div className="flex justify-end">{actionCell(job)}</div>
              </div>
            ))}
            {!items.length ? (
              <p className="p-8 text-center text-sm text-muted-foreground">{t('books.barcodePrint.empty')}</p>
            ) : null}
          </div>
        </div>
      )}

      <AlertDialog open={deleteTarget !== null} onOpenChange={(value) => !value && !isDeleting && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('books.barcodePrint.deleteConfirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>{t('books.barcodePrint.deleteConfirmText')}</AlertDialogDescription>
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
