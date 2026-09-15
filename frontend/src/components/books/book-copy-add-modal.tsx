import { useEffect, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Search } from 'lucide-react'
import { api, apiPaths, ApiError } from '@/lib/api'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { BookCopyMetadataFields } from '@/components/books/book-copy-metadata-fields'
import {
  copyMetadataPayload,
  copyQuantityPayload,
  EMPTY_COPY,
  type CopyFormValues,
} from '@/components/books/copy-form'
import { InventoryDiscrepancyDialog } from '@/components/books/inventory-discrepancy-dialog'
import type { Book, BookCopy, InventoryDiscrepancy, IsbnLookupResult } from '@/types'

interface BookCopyAddModalProps {
  open: boolean
  book: Book
  onClose: () => void
  onSuccess: () => void
  onUseExistingTitle?: (book: Book) => void
}

export function BookCopyAddModal({ open, book, onClose, onSuccess, onUseExistingTitle }: BookCopyAddModalProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const auto = book.inv_number_auto !== false
  const [form, setForm] = useState<CopyFormValues>({ ...EMPTY_COPY })
  const [discrepancy, setDiscrepancy] = useState<InventoryDiscrepancy | null>(null)
  const [isSaving, setIsSaving] = useState(false)
  const [isLookingUp, setIsLookingUp] = useState(false)
  const [lookup, setLookup] = useState<IsbnLookupResult | null>(null)

  useEffect(() => {
    if (open) {
      setForm({ ...EMPTY_COPY })
      setDiscrepancy(null)
      setLookup(null)
    }
  }, [open])

  const otherTitle = lookup
    ? lookup.book && lookup.book.id !== book.id
      ? lookup.book
      : (lookup.matches.find((match) => match.id !== book.id) ?? null)
    : null

  const update = (key: keyof CopyFormValues, value: string) =>
    setForm((current) => ({ ...current, [key]: value }))

  const runLookup = async () => {
    if (!form.isbn.trim()) return
    setIsLookingUp(true)
    try {
      const params = new URLSearchParams({ isbn: form.isbn.trim() })
      if (book.library_id) params.set('library_id', String(book.library_id))
      const result = await api.get<IsbnLookupResult>(`${apiPaths.bookIsbnLookup}?${params.toString()}`)
      setLookup(result)

      if (result.metadata) {
        const meta = result.metadata
        setForm((current) => ({
          ...current,
          isbn: meta.isbn ?? current.isbn,
          publisher: meta.publisher ?? current.publisher,
          publishPlace: meta.publish_place ?? current.publishPlace,
          publishYear: meta.publish_year ?? current.publishYear,
          numOfPages: meta.pages ? String(meta.pages) : current.numOfPages,
          dimension: meta.dimensions ?? current.dimension,
          udk: meta.udk ?? current.udk,
        }))
        return
      }

      if (result.book) {
        const first = result.existing_copies[0]
        setForm((current) => ({
          ...current,
          isbn: first?.isbn ?? current.isbn,
          publisher: first?.publisher ?? current.publisher,
          publishPlace: first?.publish_place ?? current.publishPlace,
          publishYear: first?.publish_year ?? current.publishYear,
          numOfPages: first?.num_of_pages != null ? String(first.num_of_pages) : current.numOfPages,
          dimension: first?.dimension ?? current.dimension,
          udk: first?.udk ?? current.udk,
        }))
        return
      }

      toast.info(t('books.isbn.noMetadata'))
    } catch (error) {
      if (error instanceof ApiError) toast.error(error.messageText ?? t('errors.unexpected'))
    } finally {
      setIsLookingUp(false)
    }
  }

  const submit = async () => {
    setIsSaving(true)
    try {
      const payload: Record<string, unknown> = {
        ...copyMetadataPayload(form),
        ...copyQuantityPayload(form, auto),
      }

      const response = await api.post<{ data: BookCopy[] }>(apiPaths.bookCopies(book.id), payload)
      await queryClient.invalidateQueries({ queryKey: ['book', book.id] })
      await queryClient.invalidateQueries({ queryKey: ['books'] })
      await queryClient.invalidateQueries({ queryKey: ['book-copies'] })
      toast.success(t('books.copies.created', { count: response.data?.length ?? 1 }))
      onSuccess()
      onClose()
    } catch (error) {
      if (error instanceof ApiError) {
        const payload = error.data as { inventory_discrepancy?: InventoryDiscrepancy } | undefined
        if (error.status === 409 && payload?.inventory_discrepancy) {
          setDiscrepancy(payload.inventory_discrepancy)
          return
        }
        toast.error(error.messageText ?? t('errors.unexpected'))
      }
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <>
      <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle className="font-brand-heading text-xl">{t('books.copies.addTitle')}</DialogTitle>
            <DialogDescription>{book.name}</DialogDescription>
          </DialogHeader>

          <div className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
              {auto ? (
                <div className="space-y-1.5">
                  <Label htmlFor="copy-count">{t('books.copies.count')}</Label>
                  <Input
                    id="copy-count"
                    type="number"
                    min={1}
                    max={100}
                    value={form.copies}
                    onChange={(event) => update('copies', event.target.value)}
                  />
                </div>
              ) : (
                <div className="space-y-1.5">
                  <Label htmlFor="copy-order">{t('books.copies.orderNumber')}</Label>
                  <Input
                    id="copy-order"
                    value={form.orderNumber}
                    onChange={(event) => update('orderNumber', event.target.value)}
                    placeholder={t('books.copies.orderNumberPlaceholder')}
                  />
                </div>
              )}
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="copy-isbn">{t('books.fields.isbn')}</Label>
              <div className="flex gap-2">
                <Input
                  id="copy-isbn"
                  value={form.isbn}
                  onChange={(event) => update('isbn', event.target.value)}
                  placeholder={t('books.isbn.placeholder')}
                />
                <Button type="button" variant="outline" onClick={() => void runLookup()} disabled={isLookingUp}>
                  <Search />
                </Button>
              </div>

              {lookup?.source ? (
                <p className="text-xs text-muted-foreground">
                  {t('books.isbn.source')}:{' '}
                  <Badge variant="secondary">
                    {t(`books.isbn.sources.${lookup.source}`, { defaultValue: lookup.source })}
                  </Badge>
                </p>
              ) : null}

              {lookup?.book?.id === book.id && lookup.existing_copies.length ? (
                <div className="rounded-md border bg-background p-2 text-xs">
                  <p className="font-medium">
                    {t('books.isbn.existingCopies', { count: lookup.existing_copies.length })}
                  </p>
                  <p className="mt-0.5 text-muted-foreground">{t('books.isbn.existingCopiesHint')}</p>
                </div>
              ) : null}

              {otherTitle ? (
                <div className="rounded-md border border-brand-accent/40 bg-brand-soft/40 p-3 text-sm">
                  <p className="font-medium">{t('books.duplicate.foundOtherTitle')}</p>
                  <p className="mt-0.5 text-muted-foreground">{otherTitle.name}</p>
                  {onUseExistingTitle ? (
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      className="mt-2"
                      onClick={() => onUseExistingTitle(otherTitle)}
                    >
                      {t('books.duplicate.openTitle')}
                    </Button>
                  ) : null}
                </div>
              ) : null}
            </div>

            <BookCopyMetadataFields value={form} onChange={update} />
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

      <InventoryDiscrepancyDialog
        discrepancy={discrepancy}
        open={Boolean(discrepancy)}
        onClose={() => setDiscrepancy(null)}
      />
    </>
  )
}
