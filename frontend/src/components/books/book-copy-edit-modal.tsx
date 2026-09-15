import { useEffect, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { DateInput } from '@/components/ui/date-input'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import type { BookCopy } from '@/types'

interface BookCopyEditModalProps {
  open: boolean
  copy: BookCopy | null
  bookId: number
  manual: boolean
  onClose: () => void
  onSuccess: () => void
}

export function BookCopyEditModal({ open, copy, bookId, manual, onClose, onSuccess }: BookCopyEditModalProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [form, setForm] = useState<Record<string, string>>({})
  const [isSaving, setIsSaving] = useState(false)

  useEffect(() => {
    if (!open || !copy) return
    setForm({
      order_number: copy.order_number ?? '',
      seq_number: copy.seq_number != null ? String(copy.seq_number) : '',
      isbn: copy.isbn ?? '',
      publisher: copy.publisher ?? '',
      publish_place: copy.publish_place ?? '',
      publish_year: copy.publish_year ?? '',
      issue_number: copy.issue_number ?? '',
      num_of_pages: copy.num_of_pages != null ? String(copy.num_of_pages) : '',
      dimension: copy.dimension ?? '',
      part: copy.part ?? '',
      udk: copy.udk ?? '',
      binding: copy.binding ?? '',
      origin: copy.origin ?? '',
      book_number: copy.book_number ?? '',
      place_on_shelf: copy.place_on_shelf ?? '',
      price: String(copy.price ?? '0'),
      date_add: copy.date_add ?? '',
      notice: copy.notice ?? '',
    })
  }, [open, copy])

  const update = (key: string, value: string) => setForm((current) => ({ ...current, [key]: value }))

  const submit = async () => {
    if (!copy) return
    setIsSaving(true)
    try {
      const payload: Record<string, unknown> = {
        seq_number: form.seq_number === '' ? null : Number(form.seq_number),
        isbn: form.isbn.trim() || null,
        publisher: form.publisher.trim() || null,
        publish_place: form.publish_place.trim() || null,
        publish_year: form.publish_year.trim() || null,
        issue_number: form.issue_number.trim() || null,
        num_of_pages: form.num_of_pages === '' ? null : Number(form.num_of_pages),
        dimension: form.dimension.trim() || null,
        part: form.part.trim() || null,
        udk: form.udk.trim() || null,
        binding: form.binding || null,
        origin: form.origin || null,
        book_number: form.book_number.trim() || null,
        place_on_shelf: form.place_on_shelf.trim() || null,
        price: form.price === '' ? 0 : Number(form.price),
        date_add: form.date_add || null,
        notice: form.notice.trim() || null,
      }

      if (manual) payload.order_number = form.order_number.trim()

      await api.put(apiPaths.bookCopy(copy.id), payload)
      await queryClient.invalidateQueries({ queryKey: ['book', bookId] })
      await queryClient.invalidateQueries({ queryKey: ['book-copies'] })
      toast.success(t('books.copies.updated'))
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
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle className="font-brand-heading text-xl">{t('books.copies.editTitle')}</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          {manual ? (
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label>{t('books.copies.orderNumber')}</Label>
                <Input value={form.order_number ?? ''} onChange={(event) => update('order_number', event.target.value)} />
              </div>
              <div className="space-y-1.5">
                <Label>{t('books.fields.seqNumber')}</Label>
                <Input value={form.seq_number ?? ''} onChange={(event) => update('seq_number', event.target.value)} />
              </div>
            </div>
          ) : (
            <div className="space-y-1.5">
              <Label>{t('books.fields.seqNumber')}</Label>
              <Input value={form.seq_number ?? ''} onChange={(event) => update('seq_number', event.target.value)} />
            </div>
          )}

          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-1.5">
              <Label>{t('books.fields.isbn')}</Label>
              <Input value={form.isbn ?? ''} onChange={(event) => update('isbn', event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>{t('books.fields.publisher')}</Label>
              <Input value={form.publisher ?? ''} onChange={(event) => update('publisher', event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>{t('books.fields.publishPlace')}</Label>
              <Input value={form.publish_place ?? ''} onChange={(event) => update('publish_place', event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>{t('books.fields.publishYear')}</Label>
              <Input value={form.publish_year ?? ''} onChange={(event) => update('publish_year', event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>{t('books.fields.pages')}</Label>
              <Input
                type="number"
                value={form.num_of_pages ?? ''}
                onChange={(event) => update('num_of_pages', event.target.value)}
              />
            </div>
            <div className="space-y-1.5">
              <Label>{t('books.fields.dimension')}</Label>
              <Input value={form.dimension ?? ''} onChange={(event) => update('dimension', event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>{t('books.fields.udk')}</Label>
              <Input value={form.udk ?? ''} onChange={(event) => update('udk', event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>{t('books.fields.part')}</Label>
              <Input value={form.part ?? ''} onChange={(event) => update('part', event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>{t('books.fields.placeOnShelf')}</Label>
              <Input
                value={form.place_on_shelf ?? ''}
                onChange={(event) => update('place_on_shelf', event.target.value)}
              />
            </div>
            <div className="space-y-1.5">
              <Label>{t('books.fields.bookNumber')}</Label>
              <Input value={form.book_number ?? ''} onChange={(event) => update('book_number', event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>{t('books.fields.price')}</Label>
              <Input
                type="number"
                step="0.01"
                value={form.price ?? '0'}
                onChange={(event) => update('price', event.target.value)}
              />
            </div>
            <div className="space-y-1.5">
              <Label>{t('books.fields.dateAdd')}</Label>
              <DateInput value={form.date_add ?? ''} onChange={(value) => update('date_add', value)} />
            </div>
            <div className="space-y-1.5">
              <Label>{t('books.fields.issueNumber')}</Label>
              <Input value={form.issue_number ?? ''} onChange={(event) => update('issue_number', event.target.value)} />
            </div>
          </div>

          <div className="space-y-1.5">
            <Label>{t('books.fields.notice')}</Label>
            <Textarea rows={2} value={form.notice ?? ''} onChange={(event) => update('notice', event.target.value)} />
          </div>
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
