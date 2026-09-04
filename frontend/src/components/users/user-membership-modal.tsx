import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { useAuth } from '@/hooks/useAuth'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
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
import type { User } from '@/types'

type Mode = 'toggle' | 'delete'

interface LibraryRow {
  id: number
  name: string
  deactivated: boolean
}

interface UserMembershipModalProps {
  open: boolean
  onClose: () => void
  user: User
  mode: Mode
  onSuccess: (message: string) => void
}

export function UserMembershipModal({ open, onClose, user, mode, onSuccess }: UserMembershipModalProps) {
  const { t } = useTranslation()
  const { activeLibrary, user: actor } = useAuth()
  const [selected, setSelected] = useState<number[]>([])
  const [busy, setBusy] = useState(false)
  const [forceConfirmOpen, setForceConfirmOpen] = useState(false)

  const active = user.libraries ?? []
  const deactivated = user.deactivated_libraries ?? []

  const rows: LibraryRow[] = [
    ...active.map((library) => ({ id: library.id, name: library.name, deactivated: false })),
    ...deactivated.map((library) => ({ id: library.id, name: library.name, deactivated: true })),
  ]

  const scoped = activeLibrary !== null
  const isSuperAdmin = actor?.role === 'superadmin'

  const reset = () => setSelected([])

  const toggleSelected = (id: number) => {
    setSelected((current) => (current.includes(id) ? current.filter((item) => item !== id) : [...current, id]))
  }

  const targetId = scoped ? activeLibrary!.id : null
  const targetIsActive = scoped && active.some((library) => library.id === targetId)
  const targetIsDeactivated = scoped && deactivated.some((library) => library.id === targetId)

  const run = async (action: () => Promise<unknown>, successMessage: string) => {
    setBusy(true)
    try {
      await action()
      onSuccess(successMessage)
      reset()
      onClose()
    } catch (error) {
      if (error instanceof ApiError) {
        toast.error(error.messageText ?? t('errors.unexpected'))
      }
    } finally {
      setBusy(false)
    }
  }

  const deactivate = (ids: number[]) => run(() => api.post(apiPaths.userLibrariesDeactivate(user.id), { library_ids: ids }), t('membership.deactivatedMsg'))
  const activate = (ids: number[]) => run(() => api.post(apiPaths.userLibrariesActivate(user.id), { library_ids: ids }), t('membership.activatedMsg'))
  const remove = (ids: number[]) => run(() => api.delete(apiPaths.userLibraries(user.id), { library_ids: ids }), t('membership.removedMsg'))
  const softDelete = () => run(() => api.delete(apiPaths.user(user.id)), t('membership.softDeletedMsg'))
  const forceDelete = () => {
    setForceConfirmOpen(false)
    return run(() => api.delete(apiPaths.userForce(user.id)), t('membership.forceDeletedMsg'))
  }

  const title = mode === 'toggle' ? t('membership.toggleTitle') : t('membership.deleteTitle')

  const scopedName = activeLibrary?.name ?? ''

  return (
    <>
      <Dialog
        open={open}
        onOpenChange={(value) => {
          if (!value) {
            reset()
            onClose()
          }
        }}
      >
        <DialogContent className="max-h-[95vh] sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle className="font-brand-heading text-xl">{title}</DialogTitle>
            <DialogDescription>{user.name}</DialogDescription>
          </DialogHeader>

          {scoped ? (
            <p className="text-sm text-muted-foreground">
              {mode === 'toggle'
                ? targetIsDeactivated
                  ? t('membership.scopedActivate', { name: scopedName })
                  : t('membership.scopedDeactivate', { name: scopedName })
                : t('membership.scopedRemove', { name: scopedName })}
            </p>
          ) : (
            <div className="space-y-1">
              {rows.length === 0 ? (
                <p className="text-sm text-muted-foreground">{t('membership.noLibraries')}</p>
              ) : (
                rows.map((library) => (
                  <label
                    key={library.id}
                    className="flex cursor-pointer items-center gap-3 rounded-md px-2 py-1.5 hover:bg-muted"
                  >
                    <Checkbox
                      checked={selected.includes(library.id)}
                      onCheckedChange={() => toggleSelected(library.id)}
                    />
                    <span className="min-w-0 flex-1 truncate text-sm">{library.name}</span>
                    <Badge variant={library.deactivated ? 'secondary' : 'default'} className="border-transparent">
                      {library.deactivated ? t('membership.deactivated') : t('membership.active')}
                    </Badge>
                  </label>
                ))
              )}
            </div>
          )}

          <DialogFooter>
            {mode === 'toggle' ? (
              scoped ? (
                targetIsDeactivated ? (
                  <Button variant="brand" disabled={busy} onClick={() => void activate([targetId!])}>
                    {t('membership.activate')}
                  </Button>
                ) : (
                  <Button variant="outline" disabled={busy || !targetIsActive} onClick={() => void deactivate([targetId!])}>
                    {t('membership.deactivate')}
                  </Button>
                )
              ) : (
                <>
                  <Button variant="outline" disabled={busy || selected.length === 0} onClick={() => void deactivate(selected)}>
                    {t('membership.deactivateSelected')}
                  </Button>
                  <Button variant="brand" disabled={busy || selected.length === 0} onClick={() => void activate(selected)}>
                    {t('membership.activateSelected')}
                  </Button>
                </>
              )
            ) : (
              <>
                {scoped ? (
                  <Button variant="outline" disabled={busy} onClick={() => void remove([targetId!])}>
                    {t('membership.remove')}
                  </Button>
                ) : (
                  <Button variant="outline" disabled={busy || selected.length === 0} onClick={() => void remove(selected)}>
                    {t('membership.removeSelected')}
                  </Button>
                )}
                {isSuperAdmin ? (
                  <Button variant="secondary" disabled={busy} onClick={() => void softDelete()}>
                    {t('membership.softDeleteAccount')}
                  </Button>
                ) : null}
                {isSuperAdmin ? (
                  <Button variant="destructive" disabled={busy} onClick={() => setForceConfirmOpen(true)}>
                    {t('membership.forceDeleteAccount')}
                  </Button>
                ) : null}
              </>
            )}
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={forceConfirmOpen} onOpenChange={setForceConfirmOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('membership.forceDeleteConfirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('membership.forceDeleteConfirmText', { name: user.name })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={busy}>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction variant="destructive" disabled={busy} onClick={() => void forceDelete()}>
              {t('membership.forceDeleteAccount')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}
