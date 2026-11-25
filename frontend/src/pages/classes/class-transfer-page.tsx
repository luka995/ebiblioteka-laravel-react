import { useEffect, useMemo, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { AlertTriangle, ArrowLeft, ArrowLeftRight, CheckCircle2, GraduationCap, Users } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { PageLoader } from '@/components/ui/loader'
import { SortableTableHead } from '@/components/ui/sortable-table-head'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useAuth } from '@/hooks/useAuth'
import type { ClassTransferPreview, ClassTransferResult, ClassTransferRow, SchoolClassOptions } from '@/types'

function statusVariant(status: ClassTransferRow['status']): 'default' | 'secondary' | 'destructive' | 'outline' {
  switch (status) {
    case 'ready':
      return 'secondary'
    case 'needs_target':
      return 'destructive'
    default:
      return 'outline'
  }
}

function isTransferableRow(row: ClassTransferRow): boolean {
  return row.status !== 'graduating'
    && row.status !== 'empty'
    && row.status !== 'ineligible'
    && row.eligible_students_count > 0
}

export function ClassTransferPage() {
  const { t } = useTranslation()
  const { can, activeLibrary } = useAuth()
  const queryClient = useQueryClient()
  const [searchParams, setSearchParams] = useSearchParams()

  const [mappings, setMappings] = useState<Record<number, string>>({})
  const [selected, setSelected] = useState<Record<number, boolean>>({})
  const [createMissing, setCreateMissing] = useState(false)
  const [confirmOpen, setConfirmOpen] = useState(false)
  const [result, setResult] = useState<ClassTransferResult | null>(null)
  const [previewSort, setPreviewSort] = useState<{ sort: string; direction: 'asc' | 'desc' }>({ sort: 'name', direction: 'asc' })
  const [resultSort, setResultSort] = useState<{ sort: string; direction: 'asc' | 'desc' }>({ sort: 'source_class_name', direction: 'asc' })

  const optionsQuery = useQuery({
    queryKey: ['school-class-options'],
    queryFn: () => api.get<{ data: SchoolClassOptions }>(apiPaths.schoolClassOptions),
  })

  const options = optionsQuery.data?.data
  const requestedSourceId = searchParams.get('source_school_year_id')
  const availableSourceYears = useMemo(() => {
    if (!options) return []

    const activeYear = options.school_years.find((year) => year.id === options.active_school_year_id)

    return activeYear
      ? options.school_years.filter((year) => year.year >= activeYear.year - 1)
      : options.school_years
  }, [options])

  const sourceYearId = useMemo(() => {
    if (!options) return ''

    if (requestedSourceId && availableSourceYears.some((year) => String(year.id) === requestedSourceId)) {
      return requestedSourceId
    }

    if (
      options.default_transfer_source_school_year_id
      && availableSourceYears.some((year) => year.id === options.default_transfer_source_school_year_id)
    ) {
      return String(options.default_transfer_source_school_year_id)
    }

    const firstWithClasses = availableSourceYears.find((year) => year.classes_count > 0)

    return firstWithClasses ? String(firstWithClasses.id) : ''
  }, [options, availableSourceYears, requestedSourceId])

  const previewQuery = useQuery({
    queryKey: ['class-transfer-preview', sourceYearId],
    queryFn: () => api.get<{ data: ClassTransferPreview }>(apiPaths.classTransferPreview(Number(sourceYearId))),
    enabled: Boolean(sourceYearId) && Boolean(activeLibrary),
  })

  const preview = previewQuery.data?.data

  useEffect(() => {
    if (!preview) {
      setMappings({})
      setSelected({})
      setCreateMissing(false)

      return
    }

    const next: Record<number, string> = {}
    const nextSelected: Record<number, boolean> = {}
    let hasRowThatNeedsCreation = false

    for (const row of preview.classes) {
      const transferable = isTransferableRow(row)
      nextSelected[row.id] = transferable

      if (!transferable) continue

      if (row.suggested_target_class_id) {
        next[row.id] = String(row.suggested_target_class_id)
      } else {
        next[row.id] = ''
        if (row.can_create_target) hasRowThatNeedsCreation = true
      }
    }

    setMappings(next)
    setSelected(nextSelected)
    setCreateMissing(preview.can_create_missing_classes && hasRowThatNeedsCreation)
  }, [preview, sourceYearId])

  const transferableRows = useMemo(
    () => (preview?.classes ?? []).filter((row) => isTransferableRow(row)),
    [preview],
  )

  const rowsToTransfer = useMemo(
    () => transferableRows.filter((row) => selected[row.id]),
    [transferableRows, selected],
  )

  const previewValue = (row: ClassTransferRow, column: string): string | number => {
    switch (column) {
      case 'grade': return row.grade ?? 0
      case 'students': return row.students_count
      case 'status': return row.status
      case 'target': {
        const targetId = mappings[row.id]
        return row.target_options.find((option) => String(option.id) === targetId)?.name
          ?? row.proposed_target_class_name
          ?? ''
      }
      default: return row.name
    }
  }

  const sortedPreviewClasses = useMemo(() => {
    const rows = [...(preview?.classes ?? [])]

    return rows.sort((a, b) => {
      const left = previewValue(a, previewSort.sort)
      const right = previewValue(b, previewSort.sort)
      const result = typeof left === 'number' && typeof right === 'number'
        ? left - right
        : String(left).localeCompare(String(right), 'sr')

      return previewSort.direction === 'asc' ? result : -result
    })
  }, [preview, mappings, previewSort])

  const sortedResultClasses = useMemo(() => {
    if (!result) return []

    return [...result.classes].sort((a, b) => {
      const left = resultSort.sort === 'transferred'
        ? a.transferred
        : resultSort.sort === 'skipped'
          ? a.skipped_ineligible + a.skipped_conflict
          : resultSort.sort === 'target'
            ? (a.target_class_name ?? '')
            : a.source_class_name
      const right = resultSort.sort === 'transferred'
        ? b.transferred
        : resultSort.sort === 'skipped'
          ? b.skipped_ineligible + b.skipped_conflict
          : resultSort.sort === 'target'
            ? (b.target_class_name ?? '')
            : b.source_class_name
      const comparison = typeof left === 'number' && typeof right === 'number'
        ? left - right
        : String(left).localeCompare(String(right), 'sr')

      return resultSort.direction === 'asc' ? comparison : -comparison
    })
  }, [result, resultSort])

  const togglePreviewSort = (column: string) => {
    setPreviewSort((current) => ({
      sort: column,
      direction: current.sort === column && current.direction === 'asc' ? 'desc' : 'asc',
    }))
  }

  const toggleResultSort = (column: string) => {
    setResultSort((current) => ({
      sort: column,
      direction: current.sort === column && current.direction === 'asc' ? 'desc' : 'asc',
    }))
  }

  const allSelected = transferableRows.length > 0 && transferableRows.every((row) => selected[row.id])
  const someSelected = transferableRows.some((row) => selected[row.id])

  const toggleAll = (checked: boolean | 'indeterminate') => {
    const value = checked === true

    setSelected((current) => {
      const next = { ...current }
      for (const row of transferableRows) next[row.id] = value

      return next
    })
  }

  const rowsWithoutTarget = rowsToTransfer.filter(
    (row) => !mappings[row.id] && !(createMissing && row.can_create_target),
  )

  const selectedEligibleStudents = rowsToTransfer.reduce(
    (total, row) => total + row.eligible_students_count,
    0,
  )

  const canSubmit = Boolean(
    preview?.can_transfer
    && preview.target_year
    && rowsToTransfer.length > 0
    && rowsWithoutTarget.length === 0
    && !previewQuery.isFetching,
  )

  const transferMutation = useMutation({
    mutationFn: async () => {
      if (!preview?.target_year) {
        throw new Error('Target school year is missing.')
      }

      const response = await api.post<{ data: ClassTransferResult }>(apiPaths.classTransfer, {
        source_school_year_id: preview.source_year.id,
        target_school_year_id: preview.target_year.id,
        create_missing_target_classes: createMissing,
        mappings: rowsToTransfer.map((row) => ({
          source_class_id: row.id,
          target_class_id: mappings[row.id] ? Number(mappings[row.id]) : null,
        })),
      })

      return response.data
    },
    onSuccess: async (transferResult) => {
      setResult(transferResult)
      setConfirmOpen(false)

      toast.success(t('classes.transfer.success'), {
        description: t('classes.transfer.successSummary', {
          transferred: transferResult.totals.transferred,
          already: transferResult.totals.already_enrolled,
          skipped: transferResult.totals.skipped,
          created: transferResult.totals.created_target_classes,
        }),
      })

      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['school-classes'] }),
        queryClient.invalidateQueries({ queryKey: ['school-class-options'] }),
        queryClient.invalidateQueries({ queryKey: ['class-transfer-preview', sourceYearId] }),
      ])
    },
    onError: (error) => {
      toast.error(
        error instanceof ApiError
          ? (Object.values(error.validationErrors ?? {})[0]?.[0] ?? error.messageText ?? t('errors.unexpected'))
          : t('errors.unexpected'),
      )
    },
  })

  const changeSourceYear = (value: string) => {
    setResult(null)
    setConfirmOpen(false)
    setSearchParams(
      (previous) => {
        const next = new URLSearchParams(previous)
        next.set('source_school_year_id', value)

        return next
      },
      { replace: true },
    )
  }

  if (!can('classes.transferStudents')) {
    return <p className="text-sm text-muted-foreground">{t('errors.forbidden')}</p>
  }

  if (!activeLibrary) {
    return <p className="text-sm text-muted-foreground">{t('classes.transfer.activeLibraryRequired')}</p>
  }

  if (optionsQuery.isLoading) {
    return <PageLoader />
  }

  const sourceYear = availableSourceYears.find((year) => String(year.id) === sourceYearId)

  return (
    <div className="space-y-6">
      <div>
        <Button asChild variant="outline" size="sm">
          <Link to="/classes">
            <ArrowLeft />
            {t('classes.transfer.backToClasses')}
          </Link>
        </Button>
      </div>

      <div>
        <h2 className="font-brand-heading flex items-center gap-2 text-2xl font-bold tracking-tight">
          <ArrowLeftRight className="size-6 text-brand" />
          {t('classes.transfer.title')}
        </h2>
        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">{t('classes.transfer.subtitle')}</p>
      </div>

      <Card>
        <CardContent className="grid gap-4 pt-6 sm:grid-cols-2">
          <div className="space-y-2">
            <label className="text-sm font-medium">{t('classes.transfer.sourceYear')}</label>
            <Select value={sourceYearId} onValueChange={changeSourceYear}>
              <SelectTrigger className="w-full">
                <SelectValue placeholder={t('classes.transfer.sourceYear')} />
              </SelectTrigger>
              <SelectContent>
                {availableSourceYears.map((year) => (
                  <SelectItem key={year.id} value={String(year.id)}>
                    {year.name}
                    {year.is_active ? ` · ${t('classes.fields.activeYear')}` : ''}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium">{t('classes.transfer.targetYear')}</label>
            <Select value={preview?.target_year ? String(preview.target_year.id) : ''} disabled>
              <SelectTrigger className="w-full">
                <SelectValue placeholder={previewQuery.isLoading ? '...' : t('classes.transfer.targetYearMissing')} />
              </SelectTrigger>
              <SelectContent>
                {preview?.target_year ? (
                  <SelectItem value={String(preview.target_year.id)}>
                    {preview.target_year.name}
                  </SelectItem>
                ) : null}
              </SelectContent>
            </Select>
            <p className="text-xs text-muted-foreground">{t('classes.transfer.targetYearHint')}</p>
          </div>
        </CardContent>
      </Card>

      {preview && !preview.target_year_exists ? (
        <div className="flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
          <AlertTriangle className="mt-0.5 size-4 shrink-0" />
          <p>{t('classes.transfer.targetYearMissing')}</p>
        </div>
      ) : null}

      {preview?.target_year_exists && !preview.target_year_is_active ? (
        <div className="flex items-start gap-3 rounded-lg border p-4 text-sm text-muted-foreground">
          <AlertTriangle className="mt-0.5 size-4 shrink-0" />
          <p>{t('classes.transfer.targetYearInactive')}</p>
        </div>
      ) : null}

      {preview && preview.classes.length === 0 ? (
        <div className="flex flex-col items-center gap-2 rounded-lg border bg-card px-6 py-16 text-center">
          <GraduationCap className="size-8 text-muted-foreground" />
          <p className="font-medium">{t('classes.transfer.noClasses')}</p>
          {sourceYear?.classes_count === 0 ? (
            <p className="max-w-md text-sm text-muted-foreground">{t('classes.transfer.sourceEmpty')}</p>
          ) : null}
        </div>
      ) : null}

      {previewQuery.isLoading && sourceYearId ? (
        <div className="rounded-lg border bg-card py-10">
          <PageLoader />
        </div>
      ) : null}

      {previewQuery.isError ? (
        <p className="text-sm text-destructive">{t('errors.unexpected')}</p>
      ) : null}

      {preview && preview.classes.length > 0 ? (
        <>
          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <Card className="gap-1 py-4">
              <CardHeader className="px-4">
                <CardDescription>{t('classes.transfer.summary.classes')}</CardDescription>
                <CardTitle className="text-2xl">{preview.summary.classes_count}</CardTitle>
              </CardHeader>
            </Card>
            <Card className="gap-1 py-4">
              <CardHeader className="px-4">
                <CardDescription>{t('classes.transfer.summary.students')}</CardDescription>
                <CardTitle className="text-2xl">{preview.summary.students_count}</CardTitle>
              </CardHeader>
            </Card>
            <Card className="gap-1 py-4">
              <CardHeader className="px-4">
                <CardDescription>{t('classes.transfer.summary.eligible')}</CardDescription>
                <CardTitle className="text-2xl">{preview.summary.eligible_students_count}</CardTitle>
              </CardHeader>
            </Card>
            <Card className="gap-1 py-4">
              <CardHeader className="px-4">
                <CardDescription>{t('classes.transfer.summary.graduating')}</CardDescription>
                <CardTitle className="text-2xl">{preview.summary.graduating_students_count}</CardTitle>
              </CardHeader>
            </Card>
          </div>

          <Card>
            <CardHeader>
              <CardTitle className="font-brand-heading text-base">{t('classes.transfer.title')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="overflow-x-auto rounded-md border">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead className="w-10">
                        <Checkbox
                          checked={allSelected ? true : someSelected ? 'indeterminate' : false}
                          onCheckedChange={toggleAll}
                          aria-label={t('classes.transfer.selectAll')}
                        />
                      </TableHead>
                      <SortableTableHead column="name" sort={previewSort.sort} direction={previewSort.direction} onSort={togglePreviewSort}>{t('classes.transfer.columns.sourceClass')}</SortableTableHead>
                      <SortableTableHead column="grade" sort={previewSort.sort} direction={previewSort.direction} onSort={togglePreviewSort}>{t('classes.transfer.columns.grade')}</SortableTableHead>
                      <SortableTableHead column="students" sort={previewSort.sort} direction={previewSort.direction} onSort={togglePreviewSort}>{t('classes.transfer.columns.students')}</SortableTableHead>
                      <SortableTableHead column="status" sort={previewSort.sort} direction={previewSort.direction} onSort={togglePreviewSort}>{t('classes.transfer.columns.status')}</SortableTableHead>
                      <SortableTableHead column="target" sort={previewSort.sort} direction={previewSort.direction} onSort={togglePreviewSort} className="min-w-56">{t('classes.transfer.columns.targetClass')}</SortableTableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {sortedPreviewClasses.map((row) => {
                      const targetValue = mappings[row.id] ?? ''
                      const transferable = isTransferableRow(row)
                      const isSelected = Boolean(selected[row.id])
                      const willCreate = isSelected && createMissing && row.can_create_target

                      return (
                        <TableRow key={row.id} className={!isSelected ? 'opacity-60' : undefined}>
                          <TableCell>
                            <Checkbox
                              checked={isSelected}
                              disabled={!transferable}
                              onCheckedChange={(checked) => setSelected((current) => ({
                                ...current,
                                [row.id]: checked === true,
                              }))}
                              aria-label={t('classes.transfer.selectClass', { name: row.name })}
                            />
                          </TableCell>
                          <TableCell>
                            <span className="block font-medium">{row.name}</span>
                            {row.teacher_name ? (
                              <span className="block text-xs text-muted-foreground">{row.teacher_name}</span>
                            ) : null}
                          </TableCell>
                          <TableCell>{row.grade_full_label ?? row.grade_label ?? '—'}</TableCell>
                          <TableCell>
                            <span className="inline-flex items-center gap-1.5">
                              <Users className="size-3.5 text-muted-foreground" />
                              {t('classes.transfer.studentsCount', { count: row.students_count })}
                            </span>
                            <span className="block text-xs text-muted-foreground">
                              {t('classes.transfer.eligibleCount', {
                                eligible: row.eligible_students_count,
                                total: row.students_count,
                              })}
                            </span>
                            {row.already_enrolled_count > 0 ? (
                              <span className="block text-xs text-amber-600 dark:text-amber-400">
                                {t('classes.transfer.alreadyEnrolled', { count: row.already_enrolled_count })}
                              </span>
                            ) : null}
                          </TableCell>
                          <TableCell>
                            <Badge variant={statusVariant(row.status)}>
                              {t(`classes.transfer.status.${row.status}`)}
                            </Badge>
                          </TableCell>
                          <TableCell>
                            {!transferable ? (
                              row.status === 'graduating' ? (
                                <span className="text-sm text-muted-foreground">{t('classes.transfer.graduating')}</span>
                              ) : row.status === 'empty' ? (
                                <span className="text-sm text-muted-foreground">{t('classes.transfer.emptyClass')}</span>
                              ) : (
                                <span className="text-sm text-muted-foreground">{t('classes.transfer.ineligibleClass')}</span>
                              )
                            ) : !isSelected ? (
                              <span className="text-sm text-muted-foreground">—</span>
                            ) : willCreate ? (
                              <span className="text-sm text-muted-foreground">
                                {t('classes.transfer.willCreate', { name: row.proposed_target_class_name ?? '—' })}
                              </span>
                            ) : row.target_options.length === 0 ? (
                              <span className="text-sm text-destructive">{t('classes.transfer.noTarget')}</span>
                            ) : (
                              <Select
                                value={targetValue}
                                onValueChange={(value) => setMappings((current) => ({ ...current, [row.id]: value }))}
                              >
                                <SelectTrigger className="w-full">
                                  <SelectValue placeholder={t('classes.transfer.status.needs_selection')} />
                                </SelectTrigger>
                                <SelectContent>
                                  {row.target_options.map((option) => (
                                    <SelectItem key={option.id} value={String(option.id)}>
                                      {option.name}
                                      {' · '}
                                      {t('classes.transfer.studentsCount', { count: option.students_count })}
                                    </SelectItem>
                                  ))}
                                </SelectContent>
                              </Select>
                            )}
                          </TableCell>
                        </TableRow>
                      )
                    })}
                  </TableBody>
                </Table>
              </div>

              {preview.can_create_missing_classes ? (
                <label htmlFor="class-transfer-create-missing" className="flex cursor-pointer items-start gap-3 rounded-md border p-4">
                  <Checkbox
                    id="class-transfer-create-missing"
                    checked={createMissing}
                    onCheckedChange={(checked) => setCreateMissing(checked === true)}
                    className="mt-0.5"
                  />
                  <span>
                    <span className="block text-sm font-medium">{t('classes.transfer.createMissing')}</span>
                    <span className="block text-xs text-muted-foreground">{t('classes.transfer.createMissingHint')}</span>
                  </span>
                </label>
              ) : null}

              {transferableRows.length > 0 && rowsToTransfer.length === 0 ? (
                <div className="flex items-start gap-3 rounded-lg border p-4 text-sm text-muted-foreground">
                  <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                  <p>{t('classes.transfer.noSelection')}</p>
                </div>
              ) : null}

              {rowsWithoutTarget.length > 0 ? (
                <div className="flex items-start gap-3 rounded-lg border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">
                  <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                  <p>{t('classes.transfer.noMappings')}</p>
                </div>
              ) : null}

              <div className="flex justify-end">
                <Button
                  type="button"
                  variant="brand"
                  disabled={!canSubmit || transferMutation.isPending}
                  onClick={() => setConfirmOpen(true)}
                >
                  <ArrowLeftRight />
                  {t('classes.transfer.submit')}
                </Button>
              </div>
            </CardContent>
          </Card>
        </>
      ) : null}

      {result ? (
        <Card className="border-emerald-300/60 bg-emerald-50/40 dark:border-emerald-900 dark:bg-emerald-950/20">
          <CardHeader>
            <CardTitle className="font-brand-heading flex items-center gap-2 text-base">
              <CheckCircle2 className="size-5 text-emerald-600" />
              {t('classes.transfer.resultTitle')}
            </CardTitle>
            <CardDescription>
              {t('classes.transfer.successSummary', {
                transferred: result.totals.transferred,
                already: result.totals.already_enrolled,
                skipped: result.totals.skipped,
                created: result.totals.created_target_classes,
              })}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="overflow-x-auto rounded-md border bg-card">
              <Table>
                <TableHeader>
                  <TableRow>
                    <SortableTableHead column="source_class_name" sort={resultSort.sort} direction={resultSort.direction} onSort={toggleResultSort}>{t('classes.transfer.result.source')}</SortableTableHead>
                    <SortableTableHead column="target" sort={resultSort.sort} direction={resultSort.direction} onSort={toggleResultSort}>{t('classes.transfer.result.target')}</SortableTableHead>
                    <SortableTableHead column="transferred" sort={resultSort.sort} direction={resultSort.direction} onSort={toggleResultSort}>{t('classes.transfer.result.transferred')}</SortableTableHead>
                    <SortableTableHead column="skipped" sort={resultSort.sort} direction={resultSort.direction} onSort={toggleResultSort}>{t('classes.transfer.result.skipped')}</SortableTableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {sortedResultClasses.map((row) => (
                    <TableRow key={row.source_class_id}>
                      <TableCell>{row.source_class_name}</TableCell>
                      <TableCell>
                        {row.target_class_name ?? t('classes.transfer.graduating')}
                        {row.created_target_class ? (
                          <Badge variant="outline" className="ml-2">{t('classes.transfer.created')}</Badge>
                        ) : null}
                      </TableCell>
                      <TableCell>{row.transferred}</TableCell>
                      <TableCell>{row.skipped_ineligible + row.skipped_conflict}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          </CardContent>
        </Card>
      ) : null}

      <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('classes.transfer.confirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('classes.transfer.confirmText', {
                source: preview?.source_year.name ?? '',
                target: preview?.target_year?.name ?? '',
                students: selectedEligibleStudents,
                classes: rowsToTransfer.length,
              })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={transferMutation.isPending}>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction
              variant="brand"
              disabled={transferMutation.isPending}
              onClick={() => transferMutation.mutate()}
            >
              {t('classes.transfer.submit')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
