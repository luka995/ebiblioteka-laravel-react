import { useState, type ReactNode } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { ArrowLeft, BookCheck, CalendarClock, KeyRound, LibraryBig, Pencil, Plus, Printer, Tag, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { ChangePasswordDialog } from '@/components/profile/change-password-dialog'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { PageLoader } from '@/components/ui/loader'
import { SortableTableHead } from '@/components/ui/sortable-table-head'
import { UserFormModal } from '@/components/users/user-form-modal'
import { UserBarcode } from '@/components/users/user-barcode'
import { UserBarcodePrintModal } from '@/components/users/user-barcode-print-modal'
import { UserMembershipModal } from '@/components/users/user-membership-modal'
import { UserTagModal } from '@/components/users/user-tag-modal'
import { IssueLoanModal } from '@/components/borrows/issue-loan-modal'
import { ReturnLoanDialog } from '@/components/borrows/return-loan-dialog'
import { useAuth } from '@/hooks/useAuth'
import { useTableSort } from '@/hooks/useTableSort'
import type { Borrow, BorrowStatus, PaginatedResponse, Reservation, User } from '@/types'

function initialsOf(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean)
  const first = parts[0]?.[0] ?? '?'
  const last = parts.length > 1 ? parts[parts.length - 1][0] : ''
  return (first + last).toUpperCase()
}

function InfoRow({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="grid grid-cols-[160px_1fr] gap-4 py-2">
      <dt className="text-sm font-medium text-muted-foreground">{label}</dt>
      <dd className="text-sm">{children || '—'}</dd>
    </div>
  )
}

export function UserDetailPage() {
  const { t } = useTranslation()
  const { can, activeLibrary } = useAuth()
  const navigate = useNavigate()
  const { id } = useParams<{ id: string }>()
  const userId = Number(id)
  const queryClient = useQueryClient()
  const [editOpen, setEditOpen] = useState(false)
  const [membershipOpen, setMembershipOpen] = useState(false)
  const [passwordOpen, setPasswordOpen] = useState(false)
  const [tagOpen, setTagOpen] = useState(false)
  const [printOpen, setPrintOpen] = useState(false)
  const [cancelTarget, setCancelTarget] = useState<Reservation | null>(null)
  const [borrowsStatus, setBorrowsStatus] = useState<'active' | 'history'>('active')
  const { sort: borrowSort, direction: borrowDirection, onSort: onBorrowSort } = useTableSort(
    borrowsStatus === 'history' ? 'return_date' : 'valid_till',
    borrowsStatus === 'history' ? 'desc' : 'asc',
  )
  const [reservationSort, setReservationSort] = useState<{ sort: string; direction: 'asc' | 'desc' }>({
    sort: 'valid_till',
    direction: 'asc',
  })
  const [issueOpen, setIssueOpen] = useState(false)
  const [returnTarget, setReturnTarget] = useState<Borrow | null>(null)
  const [issueReservation, setIssueReservation] = useState<Reservation | null>(null)

  const userQuery = useQuery({
    queryKey: ['users', userId],
    enabled: Number.isFinite(userId) && userId > 0,
    queryFn: async () => {
      const response = await api.get<{ data: User }>(apiPaths.user(userId))
      return response.data
    },
  })

  const borrowsQuery = useQuery({
    queryKey: ['user-borrows', userId, borrowsStatus, borrowSort, borrowDirection],
    enabled: Number.isFinite(userId) && userId > 0,
    queryFn: () =>
      api.get<PaginatedResponse<Borrow>>(
        `${apiPaths.userBorrows(userId)}?status=${borrowsStatus}&per_page=100&sort=${borrowSort}&direction=${borrowDirection}`,
      ),
  })

  const cancelReservation = useMutation({
    mutationFn: (reservationId: number) => api.delete(apiPaths.reservation(reservationId)),
    onSuccess: async () => {
      setCancelTarget(null)
      await queryClient.invalidateQueries({ queryKey: ['users', userId] })
      await queryClient.invalidateQueries({ queryKey: ['reservations'] })
      toast.success(t('reservations.cancelled'))
    },
    onError: (error) => {
      if (error instanceof ApiError) {
        toast.error(error.messageText ?? t('errors.unexpected'))
      }
    },
  })

  if (!can('users.viewAny')) {
    return <p className="text-sm text-muted-foreground">{t('errors.forbidden')}</p>
  }

  if (userQuery.isLoading) return <PageLoader />

  if (userQuery.isError || !userQuery.data) {
    return (
      <div className="space-y-4">
        <Link
          to="/users"
          className="inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
        >
          <ArrowLeft className="size-4" />
          {t('common.backToList')}
        </Link>
        <p className="text-sm text-muted-foreground">{t('users.notFound')}</p>
      </div>
    )
  }

  const target = userQuery.data

  const handleMembershipSuccess = async (message: string) => {
    toast.success(message, { description: target.email })
    const result = await userQuery.refetch()
    if (result.error) {
      navigate('/users')
    }
  }

  const memberLibraries = (target.libraries ?? []).map((library) => library.name)
  const reservations = target.reservations ?? []
  const reservationKey = (reservation: Reservation): string => {
    switch (reservationSort.sort) {
      case 'title': return reservation.book_name ?? ''
      case 'barcode': return reservation.barcode ?? ''
      case 'library': return reservation.library_name ?? ''
      default: return reservation.valid_till ?? ''
    }
  }
  const sortedReservations = [...reservations].sort((a, b) => {
    const result = reservationKey(a).localeCompare(reservationKey(b), 'sr')
    return reservationSort.direction === 'asc' ? result : -result
  })
  const toggleReservationSort = (column: string) => {
    setReservationSort((current) => ({
      sort: column,
      direction: current.sort === column && current.direction === 'asc' ? 'desc' : 'asc',
    }))
  }
  const tagsByLibrary = (target.libraries ?? []).map((library) => ({
    library,
    tags: (target.tags ?? []).filter((tag) => tag.library_id === library.id),
  }))

  return (
    <div className="space-y-6">
      <Link
        to="/users"
        className="inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
      >
        <ArrowLeft className="size-4" />
        {t('common.backToList')}
      </Link>

      <Card>
        <CardContent className="flex flex-col gap-4 p-6 sm:flex-row sm:items-center">
          <Avatar className="size-20 text-xl">
            <AvatarFallback className="bg-brand-soft font-semibold text-brand">
              {initialsOf(target.name)}
            </AvatarFallback>
          </Avatar>

          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <h2 className="font-brand-heading text-xl font-bold tracking-tight">{target.name}</h2>
              <Badge className="border-transparent bg-brand-soft text-brand">{target.role_label}</Badge>
            </div>
            {target.username ? <p className="text-sm text-muted-foreground">@{target.username}</p> : null}
            <p className="text-sm text-muted-foreground">{target.email}</p>

            <div className="mt-2 flex flex-wrap items-center gap-x-5 gap-y-1 text-sm text-muted-foreground">
              <span className="inline-flex items-center gap-1.5 font-mono text-xs">{target.bar_code ?? '—'}</span>
              {memberLibraries.length > 0 ? (
                <span className="inline-flex items-center gap-1.5">
                  <LibraryBig className="size-4" />
                  {memberLibraries.join(', ')}
                </span>
              ) : null}
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-2 sm:shrink-0">
            {target.can?.update ? (
              <Button variant="outline" className="flex-1 sm:flex-none" onClick={() => setEditOpen(true)}>
                <Pencil />
                {t('common.edit')}
              </Button>
            ) : null}
            {target.can?.update ? (
              <Button variant="outline" className="flex-1 sm:flex-none" onClick={() => setTagOpen(true)}>
                <Tag />
                {t('tags.assignButton')}
              </Button>
            ) : null}
            {target.can?.update ? (
              <Button variant="outline" className="flex-1 sm:flex-none" onClick={() => setPasswordOpen(true)}>
                <KeyRound />
                {t('profile.changePassword')}
              </Button>
            ) : null}
            {target.can?.forceDelete || target.can?.delete || target.can?.removeMemberships ? (
              <Button
                variant="outline"
                className="flex-1 sm:flex-none"
                onClick={() => setMembershipOpen(true)}
              >
                <Trash2 />
                {t('common.delete')}
              </Button>
            ) : null}
          </div>

          <UserBarcode value={target.bar_code} svg={target.bar_code_svg}>
            <Button variant="outline" size="sm" onClick={() => setPrintOpen(true)}>
              <Printer />
              {t('users.printBarcode')}
            </Button>
          </UserBarcode>
        </CardContent>
      </Card>

      <Tabs defaultValue="info">
        <TabsList>
          <TabsTrigger value="info">{t('users.tabs.info')}</TabsTrigger>
          <TabsTrigger value="reservations">{t('users.tabs.reservations')}</TabsTrigger>
          <TabsTrigger value="borrows">{t('users.tabs.borrows')}</TabsTrigger>
        </TabsList>

        <TabsContent value="info">
          <Card>
            <CardHeader>
              <CardTitle className="font-brand-heading text-base">{t('users.tabs.info')}</CardTitle>
            </CardHeader>
            <CardContent className="divide-y">
              <dl>
                <InfoRow label={t('fields.firstName')}>{target.first_name ?? target.name}</InfoRow>
                <InfoRow label={t('fields.lastName')}>{target.last_name}</InfoRow>
                <InfoRow label={t('fields.email')}>{target.email}</InfoRow>
                <InfoRow label={t('fields.username')}>{target.username ? `@${target.username}` : null}</InfoRow>
                <InfoRow label={t('fields.role')}>
                  {target.is_super_admin ? (
                    <Badge className="border-transparent bg-brand-soft text-brand">{t('roles.superadmin')}</Badge>
                  ) : (
                    <div className="flex flex-wrap gap-1.5">
                      {(target.libraries ?? []).map((library) => (
                        <Badge key={library.id} variant="secondary" className="border-transparent">
                          {library.name}: {library.role_label}
                        </Badge>
                      ))}
                    </div>
                  )}
                </InfoRow>
                <InfoRow label={t('fields.barcode')}>
                  <span className="font-mono">{target.bar_code ?? '—'}</span>
                </InfoRow>
                <InfoRow label={t('fields.jmbg')}>{target.jmbg}</InfoRow>
                <InfoRow label={t('fields.address')}>{target.address}</InfoRow>
                <InfoRow label={t('fields.city')}>{target.city}</InfoRow>
                <InfoRow label={t('fields.postCode')}>{target.post_code}</InfoRow>
                <InfoRow label={t('fields.libraries')}>
                  {memberLibraries.length > 0 ? memberLibraries.join(', ') : null}
                </InfoRow>
                <InfoRow label={t('fields.tags')}>
                  {tagsByLibrary.some(({ tags }) => tags.length > 0) ? (
                    <div className="space-y-1.5">
                      {tagsByLibrary.map(({ library, tags }) => (
                        <div key={library.id} className="flex flex-wrap items-center gap-1.5">
                          <span className="text-xs font-medium text-muted-foreground">{library.name}:</span>
                          {tags.length > 0 ? (
                            tags.map((tag) => (
                              <Badge key={tag.id} variant="secondary" className="border-transparent">
                                {tag.name}
                              </Badge>
                            ))
                          ) : (
                            <span className="text-xs text-muted-foreground">—</span>
                          )}
                        </div>
                      ))}
                    </div>
                  ) : null}
                </InfoRow>
              </dl>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="reservations">
          <Card>
            <CardHeader>
              <CardTitle className="font-brand-heading text-base">{t('users.tabs.reservations')}</CardTitle>
            </CardHeader>
            <CardContent>
              {reservations.length === 0 ? (
                <div className="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed py-14 text-center">
                  <CalendarClock className="size-10 text-muted-foreground" />
                  <p className="text-sm font-medium">{t('reservations.emptyTitle')}</p>
                  <p className="max-w-sm text-sm text-muted-foreground">{t('reservations.userEmptyText')}</p>
                </div>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <SortableTableHead column="title" sort={reservationSort.sort} direction={reservationSort.direction} onSort={toggleReservationSort}>{t('fields.title')}</SortableTableHead>
                      <SortableTableHead column="barcode" sort={reservationSort.sort} direction={reservationSort.direction} onSort={toggleReservationSort}>{t('fields.barcode')}</SortableTableHead>
                      <SortableTableHead column="library" sort={reservationSort.sort} direction={reservationSort.direction} onSort={toggleReservationSort}>{t('fields.library')}</SortableTableHead>
                      <SortableTableHead column="valid_till" sort={reservationSort.sort} direction={reservationSort.direction} onSort={toggleReservationSort}>{t('reservations.validTill')}</SortableTableHead>
                      <TableHead />
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {sortedReservations.map((reservation) => (
                      <TableRow key={reservation.id}>
                        <TableCell>
                          <p className="font-medium">{reservation.book_name ?? '—'}</p>
                          <p className="text-xs text-muted-foreground">{reservation.book_authors ?? '—'}</p>
                        </TableCell>
                        <TableCell className="font-mono text-xs">{reservation.barcode ?? '—'}</TableCell>
                        <TableCell>{reservation.library_name ?? '—'}</TableCell>
                        <TableCell>{reservation.valid_till_formatted ?? '—'}</TableCell>
                        <TableCell className="text-right">
                          <div className="flex justify-end gap-1">
                            {reservation.can.issue ? (
                              <Button variant="outline" size="sm" onClick={() => setIssueReservation(reservation)}>
                                {t('borrows.issueAction')}
                              </Button>
                            ) : null}
                            {reservation.can.cancel ? (
                              <Button variant="outline" size="sm" onClick={() => setCancelTarget(reservation)}>
                                {t('reservations.cancelAction')}
                              </Button>
                            ) : null}
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="borrows">
          <Card>
            <CardHeader className="gap-3 sm:flex-row sm:items-center sm:justify-between">
              <CardTitle className="font-brand-heading text-base">{t('users.tabs.borrows')}</CardTitle>
              {can('borrows.create') ? (
                <Button
                  variant="brand"
                  size="sm"
                  disabled={activeLibrary === null || !(target.libraries ?? []).some((library) => library.id === activeLibrary.id)}
                  onClick={() => setIssueOpen(true)}
                >
                  <Plus />
                  {t('borrows.newLoan')}
                </Button>
              ) : null}
            </CardHeader>
            <CardContent className="space-y-4">
              <Tabs value={borrowsStatus} onValueChange={(value) => setBorrowsStatus(value as 'active' | 'history')}>
                <TabsList>
                  <TabsTrigger value="active">{t('borrows.statusActive')}</TabsTrigger>
                  <TabsTrigger value="history">{t('borrows.statusHistory')}</TabsTrigger>
                </TabsList>
              </Tabs>

              {borrowsQuery.isLoading ? (
                <PageLoader />
              ) : (borrowsQuery.data?.data.length ?? 0) === 0 ? (
                <div className="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed py-14 text-center">
                  <BookCheck className="size-10 text-muted-foreground" />
                  <p className="text-sm font-medium">{t('borrows.emptyTitle')}</p>
                  <p className="max-w-sm text-sm text-muted-foreground">
                    {borrowsStatus === 'active' ? t('borrows.userEmptyText') : t('borrows.historyEmptyText')}
                  </p>
                </div>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <SortableTableHead column="title" sort={borrowSort} direction={borrowDirection} onSort={onBorrowSort}>{t('fields.title')}</SortableTableHead>
                      <SortableTableHead column="barcode" sort={borrowSort} direction={borrowDirection} onSort={onBorrowSort}>{t('fields.barcode')}</SortableTableHead>
                      <SortableTableHead column="valid_till" sort={borrowSort} direction={borrowDirection} onSort={onBorrowSort}>{t('borrows.validTill')}</SortableTableHead>
                      <SortableTableHead column="return_date" sort={borrowSort} direction={borrowDirection} onSort={onBorrowSort}>{t('borrows.returnDate')}</SortableTableHead>
                      <SortableTableHead column="status" sort={borrowSort} direction={borrowDirection} onSort={onBorrowSort}>{t('borrows.status')}</SortableTableHead>
                      <TableHead />
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {(borrowsQuery.data?.data ?? []).map((borrow) => (
                      <TableRow key={borrow.id}>
                        <TableCell>
                          <p className="font-medium">{borrow.book_name ?? '—'}</p>
                          <p className="text-xs text-muted-foreground">{borrow.book_authors ?? '—'}</p>
                        </TableCell>
                        <TableCell className="font-mono text-xs">{borrow.barcode ?? '—'}</TableCell>
                        <TableCell>{borrow.valid_till_formatted ?? '—'}</TableCell>
                        <TableCell>{borrow.return_date_formatted ?? '—'}</TableCell>
                        <TableCell>
                          <Badge variant={borrow.status === 'overdue' ? 'destructive' : 'secondary'}>
                            {t(`borrows.statusLabel.${borrow.status as BorrowStatus}`)}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-right">
                          {borrow.can?.returnBook ? (
                            <Button variant="outline" size="sm" onClick={() => setReturnTarget(borrow)}>
                              {t('borrows.returnAction')}
                            </Button>
                          ) : null}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      {editOpen ? (
        <UserFormModal
          open={editOpen}
          user={target}
          onClose={() => setEditOpen(false)}
          onSuccess={(email) => {
            toast.success(t('users.updated'), { description: email })
            void queryClient.invalidateQueries({ queryKey: ['users'] })
          }}
        />
      ) : null}

      {membershipOpen ? (
        <UserMembershipModal
          open={membershipOpen}
          user={target}
          mode="delete"
          onClose={() => setMembershipOpen(false)}
          onSuccess={(message) => void handleMembershipSuccess(message)}
        />
      ) : null}

      {tagOpen ? (
        <UserTagModal
          open={tagOpen}
          user={target}
          onClose={() => setTagOpen(false)}
          onSuccess={(message) => {
            toast.success(message)
            void queryClient.invalidateQueries({ queryKey: ['users'] })
          }}
        />
      ) : null}

      {printOpen ? (
        <UserBarcodePrintModal open={printOpen} user={target} onClose={() => setPrintOpen(false)} />
      ) : null}

      {issueOpen ? (
        <IssueLoanModal
          open
          user={target}
          onClose={() => setIssueOpen(false)}
          onSuccess={() => {
            void borrowsQuery.refetch()
            void queryClient.invalidateQueries({ queryKey: ['library-borrows'] })
            void queryClient.invalidateQueries({ queryKey: ['reservations'] })
            void queryClient.invalidateQueries({ queryKey: ['library-reservations'] })
          }}
        />
      ) : null}

      <ReturnLoanDialog
        borrow={returnTarget}
        onClose={() => setReturnTarget(null)}
        onSuccess={() => {
          void borrowsQuery.refetch()
          void queryClient.invalidateQueries({ queryKey: ['library-borrows'] })
        }}
      />

      {issueReservation ? (
        <IssueLoanModal
          open
          reservation={issueReservation}
          onClose={() => setIssueReservation(null)}
          onSuccess={() => {
            void userQuery.refetch()
            void borrowsQuery.refetch()
            void queryClient.invalidateQueries({ queryKey: ['library-reservations'] })
            void queryClient.invalidateQueries({ queryKey: ['reservations'] })
            void queryClient.invalidateQueries({ queryKey: ['library-borrows'] })
          }}
        />
      ) : null}

      <ChangePasswordDialog open={passwordOpen} onClose={() => setPasswordOpen(false)} target={target} />

      <AlertDialog open={cancelTarget !== null} onOpenChange={(open) => !open && setCancelTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('reservations.cancelConfirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('reservations.cancelConfirmText', { title: cancelTarget?.book_name ?? '' })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={cancelReservation.isPending}>
              {t('common.cancel')}
            </AlertDialogCancel>
            <AlertDialogAction
              variant="destructive"
              disabled={cancelReservation.isPending}
              onClick={() => cancelTarget && cancelReservation.mutate(cancelTarget.id)}
            >
              {t('reservations.cancelAction')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
