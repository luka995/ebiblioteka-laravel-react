import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { AlertTriangle, BookOpen, CalendarClock, ScanBarcode, UserRound } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { normalizeBarcode } from '@/lib/barcode'
import { useAuth } from '@/hooks/useAuth'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { DateInput } from '@/components/ui/date-input'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import type {
  BookCopy,
  Borrow,
  BorrowCopyLookup,
  BorrowLookupResponse,
  BorrowUserSummary,
  Reservation,
  ReservationConflict,
  User,
} from '@/types'

interface IssueLoanModalProps {
  open: boolean
  onClose: () => void
  onSuccess: (borrow: Borrow) => void
  user?: User | null
  copy?: BookCopy | null
  reservation?: Reservation | null
}

function isoDateAfterDays(days: number): string {
  const date = new Date()
  date.setDate(date.getDate() + days)
  const pad = (value: number) => String(value).padStart(2, '0')

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

function userSummaryFromUser(user: User): BorrowUserSummary {
  return {
    id: user.id,
    name: user.name,
    first_name: user.first_name,
    last_name: user.last_name,
    username: user.username,
    email: user.email,
    bar_code: user.bar_code,
    is_member: true,
    active_borrows: 0,
    active_reservations: 0,
  }
}

function userSummaryFromReservation(reservation: Reservation): BorrowUserSummary {
  return {
    id: reservation.user_id,
    name: reservation.user_name ?? `#${reservation.user_id}`,
    first_name: null,
    last_name: null,
    username: null,
    email: '',
    bar_code: null,
    is_member: true,
    active_borrows: 0,
    active_reservations: 1,
  }
}

function copySummaryFromReservation(reservation: Reservation): BorrowCopyLookup {
  return {
    id: reservation.book_copy_id,
    book_id: reservation.book_id,
    book_name: reservation.book_name ?? null,
    book_authors: reservation.book_authors ?? null,
    barcode: reservation.barcode ?? null,
    order_number: reservation.order_number ?? null,
    status: 'reserved',
    issuable: true,
    block_reason: null,
    active_reservation: {
      id: reservation.id,
      user_id: reservation.user_id,
      user_name: reservation.user_name ?? null,
      valid_till: reservation.valid_till,
      valid_till_formatted: reservation.valid_till_formatted,
    },
    active_borrow: null,
  }
}

export function IssueLoanModal({ open, onClose, onSuccess, user, copy, reservation }: IssueLoanModalProps) {
  const { t } = useTranslation()
  const { activeLibrary } = useAuth()
  const [userTerm, setUserTerm] = useState('')
  const [copyTerm, setCopyTerm] = useState('')
  const [selectedUser, setSelectedUser] = useState<BorrowUserSummary | null>(null)
  const [selectedCopy, setSelectedCopy] = useState<BorrowCopyLookup | null>(null)
  const [suggestions, setSuggestions] = useState<BorrowUserSummary[]>([])
  const [validTill, setValidTill] = useState('')
  const [lookupBusy, setLookupBusy] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [conflict, setConflict] = useState<ReservationConflict | null>(null)

  const reservationMode = reservation !== null && reservation !== undefined

  const reservedForOther = useMemo(() => {
    if (!selectedCopy?.active_reservation || !selectedUser) return false

    return selectedCopy.active_reservation.user_id !== selectedUser.id
  }, [selectedCopy, selectedUser])

  useEffect(() => {
    if (!open) return

    const initialUser = reservation
      ? userSummaryFromReservation(reservation)
      : user
        ? userSummaryFromUser(user)
        : null

    const initialCopy = reservation
      ? copySummaryFromReservation(reservation)
      : null

    setUserTerm(initialUser ? (initialUser.bar_code ?? initialUser.username ?? initialUser.name) : '')
    setCopyTerm(reservation?.barcode ?? copy?.barcode ?? '')
    setSelectedUser(initialUser)
    setSelectedCopy(initialCopy)
    setSuggestions([])
    setValidTill(isoDateAfterDays(activeLibrary?.lend_days ?? 30))
    setError(null)
    setConflict(null)
    setLookupBusy(false)
    setSubmitting(false)

    if (!reservation && copy?.barcode) {
      void lookupCopy(copy.barcode)
    }
  }, [open, user, copy, reservation, activeLibrary?.lend_days]) // eslint-disable-line react-hooks/exhaustive-deps

  const applyLookupDefaults = (payload: BorrowLookupResponse) => {
    if (payload.data.defaults?.valid_till) {
      setValidTill(payload.data.defaults.valid_till)
    }
  }

  const lookupUser = async (term: string) => {
    const value = term.trim()
    if (value === '') return

    setLookupBusy(true)
    setError(null)

    try {
      const response = await api.get<BorrowLookupResponse>(apiPaths.borrowLookup({ user: value }))
      applyLookupDefaults(response)

      if (response.data.user) {
        setSelectedUser(response.data.user)
        setSuggestions([])
        setUserTerm(response.data.user.bar_code ?? response.data.user.username ?? response.data.user.name)
      } else {
        setSelectedUser(null)
        setSuggestions(response.data.users ?? [])
        if ((response.data.users ?? []).length === 0) {
          setError(t('borrows.modal.userNotFound'))
        }
      }
    } catch (lookupError) {
      if (lookupError instanceof ApiError) {
        setError(lookupError.messageText ?? t('errors.unexpected'))
      }
    } finally {
      setLookupBusy(false)
    }
  }

  const lookupCopy = async (barcode: string) => {
    const value = normalizeBarcode(barcode)
    if (value === '') return

    setLookupBusy(true)
    setError(null)

    try {
      const response = await api.get<BorrowLookupResponse>(apiPaths.borrowLookup({ barcode: value }))
      applyLookupDefaults(response)

      if (response.data.copy) {
        setSelectedCopy(response.data.copy)
        setCopyTerm(response.data.copy.barcode ?? value)
      } else {
        setSelectedCopy(null)
        setError(t('borrows.modal.copyNotFound'))
      }
    } catch (lookupError) {
      if (lookupError instanceof ApiError) {
        setError(lookupError.messageText ?? t('errors.unexpected'))
      }
    } finally {
      setLookupBusy(false)
    }
  }

  const submit = async (confirmExchange = false) => {
    if (!selectedUser || !selectedCopy || reservedForOther) return

    setSubmitting(true)
    setError(null)

    try {
      const borrow = reservationMode
        ? await api.post<{ data: Borrow }>(apiPaths.reservationIssue(reservation!.id), {
            valid_till: validTill || null,
          })
        : await api.post<{ data: Borrow }>(apiPaths.borrows, {
            user_id: selectedUser.id,
            book_copy_id: selectedCopy.id,
            valid_till: validTill || null,
            confirm_reservation_exchange: confirmExchange,
          })

      setConflict(null)
      toast.success(t('borrows.issued'))
      onSuccess(borrow.data)
      onClose()
    } catch (submitError) {
      if (submitError instanceof ApiError) {
        if (submitError.status === 409) {
          const payload = submitError.data as { reservation_conflict?: ReservationConflict } | undefined

          if (payload?.reservation_conflict) {
            setConflict(payload.reservation_conflict)
            return
          }
        }

        const validationErrors = submitError.validationErrors
        if (validationErrors) {
          setError(Object.values(validationErrors)[0]?.[0] ?? t('errors.unexpected'))
        } else {
          setError(submitError.messageText ?? t('errors.unexpected'))
        }
      }
    } finally {
      setSubmitting(false)
    }
  }

  const canSubmit = Boolean(
    selectedUser
      && selectedCopy
      && selectedCopy.issuable
      && !reservedForOther
      && !submitting
      && activeLibrary,
  )

  return (
    <>
      <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
        <DialogContent className="sm:max-w-xl">
          <DialogHeader>
            <DialogTitle className="font-brand-heading text-xl">{t('borrows.modal.title')}</DialogTitle>
            <DialogDescription>{t('borrows.modal.subtitle')}</DialogDescription>
          </DialogHeader>

          {!activeLibrary ? (
            <p className="text-sm text-destructive">{t('borrows.activeLibraryRequired')}</p>
          ) : null}

          {reservationMode ? (
            <div className="rounded-md border border-brand-accent/50 bg-brand-accent/10 p-3 text-sm">
              <p className="font-medium">{t('borrows.modal.reservationModeTitle')}</p>
              <p className="text-muted-foreground">
                {t('borrows.modal.reservationModeText', {
                  barcode: reservation?.barcode ?? '—',
                  date: reservation?.valid_till_formatted ?? '—',
                })}
              </p>
            </div>
          ) : null}

          <div className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="borrow-user">{t('borrows.modal.userLabel')}</Label>
              {selectedUser ? (
                <div className="flex items-center justify-between gap-3 rounded-md border p-3">
                  <div className="min-w-0">
                    <p className="truncate font-medium">{selectedUser.name}</p>
                    <p className="truncate text-xs text-muted-foreground">
                      {selectedUser.bar_code ?? selectedUser.username ?? selectedUser.email}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                      {t('borrows.modal.userCounts', {
                        borrows: selectedUser.active_borrows,
                        reservations: selectedUser.active_reservations,
                      })}
                    </p>
                  </div>
                  {!reservationMode ? (
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      onClick={() => {
                        setSelectedUser(null)
                        setUserTerm('')
                        setSuggestions([])
                      }}
                    >
                      {t('common.change')}
                    </Button>
                  ) : null}
                </div>
              ) : (
                <>
                  <div className="flex gap-2">
                    <Input
                      id="borrow-user"
                      value={userTerm}
                      onChange={(event) => setUserTerm(event.target.value)}
                      onBlur={() => void lookupUser(userTerm)}
                      onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                          event.preventDefault()
                          void lookupUser(userTerm)
                        }
                      }}
                      placeholder={t('borrows.modal.userPlaceholder')}
                      autoFocus={!user}
                    />
                    <Button
                      type="button"
                      variant="outline"
                      size="icon"
                      disabled={lookupBusy}
                      onClick={() => void lookupUser(userTerm)}
                      aria-label={t('common.search')}
                    >
                      <UserRound />
                    </Button>
                  </div>
                  {suggestions.length > 0 ? (
                    <div className="max-h-40 space-y-1 overflow-y-auto rounded-md border p-1">
                      {suggestions.map((candidate) => (
                        <button
                          key={candidate.id}
                          type="button"
                          className="flex w-full items-center justify-between gap-3 rounded-sm px-2 py-1.5 text-left text-sm hover:bg-muted"
                          onClick={() => {
                            setSelectedUser(candidate)
                            setSuggestions([])
                          }}
                        >
                          <span className="truncate">{candidate.name}</span>
                          <span className="shrink-0 font-mono text-xs text-muted-foreground">
                            {candidate.bar_code ?? candidate.username ?? '—'}
                          </span>
                        </button>
                      ))}
                    </div>
                  ) : null}
                </>
              )}
            </div>

            <div className="space-y-2">
              <Label htmlFor="borrow-copy">{t('borrows.modal.copyLabel')}</Label>
              {selectedCopy ? (
                <div className="rounded-md border p-3">
                  <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                      <p className="truncate font-medium">{selectedCopy.book_name ?? '—'}</p>
                      <p className="truncate text-xs text-muted-foreground">
                        {selectedCopy.book_authors ?? '—'}
                      </p>
                      <p className="mt-1 font-mono text-xs">{selectedCopy.barcode ?? '—'}</p>
                    </div>
                    <Badge variant={selectedCopy.issuable ? 'secondary' : 'destructive'}>
                      {t(`borrows.copyStatus.${selectedCopy.status}`, { defaultValue: selectedCopy.status })}
                    </Badge>
                  </div>

                  {selectedCopy.block_reason ? (
                    <p className="mt-2 flex items-start gap-1.5 text-xs text-destructive">
                      <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
                      {selectedCopy.block_reason}
                    </p>
                  ) : null}

                  {selectedCopy.active_reservation ? (
                    <p className={`mt-2 text-xs ${reservedForOther ? 'text-destructive' : 'text-muted-foreground'}`}>
                      {t('borrows.modal.reservedFor', {
                        name: selectedCopy.active_reservation.user_name ?? '—',
                        date: selectedCopy.active_reservation.valid_till_formatted ?? '—',
                      })}
                    </p>
                  ) : null}
                </div>
              ) : (
                <div className="flex gap-2">
                  <Input
                    id="borrow-copy"
                    value={copyTerm}
                    onChange={(event) => setCopyTerm(event.target.value)}
                    onBlur={() => void lookupCopy(copyTerm)}
                    onKeyDown={(event) => {
                      if (event.key === 'Enter') {
                        event.preventDefault()
                        void lookupCopy(copyTerm)
                      }
                    }}
                    placeholder={t('borrows.modal.copyPlaceholder')}
                    className="font-mono"
                    autoFocus={Boolean(user) && !copy}
                  />
                  <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    disabled={lookupBusy}
                    onClick={() => void lookupCopy(copyTerm)}
                    aria-label={t('common.search')}
                  >
                    <ScanBarcode />
                  </Button>
                </div>
              )}
            </div>

            <div className="space-y-2">
              <Label htmlFor="borrow-valid-till">{t('borrows.modal.validTill')}</Label>
              <DateInput
                id="borrow-valid-till"
                value={validTill}
                onChange={setValidTill}
                className="sm:max-w-xs"
              />
              <p className="text-xs text-muted-foreground">
                {t('borrows.modal.validTillHint', { days: activeLibrary?.lend_days ?? 30 })}
              </p>
            </div>

            {error ? <p className="text-sm text-destructive">{error}</p> : null}
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose} disabled={submitting}>
              {t('common.cancel')}
            </Button>
            <Button
              type="button"
              variant="brand"
              disabled={!canSubmit}
              onClick={() => void submit(false)}
            >
              <BookOpen />
              {t('borrows.modal.submit')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={conflict !== null} onOpenChange={(value) => !value && setConflict(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('borrows.conflict.title')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('borrows.conflict.text', {
                reserved: conflict?.reserved_barcode ?? '—',
                scanned: conflict?.scanned_barcode ?? '—',
                date: conflict?.reserved_valid_till_formatted ?? '—',
              })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <div className="flex items-center gap-2 rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-sm">
            <CalendarClock className="size-4 shrink-0" />
            {t('borrows.conflict.hint')}
          </div>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={submitting}>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction
              disabled={submitting}
              onClick={() => {
                setConflict(null)
                void submit(true)
              }}
            >
              {t('borrows.conflict.confirm')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}
