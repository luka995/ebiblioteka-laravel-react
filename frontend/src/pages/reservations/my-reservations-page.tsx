import { Link } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { CalendarClock, LibraryBig } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { PageLoader } from '@/components/ui/loader'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import type { Reservation } from '@/types'

export function MyReservationsPage() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  const reservationsQuery = useQuery({
    queryKey: ['reservations'],
    queryFn: () => api.get<{ data: Reservation[] }>(apiPaths.reservations),
  })

  const cancelMutation = useMutation({
    mutationFn: (id: number) => api.delete(apiPaths.reservation(id)),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['reservations'] })
      toast.success(t('reservations.cancelled'))
    },
    onError: (error) => {
      if (error instanceof ApiError) {
        toast.error(error.messageText ?? t('errors.unexpected'))
      }
    },
  })

  if (reservationsQuery.isLoading) return <PageLoader />

  const reservations = reservationsQuery.data?.data ?? []

  return (
    <div className="space-y-6">
      <div>
        <h2 className="font-brand-heading text-2xl font-bold tracking-tight">{t('reservations.myTitle')}</h2>
        <p className="mt-1 text-sm text-muted-foreground">{t('reservations.mySubtitle')}</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="font-brand-heading text-base">
            {t('reservations.activeCount', { count: reservations.length })}
          </CardTitle>
        </CardHeader>
        <CardContent>
          {reservations.length === 0 ? (
            <div className="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed py-14 text-center">
              <CalendarClock className="size-10 text-muted-foreground" />
              <p className="text-sm font-medium">{t('reservations.emptyTitle')}</p>
              <p className="max-w-sm text-sm text-muted-foreground">{t('reservations.emptyText')}</p>
            </div>
          ) : (
            <>
              <div className="hidden md:block">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t('fields.title')}</TableHead>
                      <TableHead>{t('fields.barcode')}</TableHead>
                      <TableHead>{t('fields.library')}</TableHead>
                      <TableHead>{t('reservations.validTill')}</TableHead>
                      <TableHead />
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {reservations.map((reservation) => (
                      <TableRow key={reservation.id}>
                        <TableCell>
                          <div className="min-w-0">
                            <p className="truncate font-medium">{reservation.book_name ?? '—'}</p>
                            <p className="truncate text-xs text-muted-foreground">
                              {reservation.book_authors ?? '—'}
                            </p>
                          </div>
                        </TableCell>
                        <TableCell className="font-mono text-xs">{reservation.barcode ?? '—'}</TableCell>
                        <TableCell>{reservation.library_name ?? '—'}</TableCell>
                        <TableCell>{reservation.valid_till_formatted ?? '—'}</TableCell>
                        <TableCell className="text-right">
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
                        <p className="text-xs text-muted-foreground">{reservation.book_authors ?? '—'}</p>
                      </div>
                      <Badge variant="secondary">{reservation.status_label}</Badge>
                    </div>
                    <dl className="mt-3 space-y-1 text-sm">
                      <div className="flex items-center gap-1.5 text-muted-foreground">
                        <LibraryBig className="size-4" />
                        {reservation.library_name ?? '—'}
                      </div>
                      <div className="flex items-center gap-1.5 text-muted-foreground">
                        <CalendarClock className="size-4" />
                        {t('reservations.validTill')}: {reservation.valid_till_formatted ?? '—'}
                      </div>
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
                  </div>
                ))}
              </div>
            </>
          )}
        </CardContent>
      </Card>

      <p className="text-center text-sm text-muted-foreground">
        <Link to="/" className="underline-offset-4 hover:underline">
          {t('nav.dashboard')}
        </Link>
      </p>
    </div>
  )
}
