import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { FileSpreadsheet, Plus } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { useAuth } from '@/hooks/useAuth'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { PageLoader, Spinner } from '@/components/ui/loader'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { BookImportUploadDialog } from '@/components/books/book-import-upload-dialog'
import type { BookImport, BookImportStatus, PaginatedResponse } from '@/types'

const IMPORT_STATUS_VARIANTS: Record<BookImportStatus, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  pending: 'secondary',
  ready: 'default',
  processing: 'secondary',
  completed: 'default',
  completed_with_errors: 'outline',
  failed: 'destructive',
  cancelled: 'outline',
}

function getErrorMessage(error: unknown, fallback: string): string {
  if (!(error instanceof ApiError)) return fallback
  const validationMessage = Object.values(error.validationErrors ?? {}).flat()[0]
  return error.messageText ?? validationMessage ?? fallback
}

function formatFileSize(bytes: number): string {
  if (bytes < 1024 * 1024) return `${Math.ceil(bytes / 1024)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

export function BookImportsPage() {
  const { t } = useTranslation()
  const { can, activeLibrary, isLoading } = useAuth()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [uploadOpen, setUploadOpen] = useState(false)
  const [preparingImportId, setPreparingImportId] = useState<number | null>(null)
  const libraryId = activeLibrary?.id ?? null

  const importsQuery = useQuery({
    queryKey: ['book-imports', libraryId],
    queryFn: () => api.get<PaginatedResponse<BookImport>>(`${apiPaths.bookImports}?per_page=50&sort=created_at&direction=desc`),
    enabled: libraryId !== null && can('book_imports.viewAny'),
    refetchInterval: (query) =>
      query.state.data?.data.some((item) => item.status === 'pending' || item.status === 'processing') ? 5000 : false,
  })

  const preparingQuery = useQuery({
    queryKey: ['book-import', preparingImportId, libraryId],
    queryFn: () => api.get<{ data: BookImport }>(apiPaths.bookImport(preparingImportId as number)),
    enabled: preparingImportId !== null,
    refetchInterval: (query) => {
      const status = query.state.data?.data.status
      return status === 'pending' || status === 'processing' ? 1500 : false
    },
  })

  useEffect(() => {
    if (preparingImportId === null || !preparingQuery.data) return

    const status = preparingQuery.data.data.status

    if (status === 'ready') {
      setPreparingImportId(null)
      void queryClient.invalidateQueries({ queryKey: ['book-imports', libraryId] })
      navigate(`/books/imports/${preparingImportId}`)
      return
    }

    if (status === 'failed' || status === 'cancelled') {
      toast.error(preparingQuery.data.data.failure_message ?? t('bookImports.upload.preparationFailed'))
      setPreparingImportId(null)
      void queryClient.invalidateQueries({ queryKey: ['book-imports', libraryId] })
    }
  }, [libraryId, navigate, preparingImportId, preparingQuery.data, queryClient, t])

  useEffect(() => {
    if (!preparingQuery.isError) return

    toast.error(getErrorMessage(preparingQuery.error, t('errors.unexpected')))
    setPreparingImportId(null)
  }, [preparingQuery.error, preparingQuery.isError, t])

  if (isLoading) return <PageLoader />

  if (!can('book_imports.viewAny')) {
    return <p className="text-sm text-muted-foreground">{t('errors.forbidden')}</p>
  }

  if (libraryId === null) {
    return <p className="text-sm text-muted-foreground">{t('bookImports.activeLibraryRequired')}</p>
  }

  if (importsQuery.isLoading) return <PageLoader />

  const imports = importsQuery.data?.data ?? []

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 className="font-brand-heading text-2xl font-bold tracking-tight">{t('bookImports.title')}</h2>
          <p className="mt-1 text-sm text-muted-foreground">{t('bookImports.subtitle')}</p>
        </div>
        {can('book_imports.create') ? (
          <Button variant="brand" onClick={() => setUploadOpen(true)}>
            <Plus />
            {t('bookImports.upload.button')}
          </Button>
        ) : null}
      </div>

      {importsQuery.isError ? (
        <div className="rounded-md border border-destructive/40 p-4 text-sm text-destructive">
          {getErrorMessage(importsQuery.error, t('errors.unexpected'))}
          <Button variant="link" className="ml-2" onClick={() => void importsQuery.refetch()}>
            {t('bookImports.retry')}
          </Button>
        </div>
      ) : imports.length === 0 ? (
        <div className="rounded-lg border border-dashed p-8 text-center">
          <FileSpreadsheet className="mx-auto size-8 text-muted-foreground" />
          <p className="mt-3 font-medium">{t('bookImports.emptyTitle')}</p>
          <p className="mt-1 text-sm text-muted-foreground">{t('bookImports.emptyText')}</p>
        </div>
      ) : (
        <div className="overflow-x-auto rounded-md border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('bookImports.columns.file')}</TableHead>
                <TableHead>{t('bookImports.columns.status')}</TableHead>
                <TableHead>{t('bookImports.columns.rows')}</TableHead>
                <TableHead>{t('bookImports.columns.requestedBy')}</TableHead>
                <TableHead>{t('bookImports.columns.createdAt')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {imports.map((item) => (
                <TableRow key={item.id}>
                  <TableCell className="min-w-56">
                    <Link className="font-medium text-primary hover:underline" to={`/books/imports/${item.id}`}>
                      {item.original_filename}
                    </Link>
                    <p className="text-xs text-muted-foreground">{formatFileSize(item.file_size)}</p>
                  </TableCell>
                  <TableCell>
                    <Badge variant={IMPORT_STATUS_VARIANTS[item.status]}>
                      {t(`bookImports.status.${item.status}`)}
                    </Badge>
                  </TableCell>
                  <TableCell className="whitespace-nowrap">
                    {t('bookImports.rowCounts', {
                      pending: item.pending_rows,
                      imported: item.imported_rows,
                      importFailed: item.import_failed_rows,
                      preparationFailed: item.preparation_failed_rows,
                      total: item.total_rows,
                    })}
                  </TableCell>
                  <TableCell>{item.requested_by ?? '—'}</TableCell>
                  <TableCell className="whitespace-nowrap">{item.created_at || '—'}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}

      {preparingImportId !== null ? (
        <div
          className="fixed inset-0 z-[100] flex items-center justify-center bg-background/85 backdrop-blur-sm"
          role="status"
          aria-live="polite"
          aria-busy="true"
        >
          <div className="mx-4 max-w-sm rounded-lg border bg-card p-6 text-center shadow-lg">
            <Spinner className="mx-auto size-8 text-brand" />
            <p className="mt-4 font-medium">{t('bookImports.upload.preparing')}</p>
            <p className="mt-1 text-sm text-muted-foreground">{t('bookImports.upload.preparingDescription')}</p>
          </div>
        </div>
      ) : null}

      <BookImportUploadDialog
        open={uploadOpen}
        onOpenChange={setUploadOpen}
        onUploaded={(id) => {
          setPreparingImportId(id)
          void queryClient.invalidateQueries({ queryKey: ['book-imports', libraryId] })
        }}
      />
    </div>
  )
}
