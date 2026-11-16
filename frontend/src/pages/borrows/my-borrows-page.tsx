import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { BookOpen, CalendarClock, LibraryBig } from 'lucide-react'
import { api, apiPaths } from '@/lib/api'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { PageLoader } from '@/components/ui/loader'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import type { Borrow, BorrowStatus, PaginatedResponse } from '@/types'

const STATUS_VARIANTS: Record<BorrowStatus, 'default' | 'secondary' | 'destructive'> = {
  active: 'default',
  overdue: 'destructive',
  returned: 'secondary',
}

export function MyBorrowsPage() {
  const { t } = useTranslation()
  const [status, setStatus] = useState<'active' | 'history'>('active')

  const borrowsQuery = useQuery({
    queryKey: ['borrows', status],
    queryFn: () =>
      api.get<PaginatedResponse<Borrow>>(`${apiPaths.borrows}?status=${status}&per_page=100`),
  })

  if (borrowsQuery.isLoading) return <PageLoader />

  const borrows = borrowsQuery.data?.data ?? []

  return (
    <div className="space-y-6">
      <div>
        <h2 className="font-brand-heading text-2xl font-bold tracking-tight">{t('borrows.myTitle')}</h2>
        <p className="mt-1 text-sm text-muted-foreground">{t('borrows.mySubtitle')}</p>
      </div>

      <Tabs value={status} onValueChange={(value) => setStatus(value as 'active' | 'history')}>
        <TabsList>
          <TabsTrigger value="active">{t('borrows.statusActive')}</TabsTrigger>
          <TabsTrigger value="history">{t('borrows.statusHistory')}</TabsTrigger>
        </TabsList>
      </Tabs>

      <Card>
        <CardHeader>
          <CardTitle className="font-brand-heading text-base">
            {t('borrows.myCount', { count: borrowsQuery.data?.meta.total ?? 0 })}
          </CardTitle>
        </CardHeader>
        <CardContent>
          {borrows.length === 0 ? (
            <div className="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed py-14 text-center">
              <BookOpen className="size-10 text-muted-foreground" />
              <p className="text-sm font-medium">{t('borrows.emptyTitle')}</p>
              <p className="max-w-sm text-sm text-muted-foreground">
                {status === 'active' ? t('borrows.myEmptyText') : t('borrows.historyEmptyText')}
              </p>
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
                      <TableHead>{t('borrows.fromDate')}</TableHead>
                      <TableHead>{t('borrows.validTill')}</TableHead>
                      <TableHead>{t('borrows.status')}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {borrows.map((borrow) => (
                      <TableRow key={borrow.id}>
                        <TableCell>
                          <p className="font-medium">{borrow.book_name ?? '—'}</p>
                          <p className="text-xs text-muted-foreground">{borrow.book_authors ?? '—'}</p>
                        </TableCell>
                        <TableCell className="font-mono text-xs">{borrow.barcode ?? '—'}</TableCell>
                        <TableCell>{borrow.library_name ?? '—'}</TableCell>
                        <TableCell>{borrow.from_date_formatted ?? '—'}</TableCell>
                        <TableCell>{borrow.valid_till_formatted ?? '—'}</TableCell>
                        <TableCell>
                          <Badge variant={STATUS_VARIANTS[borrow.status]}>
                            {t(`borrows.statusLabel.${borrow.status}`)}
                          </Badge>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>

              <div className="space-y-3 md:hidden">
                {borrows.map((borrow) => (
                  <div key={borrow.id} className="rounded-lg border p-4">
                    <div className="flex items-start justify-between gap-3">
                      <div className="min-w-0">
                        <p className="font-medium">{borrow.book_name ?? '—'}</p>
                        <p className="text-xs text-muted-foreground">{borrow.book_authors ?? '—'}</p>
                      </div>
                      <Badge variant={STATUS_VARIANTS[borrow.status]}>
                        {t(`borrows.statusLabel.${borrow.status}`)}
                      </Badge>
                    </div>
                    <dl className="mt-3 space-y-1 text-sm text-muted-foreground">
                      <div className="flex items-center gap-1.5">
                        <LibraryBig className="size-4" />
                        {borrow.library_name ?? '—'}
                      </div>
                      <div className="flex items-center gap-1.5">
                        <CalendarClock className="size-4" />
                        {t('borrows.validTill')}: {borrow.valid_till_formatted ?? '—'}
                      </div>
                      <div className="font-mono text-xs">{borrow.barcode ?? '—'}</div>
                    </dl>
                  </div>
                ))}
              </div>
            </>
          )}
        </CardContent>
      </Card>

      <div className="flex flex-wrap justify-center gap-3 text-sm text-muted-foreground">
        <Link to="/reservations" className="underline-offset-4 hover:underline">
          {t('nav.myReservations')}
        </Link>
        <span aria-hidden>·</span>
        <Link to="/" className="underline-offset-4 hover:underline">
          {t('nav.dashboard')}
        </Link>
      </div>
    </div>
  )
}
