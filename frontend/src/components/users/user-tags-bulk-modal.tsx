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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
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
  const { activeLibrary, libraries } = useAuth()
  const [libraryId, setLibraryId] = useState<number | null>(null)
  const [selectedTags, setSelectedTags] = useState<TagOption[]>([])
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    if (!open) return
    setLibraryId(activeLibrary?.id ?? null)
    setSelectedTags([])
  }, [open, activeLibrary?.id])

  const save = async () => {
    if (libraryId === null || selectedTags.length === 0) return
    setBusy(true)
    try {
      const endpoint = mode === 'assign' ? apiPaths.usersTagsAssign : apiPaths.usersTagsRemove
      await api.post(endpoint, {
        user_ids: userIds,
        library_id: libraryId,
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
            <p className="text-sm text-muted-foreground">
              {t('tags.libraryLabel')}: <span className="font-medium">{activeLibrary.name}</span>
            </p>
          ) : (
            <div className="space-y-2">
              <p className="text-sm font-medium">{t('tags.libraryLabel')}</p>
              <Select
                value={libraryId !== null ? String(libraryId) : undefined}
                onValueChange={(value) => setLibraryId(Number(value))}
              >
                <SelectTrigger className="w-full">
                  <SelectValue placeholder={t('tags.libraryPlaceholder')} />
                </SelectTrigger>
                <SelectContent>
                  {libraries.map((library) => (
                    <SelectItem key={library.id} value={String(library.id)}>
                      {library.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}

          {libraryId !== null ? (
            <TagSelect libraryId={libraryId} value={selectedTags} onChange={setSelectedTags} />
          ) : null}
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
