import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { useAuth } from '@/hooks/useAuth'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { TagSelect, type TagOption } from '@/components/tags/tag-select'

interface UserTagsBulkModalProps {
  open: boolean
  onClose: () => void
  mode: 'assign' | 'remove'
  userIds: number[]
  onSuccess: (message: string) => void
}

export function UserTagsBulkModal({ open, onClose, mode, userIds, onSuccess }: UserTagsBulkModalProps) {
  const { t } = useTranslation()
  const { activeLibrary } = useAuth()
  const [selectedTags, setSelectedTags] = useState<TagOption[]>([])
  const [busy, setBusy] = useState(false)

  const libraryId = activeLibrary?.id ?? null

  useEffect(() => {
    if (!open) return
    setSelectedTags([])
  }, [open])

  const save = async () => {
    if (libraryId === null || selectedTags.length === 0) return
    setBusy(true)
    try {
      const endpoint = mode === 'assign' ? apiPaths.usersTagsAssign : apiPaths.usersTagsRemove
      await api.post(endpoint, {
        user_ids: userIds,
        tag_ids: selectedTags.map((tag) => tag.id),
      })
      onSuccess(mode === 'assign' ? t('tags.assignSuccess') : t('tags.removeSuccess'))
      onClose()
    } catch (error) {
      if (error instanceof ApiError) {
        toast.error(error.messageText ?? t('errors.unexpected'))
      }
    } finally {
      setBusy(false)
    }
  }

  const title = mode === 'assign' ? t('tags.bulkAssignTitle') : t('tags.bulkRemoveTitle')

  return (
    <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
      <DialogContent className="max-h-[95vh] sm:max-w-xl">
        <DialogHeader>
          <DialogTitle className="font-brand-heading text-xl">{title}</DialogTitle>
          <DialogDescription>{t('tags.bulkSelected', { count: userIds.length })}</DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {activeLibrary ? (
            <>
              <p className="text-sm text-muted-foreground">
                {t('tags.libraryLabel')}: <span className="font-medium">{activeLibrary.name}</span>
              </p>
              <TagSelect libraryId={activeLibrary.id} value={selectedTags} onChange={setSelectedTags} />
            </>
          ) : (
            <p className="text-sm text-muted-foreground">{t('tags.bulkNeedActiveLibrary')}</p>
          )}
        </div>

        <div className="flex justify-end gap-2 pt-2">
          <Button variant="outline" disabled={busy} onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button
            variant="brand"
            disabled={busy || libraryId === null || selectedTags.length === 0}
            onClick={() => void save()}
          >
            {mode === 'assign' ? t('tags.bulkAssign') : t('tags.bulkRemove')}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}
