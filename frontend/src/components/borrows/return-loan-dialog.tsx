import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { truncateBookTitle } from '@/lib/text'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import type { Borrow } from '@/types'

interface ReturnLoanDialogProps {
  borrow: Borrow | null
  onClose: () => void
  onSuccess: (borrow: Borrow) => void
}

export function ReturnLoanDialog({ borrow, onClose, onSuccess }: ReturnLoanDialogProps) {
  const { t } = useTranslation()
  const [busy, setBusy] = useState(false)

  const submit = async () => {
    if (!borrow) return

    setBusy(true)

    try {
      const response = await api.post<{ data: Borrow }>(apiPaths.borrowReturn(borrow.id))
      toast.success(t('borrows.returned'), {
        description:
          response.data.overdue_days > 0
            ? t('borrows.returnedOverdue', {
                days: response.data.overdue_days,
                amount: response.data.overdue_amount,
              })
            : undefined,
      })
      onSuccess(response.data)
      onClose()
    } catch (error) {
      if (error instanceof ApiError) {
        toast.error(error.messageText ?? t('errors.unexpected'))
      }
    } finally {
      setBusy(false)
    }
  }

  return (
    <AlertDialog open={borrow !== null} onOpenChange={(value) => !value && onClose()}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{t('borrows.returnTitle')}</AlertDialogTitle>
          <AlertDialogDescription title={borrow?.book_name ?? undefined}>
            {t('borrows.returnText', {
              title: truncateBookTitle(borrow?.book_name),
              user: borrow?.user_name ?? '—',
              due: borrow?.valid_till_formatted ?? '—',
            })}
          </AlertDialogDescription>
        </AlertDialogHeader>

        {borrow && borrow.overdue_days > 0 ? (
          <p className="rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-sm">
            {t('borrows.overdueNotice', {
              days: borrow.overdue_days,
              amount: borrow.overdue_amount,
            })}
          </p>
        ) : null}

        <AlertDialogFooter>
          <AlertDialogCancel disabled={busy}>{t('common.cancel')}</AlertDialogCancel>
          <AlertDialogAction disabled={busy} onClick={() => void submit()}>
            {t('borrows.returnAction')}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
