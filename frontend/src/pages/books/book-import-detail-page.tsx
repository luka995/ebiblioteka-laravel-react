import { useEffect, useRef, useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { ArrowLeft, Ban, Check, Info, Pencil, Play, RotateCw, Search, Trash2, TriangleAlert, X } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { useAuth } from '@/hooks/useAuth'
import { useBookImportPolling } from '@/hooks/useBookImportPolling'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { PageLoader } from '@/components/ui/loader'
import { PaginationBar } from '@/components/pagination-bar'
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import type {
  BookImport,
  BookImportFailureCode,
  BookImportField,
  BookImportMode,
  BookImportRow,
  BookImportRowStatus,
  BookImportStatus,
  BookImportSelectionResponse,
  PaginatedResponse,
} from '@/types'

const PAGE_SIZE_OPTIONS = [50, 100, 200]

const FAILURE_CODES: BookImportFailureCode[] = [
  'missing_isbn',
  'invalid_isbn',
  'invalid_field_value',
  'isbn_not_found',
  'lookup_failed',
  'missing_title',
  'missing_inventory_number',
  'invalid_inventory_number',
  'duplicate_inventory_number',
  'inventory_collision',
  'duplicate_seq_number',
  'file_unreadable',
  'missing_columns',
  'duplicate_headers',
  'no_rows',
  'too_many_rows',
  'job_failed',
  'unexpected_error',
]

const IMPORT_FIELDS: BookImportField[] = [
  'isbn',
  'title',
  'authors',
  'category',
  'category_secondary',
  'description',
  'cover_url',
  'inventory_number',
  'seq_number',
  'publisher',
  'publish_place',
  'publish_year',
  'issue_number',
  'num_of_pages',
  'dimension',
  'part',
  'udk',
  'binding',
  'origin',
  'book_number',
  'place_on_shelf',
  'price',
  'notice',
]

const TEXTAREA_FIELDS = new Set<BookImportField>(['description', 'notice'])

const IMPORT_STATUS_VARIANTS: Record<BookImportStatus, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  pending: 'secondary',
  ready: 'default',
  processing: 'secondary',
  completed: 'default',
  completed_with_errors: 'outline',
  failed: 'destructive',
  cancelled: 'outline',
}

const ROW_STATUS_VARIANTS: Record<BookImportRowStatus, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  pending: 'secondary',
  processing: 'outline',
  imported: 'default',
  failed: 'destructive',
}

interface RowFilters {
  status: '' | BookImportRowStatus
  failure_code: '' | BookImportFailureCode
  attempt_mode: '' | BookImportMode
  search: string
}

interface MatchingSelection {
  status: 'pending' | 'failed'
  count: number
  filters: {
    status: 'pending' | 'failed'
    failure_code?: BookImportFailureCode
    attempt_mode?: BookImportMode
    search?: string
  }
}

type RowDataView = 'original' | 'effective' | 'resolved'

interface InlineRowEdit {
  rowId: number
  inputVersion: number
  values: Record<BookImportField, string>
  baseline: Record<BookImportField, string>
}

function getErrorMessage(error: unknown, fallback: string): string {
  if (!(error instanceof ApiError)) return fallback
  const validationMessage = Object.values(error.validationErrors ?? {}).flat()[0]
  return error.messageText ?? validationMessage ?? fallback
}

function displayValue(value: unknown): string {
  if (value === null || value === undefined || value === '') return ''
  if (Array.isArray(value)) return value.map((part) => String(part)).join('; ')
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}

function renderValue(value: unknown): string {
  return displayValue(value) || '—'
}

function rowStatusIsSelectable(status: BookImportRowStatus): status is 'pending' | 'failed' {
  return status === 'pending' || status === 'failed'
}

export function BookImportDetailPage() {
  const { t } = useTranslation()
  const { can, activeLibrary, isLoading } = useAuth()
  const { id: routeId } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const importId = Number(routeId)
  const libraryId = activeLibrary?.id ?? null
  const [filters, setFilters] = useState<RowFilters>({
    status: '',
    failure_code: '',
    attempt_mode: '',
    search: '',
  })
  const [searchInput, setSearchInput] = useState('')
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(200)
  const [selectionStatus, setSelectionStatus] = useState<'pending' | 'failed'>('pending')
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())
  const [matchingSelection, setMatchingSelection] = useState<MatchingSelection | null>(null)
  const [mode, setMode] = useState<BookImportMode>('isbn_check')
  const [rowDataView, setRowDataView] = useState<RowDataView>('effective')
  const [confirmOpen, setConfirmOpen] = useState(false)
  const [actionPending, setActionPending] = useState(false)
  const [cancelOpen, setCancelOpen] = useState(false)
  const [deleteOpen, setDeleteOpen] = useState(false)
  const [lifecyclePending, setLifecyclePending] = useState(false)
  const [inlineEdit, setInlineEdit] = useState<InlineRowEdit | null>(null)
  const [inlineSaving, setInlineSaving] = useState(false)
  const previousStatus = useRef<BookImportStatus | null>(null)

  const importQuery = useQuery({
    queryKey: ['book-import', importId, libraryId],
    queryFn: () => api.get<{ data: BookImport }>(apiPaths.bookImport(importId)),
    enabled: libraryId !== null && can('book_imports.viewAny') && Number.isInteger(importId) && importId > 0,
  })

  const bookImport = importQuery.data?.data
  const importBusy = bookImport?.status === 'pending' || bookImport?.status === 'processing'
  const canCancelImport = bookImport?.can?.cancel === true
    && bookImport.imported_rows === 0
    && (bookImport.status === 'pending' || bookImport.status === 'ready' || bookImport.status === 'processing')
  const canDeleteImport = bookImport?.can?.delete === true
    && bookImport.imported_rows === 0
    && bookImport.status !== 'processing'
  const rowQuery = useQuery({
    queryKey: ['book-import-rows', importId, libraryId, filters, page, perPage],
    queryFn: async () => {
      const query = new URLSearchParams({
        page: String(page),
        per_page: String(perPage),
        sort: 'row_number',
        direction: 'asc',
      })
      if (filters.status) query.set('status', filters.status)
      if (filters.failure_code) query.set('failure_code', filters.failure_code)
      if (filters.attempt_mode) query.set('attempt_mode', filters.attempt_mode)
      if (filters.search) query.set('search', filters.search)
      return api.get<PaginatedResponse<BookImportRow>>(
        `${apiPaths.bookImportRows(importId)}?${query.toString()}`,
      )
    },
    enabled: bookImport !== undefined,
  })
  useBookImportPolling(importId, bookImport?.status)

  useEffect(() => {
    if (!bookImport) return
    const previous = previousStatus.current
    if (previous !== null && previous !== bookImport.status) {
      if (bookImport.status === 'completed') toast.success(t('bookImports.notifications.completed'))
      if (bookImport.status === 'completed_with_errors') toast.warning(t('bookImports.notifications.completedWithErrors'))
      if (bookImport.status === 'failed') {
        toast.error(bookImport.failure_message ?? t('bookImports.notifications.failed'))
      }
    }
    previousStatus.current = bookImport.status
  }, [bookImport, t])

  const rows = rowQuery.data?.data ?? []
  const meta = rowQuery.data?.meta
  const matchingCount = selectionStatus === 'pending'
    ? (meta?.matching_pending_rows ?? 0)
    : (meta?.matching_failed_rows ?? 0)
  const selectedCount = matchingSelection?.count ?? selectedIds.size
  const allMatchingSelected = matchingSelection !== null
  const canStart = bookImport?.status === 'ready'
  const canRetry = bookImport !== undefined && !importBusy
  const canSelect = !importBusy && (selectionStatus === 'pending' ? canStart : canRetry)
  const filterStatusConflicts = filters.status !== '' && filters.status !== selectionStatus

  useEffect(() => {
    setSearchInput(filters.search)
  }, [filters.search])

  useEffect(() => {
    setInlineEdit(null)
  }, [filters, page, perPage])

  useEffect(() => {
    if (importQuery.data && previousStatus.current === null) {
      previousStatus.current = importQuery.data.data.status
    }
  }, [importQuery.data])

  const clearSelection = () => {
    setSelectedIds(new Set())
    setMatchingSelection(null)
  }

  const updateFilter = <K extends keyof RowFilters>(key: K, value: RowFilters[K]) => {
    setFilters((current) => ({ ...current, [key]: value }))
    setPage(1)
    clearSelection()
  }

  const submitSearch = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    updateFilter('search', searchInput.trim())
  }

  const toggleMatchingSelection = () => {
    if (allMatchingSelected) {
      clearSelection()
      return
    }
    if (filterStatusConflicts || matchingCount === 0) return

    const snapshot: MatchingSelection = {
      status: selectionStatus,
      count: matchingCount,
      filters: { status: selectionStatus },
    }
    if (filters.failure_code) snapshot.filters.failure_code = filters.failure_code
    if (filters.attempt_mode) snapshot.filters.attempt_mode = filters.attempt_mode
    if (filters.search) snapshot.filters.search = filters.search

    setSelectedIds(new Set())
    setMatchingSelection(snapshot)
  }

  const toggleRowSelection = (row: BookImportRow, checked: boolean) => {
    if (!rowStatusIsSelectable(row.status) || row.status !== selectionStatus || matchingSelection) return
    setSelectedIds((current) => {
      const next = new Set(current)
      if (checked) next.add(row.id)
      else next.delete(row.id)
      return next
    })
  }

  const changeSelectionStatus = (status: 'pending' | 'failed') => {
    setSelectionStatus(status)
    clearSelection()
  }

  const submitSelection = async () => {
    if (!bookImport || selectedCount === 0) return
    setActionPending(true)
    try {
      const body = matchingSelection
        ? { mode, all_matching: true, filters: matchingSelection.filters }
        : { mode, row_ids: Array.from(selectedIds) }
      const endpoint = selectionStatus === 'pending'
        ? apiPaths.bookImportStart(importId)
        : apiPaths.bookImportRetry(importId)
      const result = await api.post<BookImportSelectionResponse>(endpoint, body)
      toast.success(t('bookImports.selection.accepted', { count: result.data.selected_rows }))
      clearSelection()
      setConfirmOpen(false)
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['book-import', importId] }),
        queryClient.invalidateQueries({ queryKey: ['book-import-rows', importId] }),
      ])
    } catch (error) {
      toast.error(getErrorMessage(error, t('errors.unexpected')))
    } finally {
      setActionPending(false)
    }
  }

  const inlineDirty = inlineEdit !== null && IMPORT_FIELDS.some(
    (field) => inlineEdit.values[field] !== inlineEdit.baseline[field],
  )

  const startInlineEdit = (row: BookImportRow) => {
    if (importBusy || (row.status !== 'pending' && row.status !== 'failed')) return

    const values = Object.fromEntries(
      IMPORT_FIELDS.map((field) => [field, displayValue(row.effective_input[field])]),
    ) as Record<BookImportField, string>

    setInlineEdit({
      rowId: row.id,
      inputVersion: row.input_version,
      values,
      baseline: { ...values },
    })
  }

  const updateInlineValue = (field: BookImportField, value: string) => {
    setInlineEdit((current) => current === null
      ? current
      : { ...current, values: { ...current.values, [field]: value } })
  }

  const cancelInlineEdit = () => {
    if (inlineSaving) return
    setInlineEdit(null)
  }

  const saveInlineEdit = async () => {
    if (!inlineEdit || !inlineDirty) {
      setInlineEdit(null)
      return
    }

    const changes: Partial<Record<BookImportField, string | null>> = {}
    IMPORT_FIELDS.forEach((field) => {
      if (field === 'inventory_number' && !bookImport?.has_inventory_column) return
      if (inlineEdit.values[field] !== inlineEdit.baseline[field]) {
        changes[field] = inlineEdit.values[field].trim() === '' ? null : inlineEdit.values[field]
      }
    })

    if (Object.keys(changes).length === 0) {
      setInlineEdit(null)
      return
    }

    setInlineSaving(true)
    try {
      await api.patch(apiPaths.bookImportRow(importId, inlineEdit.rowId), {
        input_version: inlineEdit.inputVersion,
        changes,
      })
      toast.success(t('bookImports.rows.saved'))
      setInlineEdit(null)
      await queryClient.invalidateQueries({ queryKey: ['book-import-rows', importId] })
      await queryClient.invalidateQueries({ queryKey: ['book-import', importId] })
    } catch (error) {
      toast.error(getErrorMessage(error, t('errors.unexpected')))
      if (error instanceof ApiError && error.status === 409) {
        setInlineEdit(null)
        await queryClient.invalidateQueries({ queryKey: ['book-import-rows', importId] })
      }
    } finally {
      setInlineSaving(false)
    }
  }

  const cancelImport = async () => {
    if (!bookImport) return
    setLifecyclePending(true)
    try {
      await api.post(apiPaths.bookImportCancel(importId))
      toast.success(t('bookImports.actions.cancelled'))
      setCancelOpen(false)
      await queryClient.invalidateQueries({ queryKey: ['book-import', importId] })
      await queryClient.invalidateQueries({ queryKey: ['book-import-rows', importId] })
      await queryClient.invalidateQueries({ queryKey: ['book-imports'] })
    } catch (error) {
      toast.error(getErrorMessage(error, t('errors.unexpected')))
    } finally {
      setLifecyclePending(false)
    }
  }

  const deleteImport = async () => {
    setLifecyclePending(true)
    try {
      await api.delete(apiPaths.bookImport(importId))
      toast.success(t('bookImports.actions.deleted'))
      setDeleteOpen(false)
      await queryClient.invalidateQueries({ queryKey: ['book-imports'] })
      navigate('/books/imports')
    } catch (error) {
      toast.error(getErrorMessage(error, t('errors.unexpected')))
    } finally {
      setLifecyclePending(false)
    }
  }

  if (isLoading) return <PageLoader />

  if (!can('book_imports.viewAny')) {
    return <p className="text-sm text-muted-foreground">{t('errors.forbidden')}</p>
  }

  if (libraryId === null) {
    return <p className="text-sm text-muted-foreground">{t('bookImports.activeLibraryRequired')}</p>
  }

  if (importQuery.isLoading) return <PageLoader />

  if (importQuery.isError || !bookImport) {
    return (
      <div className="space-y-4">
        <Button asChild variant="outline">
          <Link to="/books/imports"><ArrowLeft />{t('bookImports.backToImports')}</Link>
        </Button>
        <p className="text-sm text-destructive">
          {getErrorMessage(importQuery.error, t('errors.unexpected'))}
        </p>
      </div>
    )
  }

  const currentFiltersMatch = !filterStatusConflicts
  const allMatchingChecked = allMatchingSelected && matchingSelection.status === selectionStatus
  const headerCheckboxDisabled = !canSelect || !currentFiltersMatch || matchingCount === 0

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div className="space-y-2">
          <Button asChild variant="outline" size="sm">
            <Link to="/books/imports"><ArrowLeft />{t('bookImports.backToImports')}</Link>
          </Button>
          <div>
            <div className="flex flex-wrap items-center gap-2">
              <h2 className="font-brand-heading text-2xl font-bold tracking-tight">{bookImport.original_filename}</h2>
              <Badge variant={IMPORT_STATUS_VARIANTS[bookImport.status]}>
                {t(`bookImports.status.${bookImport.status}`)}
              </Badge>
            </div>
            <p className="mt-1 text-sm text-muted-foreground">
              {t('bookImports.detail.subtitle', {
                library: bookImport.library_name ?? activeLibrary?.name ?? '',
                sheet: bookImport.sheet_name ?? '—',
              })}
            </p>
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {canCancelImport ? (
            <Button variant="outline" size="sm" onClick={() => setCancelOpen(true)}>
              <Ban />{t('bookImports.actions.cancel')}
            </Button>
          ) : null}
          {canDeleteImport ? (
            <Button variant="destructive" size="sm" onClick={() => setDeleteOpen(true)}>
              <Trash2 />{t('bookImports.actions.delete')}
            </Button>
          ) : null}
        </div>
      </div>

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <SummaryCard label={t('bookImports.summary.total')} value={bookImport.total_rows} />
        <SummaryCard label={t('bookImports.summary.available')} value={bookImport.pending_rows} />
        <SummaryCard label={t('bookImports.summary.preparationFailed')} value={bookImport.preparation_failed_rows} />
        <SummaryCard label={t('bookImports.summary.processing')} value={bookImport.processing_rows} />
        <SummaryCard label={t('bookImports.summary.imported')} value={bookImport.imported_rows} />
        <SummaryCard label={t('bookImports.summary.importFailed')} value={bookImport.import_failed_rows} />
      </div>

      {!bookImport.has_inventory_column ? (
        <div className="flex items-start gap-3 rounded-md border border-amber-500/40 bg-amber-500/10 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
          <TriangleAlert className="mt-0.5 size-4 shrink-0" />
          <div>
            <p className="font-medium">{t('bookImports.inventoryGuidance.noColumn')}</p>
            {!activeLibrary?.inv_number_auto ? (
              <p className="mt-1 text-amber-800/80 dark:text-amber-300/80">
                {t('bookImports.inventoryGuidance.manualLibrary')}
              </p>
            ) : null}
          </div>
        </div>
      ) : activeLibrary?.inv_number_auto ? (
        <div className="flex items-start gap-3 rounded-md border border-amber-500/40 bg-amber-500/10 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
          <Info className="mt-0.5 size-4 shrink-0" />
          <p>{t('bookImports.inventoryGuidance.hasColumn')}</p>
        </div>
      ) : null}

      {bookImport.failure_message ? (
        <div className="rounded-md border border-destructive/40 bg-destructive/5 p-4 text-sm text-destructive">
          {bookImport.failure_message}
        </div>
      ) : null}

      <div className="space-y-4 rounded-lg border p-4">
        <div className="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <label className="grid gap-1 text-sm">
              <span>{t('bookImports.filters.status')}</span>
              <select
                className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                value={filters.status}
                onChange={(event) => updateFilter('status', event.target.value as RowFilters['status'])}
              >
                <option value="">{t('bookImports.filters.allStatuses')}</option>
                {(['pending', 'processing', 'imported', 'failed'] as const).map((status) => (
                  <option key={status} value={status}>{t(`bookImports.rowStatus.${status}`)}</option>
                ))}
              </select>
            </label>
            <label className="grid gap-1 text-sm">
              <span>{t('bookImports.filters.failureCode')}</span>
              <select
                className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                value={filters.failure_code}
                onChange={(event) => updateFilter('failure_code', event.target.value as RowFilters['failure_code'])}
              >
                <option value="">{t('bookImports.filters.allFailureCodes')}</option>
                {FAILURE_CODES.map((code) => (
                  <option key={code} value={code}>{t(`bookImports.failureCodes.${code}`)}</option>
                ))}
              </select>
            </label>
            <label className="grid gap-1 text-sm">
              <span>{t('bookImports.filters.attemptMode')}</span>
              <select
                className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                value={filters.attempt_mode}
                onChange={(event) => updateFilter('attempt_mode', event.target.value as RowFilters['attempt_mode'])}
              >
                <option value="">{t('bookImports.filters.allModes')}</option>
                <option value="isbn_check">{t('bookImports.modes.isbn_check')}</option>
                <option value="excel_direct">{t('bookImports.modes.excel_direct')}</option>
              </select>
            </label>
            <form className="grid gap-1 text-sm" onSubmit={submitSearch}>
              <label htmlFor="book-import-search">{t('bookImports.filters.search')}</label>
              <div className="flex gap-2">
                <Input
                  id="book-import-search"
                  value={searchInput}
                  onChange={(event) => setSearchInput(event.target.value)}
                  placeholder={t('bookImports.filters.searchPlaceholder')}
                />
                <Button type="submit" variant="outline" aria-label={t('common.search')}>
                  <Search />
                </Button>
              </div>
            </form>
          </div>
        </div>

        <div className="flex flex-col gap-3 rounded-md bg-muted/40 p-3 lg:flex-row lg:items-end lg:justify-between">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
            <label className="grid gap-1 text-sm">
              <span>{t('bookImports.selection.targetStatus')}</span>
              <select
                className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                value={selectionStatus}
                onChange={(event) => changeSelectionStatus(event.target.value as 'pending' | 'failed')}
                disabled={importBusy}
              >
                <option value="pending">{t('bookImports.selection.pending')}</option>
                <option value="failed">{t('bookImports.selection.failed')}</option>
              </select>
            </label>
            <label className="grid gap-1 text-sm">
              <span>{t('bookImports.selection.mode')}</span>
              <select
                className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                value={mode}
                onChange={(event) => setMode(event.target.value as BookImportMode)}
                disabled={importBusy}
              >
                <option value="isbn_check">{t('bookImports.modes.isbn_check')}</option>
                <option value="excel_direct">{t('bookImports.modes.excel_direct')}</option>
              </select>
            </label>
            <p className="max-w-xl text-sm text-muted-foreground">
              {allMatchingSelected
                ? t('bookImports.selection.allSelected', { count: matchingSelection.count })
                : t('bookImports.selection.selected', { count: selectedCount })}
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            {selectedCount > 0 ? (
              <Button variant="outline" onClick={clearSelection}>
                {t('bookImports.selection.clear')}
              </Button>
            ) : null}
            <Button
              variant="brand"
              onClick={() => setConfirmOpen(true)}
              disabled={!canSelect || selectedCount === 0}
            >
              {selectionStatus === 'pending' ? <Play /> : <RotateCw />}
              {selectionStatus === 'pending' ? t('bookImports.selection.start') : t('bookImports.selection.retry')}
            </Button>
          </div>
        </div>

        {filterStatusConflicts ? (
          <p className="text-sm text-muted-foreground">{t('bookImports.selection.statusFilterConflict')}</p>
        ) : null}

        {rowQuery.isError ? (
          <div className="rounded-md border border-destructive/40 p-4 text-sm text-destructive">
            {getErrorMessage(rowQuery.error, t('errors.unexpected'))}
            <Button variant="link" className="ml-2" onClick={() => void rowQuery.refetch()}>
              {t('bookImports.retry')}
            </Button>
          </div>
        ) : (
          <>
            <label className="flex w-fit items-center gap-2 text-sm">
              <span>{t('bookImports.rows.dataView')}</span>
              <select
                className="h-9 rounded-md border border-input bg-background px-3"
                value={rowDataView}
                onChange={(event) => setRowDataView(event.target.value as RowDataView)}
              >
                <option value="original">{t('bookImports.rows.originalData')}</option>
                <option value="effective">{t('bookImports.rows.effectiveData')}</option>
                <option value="resolved">{t('bookImports.rows.resolvedData')}</option>
              </select>
            </label>
            <div className="rounded-md border">
              <Table className="min-w-max" containerClassName="max-h-[70vh] overflow-y-auto">
                <TableHeader className="sticky top-0 z-20 bg-background shadow-sm [&_th]:sticky [&_th]:top-0 [&_th]:bg-background">
                  <TableRow>
                    <TableHead className="sticky left-0 z-30 w-28 min-w-28 bg-background">
                      <div className="flex items-center gap-1.5">
                        <Checkbox
                          checked={allMatchingChecked}
                          onCheckedChange={toggleMatchingSelection}
                          disabled={headerCheckboxDisabled}
                          aria-label={t('bookImports.selection.selectAllMatching', { count: matchingCount })}
                        />
                        <span title={t('bookImports.help.row')}>{t('bookImports.table.row')}</span>
                      </div>
                    </TableHead>
                    <TableHead>
                      <div className="flex items-center gap-1.5">
                        <span title={t('bookImports.help.status')}>{t('bookImports.table.status')}</span>
                      </div>
                    </TableHead>
                    <TableHead>
                      <div className="flex items-center gap-1.5">
                        <span title={t('bookImports.help.failure')}>{t('bookImports.table.failure')}</span>
                      </div>
                    </TableHead>
                    {IMPORT_FIELDS.map((field) => (
                      <TableHead key={field}>
                        <div className="flex items-center gap-1.5">
                          <span title={t(`bookImports.help.fields.${field}`)}>
                            {t(`bookImports.fields.${field}`)}
                          </span>
                        </div>
                      </TableHead>
                    ))}
                    <TableHead>{t('bookImports.table.assignedInventory')}</TableHead>
                    <TableHead>{t('bookImports.table.barcode')}</TableHead>
                    <TableHead>{t('bookImports.table.actions')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {rowQuery.isLoading ? (
                    <TableRow><TableCell colSpan={IMPORT_FIELDS.length + 6}><PageLoader /></TableCell></TableRow>
                  ) : rows.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={IMPORT_FIELDS.length + 6} className="p-8 text-center text-muted-foreground">
                        {t('bookImports.rows.empty')}
                      </TableCell>
                    </TableRow>
                  ) : rows.map((row) => {
                    const rowIsEditing = inlineEdit?.rowId === row.id
                    const rowSelectable = !rowIsEditing
                      && canSelect
                      && row.status === selectionStatus
                      && rowStatusIsSelectable(row.status)
                    const checked = allMatchingSelected
                      ? row.status === selectionStatus
                      : selectedIds.has(row.id)
                    const rowPayload = rowDataView === 'original'
                      ? row.raw_payload
                      : rowDataView === 'resolved'
                        ? row.resolved_payload ?? {}
                        : row.effective_input

                    return (
                      <TableRow key={row.id}>
                        <TableCell className="sticky left-0 z-10 w-28 min-w-28 bg-background font-medium">
                          <div className="flex items-center gap-2">
                            <Checkbox
                              checked={checked}
                              disabled={!rowSelectable || allMatchingSelected || rowIsEditing}
                              onCheckedChange={(value) => toggleRowSelection(row, value === true)}
                              aria-label={t('bookImports.selection.selectRow', { row: row.row_number })}
                            />
                            {row.row_number}
                          </div>
                        </TableCell>
                        <TableCell>
                          <Badge variant={ROW_STATUS_VARIANTS[row.status]}>
                            {t(`bookImports.rowStatus.${row.status}`)}
                          </Badge>
                          {row.attempt_mode ? (
                            <div className="mt-1 text-xs text-muted-foreground">{t(`bookImports.modes.${row.attempt_mode}`)}</div>
                          ) : null}
                        </TableCell>
                        <TableCell className="max-w-56 whitespace-normal">
                          {row.failure_code ? (
                            <>
                              {row.failure_stage ? (
                                <p className="text-xs font-medium text-muted-foreground">
                                  {t(`bookImports.failureStages.${row.failure_stage}`)}
                                </p>
                              ) : null}
                              <p className="font-medium">{t(`bookImports.failureCodes.${row.failure_code}`)}</p>
                              {row.failure_message ? <p className="text-xs text-muted-foreground">{row.failure_message}</p> : null}
                            </>
                          ) : '—'}
                        </TableCell>
                        {IMPORT_FIELDS.map((field) => (
                          <TableCell key={field} className="max-w-64 whitespace-normal">
                            {rowIsEditing && inlineEdit ? (
                              TEXTAREA_FIELDS.has(field) ? (
                                <textarea
                                  className="min-h-20 w-full min-w-56 rounded-md border border-input bg-background px-2 py-1 text-sm"
                                  value={inlineEdit.values[field]}
                                  disabled={inlineSaving}
                                  onChange={(event) => updateInlineValue(field, event.target.value)}
                                />
                              ) : (
                                <Input
                                  className="min-w-40"
                                  value={inlineEdit.values[field]}
                                  disabled={inlineSaving || (field === 'inventory_number' && !bookImport.has_inventory_column)}
                                  onChange={(event) => updateInlineValue(field, event.target.value)}
                                />
                              )
                            ) : (
                              <>
                                {renderValue(rowPayload[field])}
                                {rowDataView === 'effective' && Object.prototype.hasOwnProperty.call(row.input_overrides, field) ? (
                                  <span className="ml-1 text-xs text-muted-foreground">
                                    {t('bookImports.rows.previewCorrection')}
                                  </span>
                                ) : null}
                              </>
                            )}
                          </TableCell>
                        ))}
                        <TableCell>
                          {row.assigned_inventory_number
                            ?? (!bookImport.has_inventory_column && activeLibrary?.inv_number_auto
                              ? t('bookImports.rows.assignedOnImport')
                              : '—')}
                        </TableCell>
                        <TableCell>{row.barcode ?? '—'}</TableCell>
                        <TableCell>
                          {rowIsEditing ? (
                            <div className="flex items-center gap-1">
                              <Button
                                variant="brand"
                                size="icon-sm"
                                onClick={() => void saveInlineEdit()}
                                disabled={inlineSaving || !inlineDirty}
                                aria-label={t('bookImports.rows.save')}
                              >
                                <Check />
                              </Button>
                              <Button
                                variant="ghost"
                                size="icon-sm"
                                onClick={cancelInlineEdit}
                                disabled={inlineSaving}
                                aria-label={t('common.cancel')}
                              >
                                <X />
                              </Button>
                            </div>
                          ) : (
                            <div className="flex items-center gap-1">
                              {row.book_id ? (
                                <Button asChild variant="ghost" size="sm">
                                  <Link to={`/books/titles/${row.book_id}`}>{t('bookImports.rows.openBook')}</Link>
                                </Button>
                              ) : null}
                              {row.book_copy_id ? (
                                <Button asChild variant="ghost" size="sm">
                                  <Link to={`/books/copies/${row.book_copy_id}`}>{t('bookImports.rows.openCopy')}</Link>
                                </Button>
                              ) : null}
                              {!importBusy && (row.status === 'pending' || row.status === 'failed') ? (
                                <Button
                                  variant="ghost"
                                  size="icon-sm"
                                  onClick={() => startInlineEdit(row)}
                                  disabled={inlineEdit !== null}
                                  aria-label={t('bookImports.rows.edit')}
                                >
                                  <Pencil />
                                </Button>
                              ) : null}
                            </div>
                          )}
                        </TableCell>
                      </TableRow>
                    )
                  })}
                </TableBody>
              </Table>
            </div>
            <div className="flex items-center justify-between gap-3">
              <label className="flex items-center gap-2 text-sm text-muted-foreground">
                {t('bookImports.rows.perPage')}
                <select
                  className="h-8 rounded-md border border-input bg-background px-2"
                  value={perPage}
                  onChange={(event) => {
                    setPerPage(Number(event.target.value))
                    setPage(1)
                  }}
                >
                  {PAGE_SIZE_OPTIONS.map((size) => <option key={size} value={size}>{size}</option>)}
                </select>
              </label>
            </div>
            {meta && meta.total > 0 ? (
              <PaginationBar
                currentPage={meta.current_page}
                lastPage={meta.last_page}
                total={meta.total}
                perPage={meta.per_page}
                onPageChange={setPage}
              />
            ) : null}
          </>
        )}
      </div>

      <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {selectionStatus === 'pending'
                ? t('bookImports.confirm.startTitle')
                : t('bookImports.confirm.retryTitle')}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {t('bookImports.confirm.description', {
                count: selectedCount,
                mode: t(`bookImports.modes.${mode}`),
              })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          {mode === 'excel_direct' ? (
            <p className="rounded-md bg-muted p-3 text-sm">{t('bookImports.confirm.excelDirectWarning')}</p>
          ) : null}
          <AlertDialogFooter>
            <AlertDialogCancel disabled={actionPending}>{t('common.cancel')}</AlertDialogCancel>
            <Button variant="brand" onClick={() => void submitSelection()} disabled={actionPending}>
              {actionPending ? t('bookImports.saving') : t('bookImports.confirm.confirm')}
            </Button>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={cancelOpen} onOpenChange={setCancelOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('bookImports.actions.cancelTitle')}</AlertDialogTitle>
            <AlertDialogDescription>{t('bookImports.actions.cancelText')}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={lifecyclePending}>{t('common.cancel')}</AlertDialogCancel>
            <Button variant="brand" onClick={() => void cancelImport()} disabled={lifecyclePending}>
              {lifecyclePending ? t('bookImports.saving') : t('bookImports.actions.cancelConfirm')}
            </Button>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('bookImports.actions.deleteTitle')}</AlertDialogTitle>
            <AlertDialogDescription>{t('bookImports.actions.deleteText')}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={lifecyclePending}>{t('common.cancel')}</AlertDialogCancel>
            <Button variant="destructive" onClick={() => void deleteImport()} disabled={lifecyclePending}>
              {lifecyclePending ? t('bookImports.saving') : t('bookImports.actions.deleteConfirm')}
            </Button>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

    </div>
  )
}

function SummaryCard({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-lg border p-4">
      <p className="text-sm text-muted-foreground">{label}</p>
      <p className="mt-1 text-2xl font-semibold tabular-nums">{value}</p>
    </div>
  )
}
