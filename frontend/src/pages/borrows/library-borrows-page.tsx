import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { BookMarked, CalendarClock, Plus, RotateCcw, Search } from 'lucide-react'
import { api, apiPaths } from '@/lib/api'
import { truncateBookTitle } from '@/lib/text'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { DateInput } from '@/components/ui/date-input'
import { Input } from '@/components/ui/input'
import { PageLoader } from '@/components/ui/loader'
import { SortableTableHead } from '@/components/ui/sortable-table-head'
import { PaginationBar } from '@/components/pagination-bar'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { IssueLoanModal } from '@/components/borrows/issue-loan-modal'
import { ReturnLoanDialog } from '@/components/borrows/return-loan-dialog'
import { useAuth } from '@/hooks/useAuth'
import { useTableSort } from '@/hooks/useTableSort'
import type { Borrow, BorrowStatus, PaginatedResponse } from '@/types'

const PAGE_SIZE = 25

const STATUS_VARIANTS: Record<BorrowStatus, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  active: 'default',
  overdue: 'destructive',
  returned: 'secondary',
}

type BorrowStatusFilter = 'active' | 'history' | 'overdue' | 'all'

const STATUS_FILTERS: BorrowStatusFilter[] = ['active', 'history', 'overdue', 'all']

function isBorrowStatusFilter(value: string | null): value is BorrowStatusFilter {
  return value !== null && STATUS_FILTERS.includes(value as BorrowStatusFilter)
}

export function LibraryBorrowsPage() {
  const { t } = useTranslation()
  const { activeLibrary, isLoading, can } = useAuth()
  const queryClient = useQueryClient()
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [page, setPage] = useState(1)
  const [issueOpen, setIssueOpen] = useState(false)
  const [returnTarget, setReturnTarget] = useState<Borrow | null>(null)
  const libraryId = activeLibrary?.id ?? null

  const statusParam = searchParams.get('status')
  const status: BorrowStatusFilter = isBorrowStatusFilter(statusParam) ? statusParam : 'active'
  const { sort, direction, onSort } = useTableSort(
    status === 'history' ? 'return_date' : 'valid_till',
    status === 'history' ? 'desc' : 'asc',
  )

  const changeStatus = (next: BorrowStatusFilter) => {
    setPage(1)
    setSearchParams(
      (previous) => {
        const params = new URLSearchParams(previous)
        params.set('status', next)

        return params
      },
      { replace: true },
    )
  }

  const handleSort = (column: string) => {
    setPage(1)
    onSort(column)
  }

  const borrowsQuery = useQuery({
    queryKey: ['library-borrows', { libraryId, status, search, dateFrom, dateTo, page, sort, direction }],
    enabled: libraryId !== null,
    queryFn: async () => {
      const params = new URLSearchParams({
        status,
        page: String(page),
        per_page: String(PAGE_SIZE),
        sort,
        direction,
      })

      if (search.trim() !== '') params.set('search', search.trim())
      if (dateFrom) params.set('date_from', dateFrom)
      if (dateTo) params.set('date_to', dateTo)

      return api.get<PaginatedResponse<Borrow>>(`${apiPaths.libraryBorrows}?${params.toString()}`)
    },
  })

  const refresh = () => {
    void queryClient.invalidateQueries({ queryKey: ['library-borrows'] })
    void queryClient.invalidateQueries({ queryKey: ['borrows'] })
    void queryClient.invalidateQueries({ queryKey: ['book-copies'] })
    void queryClient.invalidateQueries({ queryKey: ['book'] })
    void queryClient.invalidateQueries({ queryKey: ['book-copy-history'] })
    void queryClient.invalidateQueries({ queryKey: ['library-reservations'] })
  }

  if (isLoading) return <PageLoader />

  if (!can('borrows.viewAny')) {
    return <p className="text-sm text-muted-foreground">{t('errors.forbidden')}</p>
  }

  if (activeLibrary === null) {
    return (
      <Card>
        <CardContent className="py-14 text-center text-sm text-muted-foreground">
          {t('borrows.selectLibrary')}
        </CardContent>
      </Card>
    )
  }

  const borrows = borrowsQuery.data?.data ?? []
  const meta = borrowsQuery.data?.meta

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h2 className="font-brand-heading text-2xl font-bold tracking-tight">{t('borrows.libraryTitle')}</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            {t('borrows.librarySubtitle', { library: activeLibrary.name })}
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Button asChild variant="outline">
            <Link to="/reservations/library">
              <BookMarked />
              {t('borrows.reservationsLink')}
            </Link>
          </Button>
          <Button variant="brand" onClick={() => setIssueOpen(true)}>
            <Plus />
            {t('borrows.newLoan')}
          </Button>
        </div>
      </div>

      <Card>
        <CardHeader className="gap-4 sm:flex-row sm:items-center sm:justify-between">
          <CardTitle className="font-brand-heading text-base">
            {t('borrows.count', { count: meta?.total ?? 0 })}
          </CardTitle>

          <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
            <div className="relative">
              <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                value={search}
                onChange={(event) => {
                  setSearch(event.target.value)
                  setPage(1)
                }}
                placeholder={t('borrows.searchPlaceholder')}
                className="pl-9 sm:w-64"
              />
            </div>

            <DateInput
              value={dateFrom}
              onChange={(value) => {
                setDateFrom(value)
                setPage(1)
              }}
              placeholder={t('borrows.dateFrom')}
              className="sm:w-36"
              aria-label={t('borrows.dateFrom')}
            />

            <DateInput
              value={dateTo}
              onChange={(value) => {
                setDateTo(value)
                setPage(1)
              }}
              placeholder={t('borrows.dateTo')}
              className="sm:w-36"
              aria-label={t('borrows.dateTo')}
            />

            <Select
              value={status}
              onValueChange={(value) => changeStatus(value as BorrowStatusFilter)}
            >
              <SelectTrigger className="sm:w-40">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="active">{t('borrows.statusActive')}</SelectItem>
                <SelectItem value="overdue">{t('borrows.statusOverdue')}</SelectItem>
                <SelectItem value="history">{t('borrows.statusHistory')}</SelectItem>
                <SelectItem value="all">{t('borrows.statusAll')}</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </CardHeader>

        <CardContent>
          {borrowsQuery.isLoading ? (
            <PageLoader />
          ) : borrows.length === 0 ? (
            <div className="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed py-14 text-center">
              <CalendarClock className="size-10 text-muted-foreground" />
              <p className="text-sm font-medium">{t('borrows.emptyTitle')}</p>
              <p className="max-w-sm text-sm text-muted-foreground">
                {status === 'history' ? t('borrows.historyEmptyText') : t('borrows.libraryEmptyText')}
              </p>
            </div>
          ) : (
            <>
              <div className="hidden lg:block">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <SortableTableHead column="user" sort={sort} direction={direction} onSort={handleSort}>{t('borrows.user')}</SortableTableHead>
                      <SortableTableHead column="title" sort={sort} direction={direction} onSort={handleSort}>{t('fields.title')}</SortableTableHead>
                      <SortableTableHead column="barcode" sort={sort} direction={direction} onSort={handleSort}>{t('fields.barcode')}</SortableTableHead>
                      <SortableTableHead column="from_date" sort={sort} direction={direction} onSort={handleSort}>{t('borrows.fromDate')}</SortableTableHead>
                      <SortableTableHead column="valid_till" sort={sort} direction={direction} onSort={handleSort}>{t('borrows.validTill')}</SortableTableHead>
                      <SortableTableHead column="return_date" sort={sort} direction={direction} onSort={handleSort}>{t('borrows.returnDate')}</SortableTableHead>
                      <SortableTableHead column="status" sort={sort} direction={direction} onSort={handleSort}>{t('borrows.status')}</SortableTableHead>
                      <TableHead />
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {borrows.map((borrow) => (
                      <TableRow key={borrow.id}>
                        <TableCell>
                          <p className="font-medium">{borrow.user_name ?? '—'}</p>
                          <p className="font-mono text-xs text-muted-foreground">{borrow.user_barcode ?? '—'}</p>
                        </TableCell>
                        <TableCell>
                          <p className="font-medium" title={borrow.book_name ?? undefined}>
                            {truncateBookTitle(borrow.book_name)}
                          </p>
                          <p className="text-xs text-muted-foreground">{borrow.book_authors ?? '—'}</p>
                        </TableCell>
                        <TableCell className="font-mono text-xs">{borrow.barcode ?? '—'}</TableCell>
                        <TableCell>{borrow.from_date_formatted ?? '—'}</TableCell>
                        <TableCell>{borrow.valid_till_formatted ?? '—'}</TableCell>
                        <TableCell>{borrow.return_date_formatted ?? '—'}</TableCell>
                        <TableCell>
                          <Badge variant={STATUS_VARIANTS[borrow.status]}>
                            {t(`borrows.statusLabel.${borrow.status}`)}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-right">
                          {borrow.can?.returnBook ? (
                            <Button variant="outline" size="sm" onClick={() => setReturnTarget(borrow)}>
                              <RotateCcw />
                              {t('borrows.returnAction')}
                            </Button>
                          ) : null}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>

              <div className="space-y-3 lg:hidden">
                {borrows.map((borrow) => (
                  <div key={borrow.id} className="rounded-lg border p-4">
                    <div className="flex items-start justify-between gap-3">
                      <div className="min-w-0">
                        <p className="font-medium" title={borrow.book_name ?? undefined}>
                          {truncateBookTitle(borrow.book_name)}
                        </p>
                        <p className="text-xs text-muted-foreground">{borrow.user_name ?? '—'}</p>
                      </div>
                      <Badge variant={STATUS_VARIANTS[borrow.status]}>
                        {t(`borrows.statusLabel.${borrow.status}`)}
                      </Badge>
                    </div>
                    <dl className="mt-3 space-y-1 text-sm text-muted-foreground">
                      <div className="flex items-center gap-1.5">
                        <CalendarClock className="size-4" />
                        {borrow.valid_till_formatted ?? '—'}
                      </div>
                      <div className="font-mono text-xs">{borrow.barcode ?? '—'}</div>
                    </dl>
                    {borrow.can?.returnBook ? (
                      <Button
                        variant="outline"
                        size="sm"
                        className="mt-3 w-full"
                        onClick={() => setReturnTarget(borrow)}
                      >
                        <RotateCcw />
                        {t('borrows.returnAction')}
                      </Button>
                    ) : null}
                  </div>
                ))}
              </div>
            </>
          )}
        </CardContent>

        {meta && meta.total > 0 ? (
          <PaginationBar
            currentPage={meta.current_page}
            lastPage={meta.last_page}
            total={meta.total}
            perPage={meta.per_page}
            onPageChange={setPage}
          />
        ) : null}
      </Card>

      {issueOpen ? (
        <IssueLoanModal
          open
          onClose={() => setIssueOpen(false)}
          onSuccess={() => refresh()}
        />
      ) : null}

      <ReturnLoanDialog
        borrow={returnTarget}
        onClose={() => setReturnTarget(null)}
        onSuccess={() => refresh()}
      />
    </div>
  )
}
