import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { CalendarClock, Search } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { DateInput } from '@/components/ui/date-input'
import { Input } from '@/components/ui/input'
import { PageLoader } from '@/components/ui/loader'
import { PaginationBar } from '@/components/pagination-bar'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { IssueLoanModal } from '@/components/borrows/issue-loan-modal'
import { useAuth } from '@/hooks/useAuth'
import type { PaginatedResponse, Reservation } from '@/types'

const PAGE_SIZE = 25

export function LibraryReservationsPage() {
  const { t } = useTranslation()
  const { activeLibrary, isLoading } = useAuth()
  const queryClient = useQueryClient()
  const [status, setStatus] = useState<'active' | 'history' | 'all'>('active')
  const [search, setSearch] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [page, setPage] = useState(1)
  const [issueTarget, setIssueTarget] = useState<Reservation | null>(null)
  const libraryId = activeLibrary?.id ?? null

  const reservationsQuery = useQuery({
    queryKey: ['library-reservations', { libraryId, status, search, dateFrom, dateTo, page }],
    enabled: libraryId !== null,
    queryFn: async () => {
      const params = new URLSearchParams({
        status,
        page: String(page),
        per_page: String(PAGE_SIZE),
      })

      if (search.trim() !== '') {
        params.set('search', search.trim())
      }

      if (dateFrom) params.set('date_from', dateFrom)
      if (dateTo) params.set('date_to', dateTo)

      return api.get<PaginatedResponse<Reservation>>(`${apiPaths.libraryReservations}?${params.toString()}`)
    },
  })

  const cancelMutation = useMutation({
    mutationFn: (id: number) => api.delete(apiPaths.reservation(id)),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['library-reservations'] })
      await queryClient.invalidateQueries({ queryKey: ['reservations'] })
      toast.success(t('reservations.cancelled'))
    },
    onError: (error) => {
      if (error instanceof ApiError) {
        toast.error(error.messageText ?? t('errors.unexpected'))
      }
    },
  })

  if (isLoading) return <PageLoader />

  if (activeLibrary === null) {
    return (
      <Card>
        <CardContent className="py-14 text-center text-sm text-muted-foreground">
          {t('reservations.selectLibrary')}
        </CardContent>
      </Card>
    )
  }

  const reservations = reservationsQuery.data?.data ?? []
  const meta = reservationsQuery.data?.meta

  return (
    <div className="space-y-6">
      <div>
        <h2 className="font-brand-heading text-2xl font-bold tracking-tight">
          {t('reservations.libraryTitle')}
        </h2>
        <p className="mt-1 text-sm text-muted-foreground">
          {t('reservations.librarySubtitle', { library: activeLibrary.name })}
        </p>
      </div>

      <Card>
        <CardHeader className="gap-4 sm:flex-row sm:items-center sm:justify-between">
          <CardTitle className="font-brand-heading text-base">
            {t('reservations.libraryCount', { count: meta?.total ?? 0 })}
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
                placeholder={t('reservations.searchPlaceholder')}
                className="pl-9 sm:w-64"
              />
            </div>

            <DateInput
              value={dateFrom}
              onChange={(value) => {
                setDateFrom(value)
                setPage(1)
              }}
              placeholder={t('reservations.dateFrom')}
              className="sm:w-36"
              aria-label={t('reservations.dateFrom')}
            />

            <DateInput
              value={dateTo}
              onChange={(value) => {
                setDateTo(value)
                setPage(1)
              }}
              placeholder={t('reservations.dateTo')}
              className="sm:w-36"
              aria-label={t('reservations.dateTo')}
            />

            <Select
              value={status}
              onValueChange={(value) => {
                setStatus(value as 'active' | 'history' | 'all')
                setPage(1)
              }}
            >
              <SelectTrigger className="sm:w-44">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="active">{t('reservations.statusActive')}</SelectItem>
                <SelectItem value="history">{t('reservations.statusHistory')}</SelectItem>
                <SelectItem value="all">{t('reservations.statusAll')}</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </CardHeader>

        <CardContent>
          {reservationsQuery.isLoading ? (
            <PageLoader />
          ) : reservations.length === 0 ? (
            <div className="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed py-14 text-center">
              <CalendarClock className="size-10 text-muted-foreground" />
              <p className="text-sm font-medium">{t('reservations.emptyTitle')}</p>
              <p className="max-w-sm text-sm text-muted-foreground">
                {status === 'history' ? t('reservations.historyEmptyText') : t('reservations.libraryEmptyText')}
              </p>
            </div>
          ) : (
            <>
              <div className="hidden md:block">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t('reservations.user')}</TableHead>
                      <TableHead>{t('fields.title')}</TableHead>
                      <TableHead>{t('fields.barcode')}</TableHead>
                      <TableHead>{t('reservations.validTill')}</TableHead>
                      <TableHead>{t('reservations.statusColumn')}</TableHead>
                      <TableHead />
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {reservations.map((reservation) => (
                      <TableRow key={reservation.id}>
                        <TableCell>
                          <p className="font-medium">{reservation.user_name ?? '—'}</p>
                        </TableCell>
                        <TableCell>
                          <p className="font-medium">{reservation.book_name ?? '—'}</p>
                          <p className="text-xs text-muted-foreground">{reservation.book_authors ?? '—'}</p>
                        </TableCell>
                        <TableCell className="font-mono text-xs">{reservation.barcode ?? '—'}</TableCell>
                        <TableCell>{reservation.valid_till_formatted ?? '—'}</TableCell>
                        <TableCell>
                          <Badge variant="secondary">{reservation.status_label}</Badge>
                        </TableCell>
                        <TableCell className="text-right">
                          <div className="flex justify-end gap-1">
                            {reservation.can.issue ? (
                              <Button variant="outline" size="sm" onClick={() => setIssueTarget(reservation)}>
                                {t('borrows.issueAction')}
                              </Button>
                            ) : null}
                            {reservation.can.cancel ? (
                              <Button
                                variant="outline"
                                size="sm"
                                disabled={cancelMutation.isPending}
                                onClick={() => cancelMutation.mutate(reservation.id)}
                              >
                                {t('reservations.cancelAction')}
                              </Button>
                            ) : null}
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>

              <div className="space-y-3 md:hidden">
                {reservations.map((reservation) => (
                  <div key={reservation.id} className="rounded-lg border p-4">
                    <div className="flex items-start justify-between gap-3">
                      <div className="min-w-0">
                        <p className="font-medium">{reservation.book_name ?? '—'}</p>
                        <p className="text-xs text-muted-foreground">{reservation.user_name ?? '—'}</p>
                      </div>
                      <Badge variant="secondary">{reservation.status_label}</Badge>
                    </div>
                    <dl className="mt-3 space-y-1 text-sm text-muted-foreground">
                      <div className="flex items-center gap-1.5">
                        <CalendarClock className="size-4" />
                        {t('reservations.validTill')}: {reservation.valid_till_formatted ?? '—'}
                      </div>
                      <div className="font-mono text-xs">{reservation.barcode ?? '—'}</div>
                    </dl>
                    {reservation.can.cancel ? (
                      <Button
                        variant="outline"
                        size="sm"
                        className="mt-3 w-full"
                        disabled={cancelMutation.isPending}
                        onClick={() => cancelMutation.mutate(reservation.id)}
                      >
                        {t('reservations.cancelAction')}
                      </Button>
                    ) : null}
                    {reservation.can.issue ? (
                      <Button
                        variant="brand"
                        size="sm"
                        className="mt-2 w-full"
                        onClick={() => setIssueTarget(reservation)}
                      >
                        {t('borrows.issueAction')}
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

      {issueTarget ? (
        <IssueLoanModal
          open
          reservation={issueTarget}
          onClose={() => setIssueTarget(null)}
          onSuccess={() => {
            void reservationsQuery.refetch()
            void queryClient.invalidateQueries({ queryKey: ['reservations'] })
            void queryClient.invalidateQueries({ queryKey: ['library-borrows'] })
            void queryClient.invalidateQueries({ queryKey: ['book-copies'] })
          }}
        />
      ) : null}
    </div>
  )
}
