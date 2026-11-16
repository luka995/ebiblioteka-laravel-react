import { Link, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { ArrowLeft, BookOpen, CalendarClock, LibraryBig } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { storageUrl } from '@/lib/environment'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { PageLoader } from '@/components/ui/loader'
import type { CatalogBookResponse } from '@/types'

export function CatalogBookPage() {
  const { t } = useTranslation()
  const { id } = useParams<{ id: string }>()
  const bookId = Number(id)
  const queryClient = useQueryClient()

  const bookQuery = useQuery({
    queryKey: ['catalog-book', bookId],
    enabled: Number.isFinite(bookId) && bookId > 0,
    queryFn: () => api.get<CatalogBookResponse>(apiPaths.catalogBook(bookId)),
  })

  const invalidate = async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ['catalog-book', bookId] }),
      queryClient.invalidateQueries({ queryKey: ['reservations'] }),
    ])
  }

  const reserveMutation = useMutation({
    mutationFn: () => api.post(apiPaths.reservations, { book_id: bookId }),
    onSuccess: async () => {
      await invalidate()
      toast.success(t('reservations.reserved'))
    },
    onError: (error) => {
      if (error instanceof ApiError) {
        toast.error(error.messageText ?? t('errors.unexpected'))
      }
    },
  })

  const cancelMutation = useMutation({
    mutationFn: (reservationId: number) => api.delete(apiPaths.reservation(reservationId)),
    onSuccess: async () => {
      await invalidate()
      toast.success(t('reservations.cancelled'))
    },
    onError: (error) => {
      if (error instanceof ApiError) {
        toast.error(error.messageText ?? t('errors.unexpected'))
      }
    },
  })

  if (bookQuery.isLoading) return <PageLoader />

  if (bookQuery.isError || !bookQuery.data) {
    return (
      <div className="space-y-4">
        <Link to="/reservations" className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
          <ArrowLeft className="size-4" />
          {t('common.backToList')}
        </Link>
        <p className="text-sm text-muted-foreground">{t('reservations.bookNotFound')}</p>
      </div>
    )
  }

  const payload = bookQuery.data
  const book = payload.data
  const active = payload.active_reservation
  const cover = storageUrl(book.image_url) ?? book.cover_url
  const available = payload.availability.available

  return (
    <div className="mx-auto max-w-4xl space-y-6">
      <Link to="/reservations" className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
        <ArrowLeft className="size-4" />
        {t('reservations.myTitle')}
      </Link>

      <Card>
        <CardContent className="grid gap-6 p-6 sm:grid-cols-[180px_1fr]">
          <div className="flex aspect-[3/4] items-center justify-center overflow-hidden rounded-lg bg-muted">
            {cover ? (
              <img src={cover} alt={book.name} className="h-full w-full object-cover" />
            ) : (
              <BookOpen className="size-12 text-muted-foreground" />
            )}
          </div>

          <div className="min-w-0 space-y-4">
            <div>
              <div className="flex flex-wrap items-center gap-2">
                <h2 className="font-brand-heading text-2xl font-bold tracking-tight">{book.name}</h2>
                {active ? (
                  <Badge className="border-transparent bg-brand-soft text-brand">{active.status_label}</Badge>
                ) : null}
              </div>
              <p className="mt-1 text-sm text-muted-foreground">
                {(book.authors ?? []).map((author) => author.display_name).join('; ') || '—'}
              </p>
              {payload.publish_year ? (
                <p className="text-sm text-muted-foreground">{payload.publish_year}</p>
              ) : null}
            </div>

            {book.description ? <p className="text-sm leading-relaxed text-muted-foreground">{book.description}</p> : null}

            <div className="flex flex-wrap gap-x-6 gap-y-2 text-sm">
              <span className="inline-flex items-center gap-1.5">
                <LibraryBig className="size-4 text-muted-foreground" />
                {book.library_name}
              </span>
              <span className="inline-flex items-center gap-1.5">
                <BookOpen className="size-4 text-muted-foreground" />
                {t('reservations.availableCopies', { count: available })}
              </span>
              <span className="inline-flex items-center gap-1.5">
                <CalendarClock className="size-4 text-muted-foreground" />
                {t('reservations.holdHours', { count: payload.reservation_hours })}
              </span>
            </div>

            {active ? (
              <div className="space-y-3 rounded-lg border border-brand/30 bg-brand-soft/40 p-4">
                <p className="text-sm font-medium">
                  {t('reservations.activeUntil', { date: active.valid_till_formatted ?? '—' })}
                </p>
                {active.barcode ? (
                  <p className="text-xs text-muted-foreground">
                    {t('fields.barcode')}: <span className="font-mono">{active.barcode}</span>
                  </p>
                ) : null}
                {active.can.cancel ? (
                  <Button
                    variant="outline"
                    disabled={cancelMutation.isPending}
                    onClick={() => cancelMutation.mutate(active.id)}
                  >
                    {t('reservations.cancelAction')}
                  </Button>
                ) : null}
              </div>
            ) : (
              <div className="space-y-2">
                <Button
                  variant="brand"
                  disabled={!payload.can_reserve || reserveMutation.isPending}
                  onClick={() => reserveMutation.mutate()}
                >
                  {t('reservations.reserveAction')}
                </Button>
                {!payload.membership ? (
                  <p className="text-sm text-destructive">{t('reservations.notMember')}</p>
                ) : available === 0 ? (
                  <p className="text-sm text-muted-foreground">{t('reservations.noCopies')}</p>
                ) : null}
              </div>
            )}
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="font-brand-heading text-base">{t('reservations.howItWorksTitle')}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2 text-sm text-muted-foreground">
          <p>{t('reservations.howItWorksText', { count: payload.reservation_hours })}</p>
        </CardContent>
      </Card>
    </div>
  )
}
