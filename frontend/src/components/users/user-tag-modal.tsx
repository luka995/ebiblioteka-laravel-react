import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
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
import type { User } from '@/types'

interface UserTagModalProps {
  open: boolean
  onClose: () => void
  user: User
  onSuccess: (message: string) => void
}

export function UserTagModal({ open, onClose, user, onSuccess }: UserTagModalProps) {
  const { t } = useTranslation()
  const [libraryId, setLibraryId] = useState<number | null>(null)
  const [selectedTags, setSelectedTags] = useState<TagOption[]>([])
  const [busy, setBusy] = useState(false)

  const libraries = user.libraries ?? []

  useEffect(() => {
    if (!open) return
    const firstId = libraries[0]?.id ?? null
    setLibraryId(firstId)
    setSelectedTags(
      firstId
        ? (user.tags ?? [])
            .filter((tag) => tag.library_id === firstId)
            .map((tag) => ({ id: tag.id, name: tag.name, library_id: tag.library_id }))
        : [],
    )
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, user.id])

  const handleLibraryChange = (value: string) => {
    const id = Number(value)
    setLibraryId(id)
    setSelectedTags(
      (user.tags ?? [])
        .filter((tag) => tag.library_id === id)
        .map((tag) => ({ id: tag.id, name: tag.name, library_id: tag.library_id })),
    )
  }

  const save = async () => {
    if (libraryId === null) return
    setBusy(true)
    try {
      await api.put(apiPaths.userTags(user.id), {
        library_id: libraryId,
        tag_ids: selectedTags.map((tag) => tag.id),
      })
      onSuccess(t('tags.assignSuccess'))
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
    <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
      <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-md">
        <DialogHeader>
          <DialogTitle className="font-brand-heading text-xl">{t('tags.assignTitle')}</DialogTitle>
          <DialogDescription>{user.name}</DialogDescription>
        </DialogHeader>

        {libraries.length === 0 ? (
          <p className="text-sm text-muted-foreground">{t('tags.noLibraries')}</p>
        ) : (
          <div className="space-y-4">
            <div className="space-y-2">
              <Select value={libraryId !== null ? String(libraryId) : undefined} onValueChange={handleLibraryChange}>
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

            {libraryId !== null ? (
              <TagSelect libraryId={libraryId} value={selectedTags} onChange={setSelectedTags} />
            ) : null}
          </div>
        )}

        <div className="flex justify-end gap-2 pt-2">
          <Button variant="outline" disabled={busy} onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button variant="brand" disabled={busy || libraryId === null} onClick={() => void save()}>
            {t('common.save')}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}
