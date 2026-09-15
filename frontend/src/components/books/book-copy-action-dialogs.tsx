import { useEffect, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { DateInput } from '@/components/ui/date-input'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import type { BookCopy } from '@/types'

interface DialogProps {
  open: boolean
  copy: BookCopy | null
  bookId?: number
  onClose: () => void
  onSuccess: () => void
}

function invalidate(queryClient: ReturnType<typeof useQueryClient>, bookId?: number) {
  void queryClient.invalidateQueries({ queryKey: ['book-copies'] })
  if (bookId) void queryClient.invalidateQueries({ queryKey: ['book', bookId] })
}

export function BookCopyWriteOffDialog({ open, copy, bookId, onClose, onSuccess }: DialogProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [reason, setReason] = useState<'out_of_date' | 'unusable'>('out_of_date')
  const [occurredAt, setOccurredAt] = useState('')
  const [notice, setNotice] = useState('')
  const [isSaving, setIsSaving] = useState(false)

  useEffect(() => {
    if (open) {
      setReason('out_of_date')
      setOccurredAt(new Date().toISOString().slice(0, 10))
      setNotice('')
    }
  }, [open])

  const submit = async () => {
    if (!copy) return
    setIsSaving(true)
    try {
      await api.post(apiPaths.bookCopyWriteOff(copy.id), {
        reason,
        occurred_at: occurredAt || null,
        notice: notice.trim() || null,
      })
      invalidate(queryClient, bookId)
      toast.success(t('books.copies.writtenOff'))
      onSuccess()
      onClose()
    } catch (error) {
      if (error instanceof ApiError) toast.error(error.messageText ?? t('errors.unexpected'))
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle className="font-brand-heading text-xl">{t('books.copies.writeOffTitle')}</DialogTitle>
          <DialogDescription>{copy?.book_name ?? ''}</DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label>{t('books.copies.reason')}</Label>
            <select
              className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
              value={reason}
              onChange={(event) => setReason(event.target.value as 'out_of_date' | 'unusable')}
            >
              <option value="out_of_date">{t('books.writeOffReasons.out_of_date')}</option>
              <option value="unusable">{t('books.writeOffReasons.unusable')}</option>
            </select>
          </div>
          <div className="space-y-1.5">
            <Label>{t('books.copies.occurredAt')}</Label>
            <DateInput value={occurredAt} onChange={(value) => setOccurredAt(value)} />
          </div>
          <div className="space-y-1.5">
            <Label>{t('books.fields.notice')}</Label>
            <Textarea rows={2} value={notice} onChange={(event) => setNotice(event.target.value)} />
          </div>
        </div>

        <div className="flex justify-end gap-2 pt-2">
          <Button type="button" variant="outline" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="button" variant="destructive" onClick={() => void submit()} disabled={isSaving}>
            {t('books.copies.writeOffConfirm')}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}

export function BookCopyRecErrorDialog({ open, copy, bookId, onClose, onSuccess }: DialogProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [recError, setRecError] = useState(false)
  const [notice, setNotice] = useState('')
  const [isSaving, setIsSaving] = useState(false)

  useEffect(() => {
    if (open) {
      setRecError(copy?.rec_error ?? false)
      setNotice(copy?.rec_error_notice ?? '')
    }
  }, [open, copy])

  const submit = async () => {
    if (!copy) return
    setIsSaving(true)
    try {
      await api.put(apiPaths.bookCopyRecError(copy.id), {
        rec_error: recError,
        rec_error_notice: recError ? notice.trim() || null : null,
      })
      invalidate(queryClient, bookId)
      toast.success(t('books.copies.recErrorSaved'))
      onSuccess()
      onClose()
    } catch (error) {
      if (error instanceof ApiError) toast.error(error.messageText ?? t('errors.unexpected'))
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle className="font-brand-heading text-xl">{t('books.copies.recErrorTitle')}</DialogTitle>
          <DialogDescription>{copy?.book_name ?? ''}</DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <label className="flex items-center gap-2 text-sm font-medium">
            <Checkbox checked={recError} onCheckedChange={(value) => setRecError(value === true)} />
            {t('books.copies.recErrorFlag')}
          </label>
          {recError ? (
            <div className="space-y-1.5">
              <Label>{t('books.copies.recErrorNotice')}</Label>
              <Textarea rows={2} value={notice} onChange={(event) => setNotice(event.target.value)} />
            </div>
          ) : null}
        </div>

        <div className="flex justify-end gap-2 pt-2">
          <Button type="button" variant="outline" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="button" variant="brand" onClick={() => void submit()} disabled={isSaving}>
            {t('common.save')}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}
