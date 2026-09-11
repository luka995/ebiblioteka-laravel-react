import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { useAuth } from '@/hooks/useAuth'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'

interface UserBarcodeBulkModalProps {
  open: boolean
  onClose: () => void
  userIds: number[]
  onSuccess: (message: string) => void
}

export function UserBarcodeBulkModal({ open, onClose, userIds, onSuccess }: UserBarcodeBulkModalProps) {
  const { t } = useTranslation()
  const { user, activeLibrary } = useAuth()
  const [busy, setBusy] = useState(false)

  const isSuperAdmin = user?.role === 'superadmin'
  const needsActiveLibrary = !isSuperAdmin
  const canSubmit = !needsActiveLibrary || activeLibrary !== null

  const run = async () => {
    setBusy(true)
    try {
      await api.post(apiPaths.usersBulkBarcode, { user_ids: userIds })
      onSuccess(t('users.bulkRegenerateBarcodeSuccess'))
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
    <AlertDialog open={open} onOpenChange={(value) => !value && !busy && onClose()}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{t('users.bulkRegenerateBarcodeTitle')}</AlertDialogTitle>
          <AlertDialogDescription className="space-y-1.5">
            <p>{t('membership.bulkInfoCount', { count: userIds.length })}</p>
            {needsActiveLibrary ? (
              activeLibrary ? (
                <p>{t('membership.bulkActiveLibrary', { library: activeLibrary.name })}</p>
              ) : (
                <p>{t('membership.bulkNeedActiveLibrary')}</p>
              )
            ) : null}
            <p className="text-sm">{t('users.bulkRegenerateBarcodeInfo')}</p>
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel disabled={busy}>{t('common.cancel')}</AlertDialogCancel>
          <AlertDialogAction
            disabled={busy || !canSubmit}
            onClick={() => void run()}
          >
            {t('users.bulkRegenerateBarcode')}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
