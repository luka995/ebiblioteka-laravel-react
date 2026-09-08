import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { useAuth } from '@/hooks/useAuth'
import { Button } from '@/components/ui/button'
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

export type BulkMembershipMode =
  | 'deactivate'
  | 'activate'
  | 'remove'
  | 'bulkDeactivateAccounts'
  | 'bulkForceDeleteAccounts'

interface UserMembershipsBulkModalProps {
  open: boolean
  onClose: () => void
  mode: BulkMembershipMode
  userIds: number[]
  onSuccess: (message: string) => void
}

export function UserMembershipsBulkModal({
  open,
  onClose,
  mode,
  userIds,
  onSuccess,
}: UserMembershipsBulkModalProps) {
  const { t } = useTranslation()
  const { activeLibrary } = useAuth()
  const [busy, setBusy] = useState(false)

  const isMembershipAction =
    mode === 'deactivate' || mode === 'activate' || mode === 'remove'
  const canSubmit = !isMembershipAction || activeLibrary !== null

  const title =
    mode === 'deactivate'
      ? t('membership.bulkDeactivateTitle')
      : mode === 'activate'
        ? t('membership.bulkActivateTitle')
        : mode === 'remove'
          ? t('membership.bulkRemoveTitle')
          : mode === 'bulkDeactivateAccounts'
            ? t('membership.bulkSoftDeleteAccountsTitle')
            : t('membership.bulkForceDeleteAccountsTitle')

  const actionLabel =
    mode === 'deactivate'
      ? t('membership.deactivateSelected')
      : mode === 'activate'
        ? t('membership.activateSelected')
        : mode === 'remove'
          ? t('membership.removeSelected')
          : mode === 'bulkDeactivateAccounts'
            ? t('membership.bulkSoftDeleteAccounts')
            : t('membership.bulkForceDeleteAccounts')

  const info =
    mode === 'remove'
      ? t('membership.removeInfo')
      : mode === 'deactivate'
        ? t('membership.toggleInfo')
        : mode === 'activate'
          ? t('membership.bulkActivateInfo')
          : mode === 'bulkDeactivateAccounts'
            ? t('membership.softDeleteInfo')
            : t('membership.forceDeleteInfo')

  const destructive = mode === 'remove' || mode === 'bulkForceDeleteAccounts'

  const run = async () => {
    setBusy(true)
    try {
      const payload = { user_ids: userIds }

      switch (mode) {
        case 'deactivate':
          await api.post(apiPaths.usersMembershipsDeactivate, payload)
          break
        case 'activate':
          await api.post(apiPaths.usersMembershipsActivate, payload)
          break
        case 'remove':
          await api.delete(apiPaths.usersMembershipsRemove, payload)
          break
        case 'bulkDeactivateAccounts':
          await api.post(apiPaths.usersBulkDeactivate, payload)
          break
        case 'bulkForceDeleteAccounts':
          await api.delete(apiPaths.usersBulkForce, payload)
          break
      }

      const message =
        mode === 'deactivate'
          ? t('membership.deactivatedMsg')
          : mode === 'activate'
            ? t('membership.activatedMsg')
            : mode === 'remove'
              ? t('membership.removedMsg')
              : mode === 'bulkDeactivateAccounts'
                ? t('membership.softDeletedMsg')
                : t('membership.forceDeletedMsg')

      onSuccess(message)
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
          <AlertDialogTitle>{title}</AlertDialogTitle>
          <AlertDialogDescription className="space-y-1.5">
            <p>{t('membership.bulkInfoCount', { count: userIds.length })}</p>
            {isMembershipAction ? (
              activeLibrary ? (
                <p>{t('membership.bulkActiveLibrary', { library: activeLibrary.name })}</p>
              ) : (
                <p>{t('membership.bulkNeedActiveLibrary')}</p>
              )
            ) : null}
            <p className="text-sm">{info}</p>
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel disabled={busy}>{t('common.cancel')}</AlertDialogCancel>
          <AlertDialogAction
            variant={destructive ? 'destructive' : 'default'}
            disabled={busy || !canSubmit}
            onClick={() => void run()}
          >
            {actionLabel}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
