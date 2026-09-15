import { useEffect, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Search } from 'lucide-react'
import { api, apiPaths, ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { DateInput } from '@/components/ui/date-input'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { InventoryDiscrepancyDialog } from '@/components/books/inventory-discrepancy-dialog'
import type { Book, BookCopy, InventoryDiscrepancy, IsbnLookupResult } from '@/types'

interface BookCopyAddModalProps {
  open: boolean
  book: Book
  onClose: () => void
  onSuccess: () => void
}

const EMPTY = {
  copies: '1',
  orderNumber: '',
  isbn: '',
  publisher: '',
  publishPlace: '',
  publishYear: '',
  issueNumber: '',
  numOfPages: '',
  dimension: '',
  part: '',
  udk: '',
  binding: '',
  origin: '',
  bookNumber: '',
  placeOnShelf: '',
  price: '0',
  dateAdd: '',
  notice: '',
}

export function BookCopyAddModal({ open, book, onClose, onSuccess }: BookCopyAddModalProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const auto = book.inv_number_auto !== false
  const [form, setForm] = useState({ ...EMPTY })
  const [discrepancy, setDiscrepancy] = useState<InventoryDiscrepancy | null>(null)
  const [isSaving, setIsSaving] = useState(false)

  useEffect(() => {
    if (open) {
      setForm({ ...EMPTY })
      setDiscrepancy(null)
    }
  }, [open])

  const update = (key: keyof typeof EMPTY, value: string) => setForm((current) => ({ ...current, [key]: value }))

  const runLookup = async () => {
    if (!form.isbn.trim()) return
    try {
      const params = new URLSearchParams({ isbn: form.isbn.trim() })
      if (book.library_id) params.set('library_id', String(book.library_id))
      const result = await api.get<IsbnLookupResult>(`${apiPaths.bookIsbnLookup}?${params.toString()}`)
      const meta = result.metadata
      if (meta) {
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
      } else {
        toast.info(t('books.isbn.noMetadata'))
      }
    } catch (error) {
      if (error instanceof ApiError) toast.error(error.messageText ?? t('errors.unexpected'))
    }
  }

  const submit = async () => {
    setIsSaving(true)
    try {
      const payload: Record<string, unknown> = {
        isbn: form.isbn.trim() || null,
        publisher: form.publisher.trim() || null,
        publish_place: form.publishPlace.trim() || null,
        publish_year: form.publishYear.trim() || null,
        issue_number: form.issueNumber.trim() || null,
        num_of_pages: form.numOfPages === '' ? null : Number(form.numOfPages),
        dimension: form.dimension.trim() || null,
        part: form.part.trim() || null,
        udk: form.udk.trim() || null,
        binding: form.binding || null,
        origin: form.origin || null,
        book_number: form.bookNumber.trim() || null,
        place_on_shelf: form.placeOnShelf.trim() || null,
        price: form.price === '' ? 0 : Number(form.price),
        date_add: form.dateAdd || null,
        notice: form.notice.trim() || null,
      }

      if (auto) {
        payload.copies = Math.max(1, Number(form.copies) || 1)
      } else {
        payload.order_number = form.orderNumber.trim()
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
                <Button type="button" variant="outline" onClick={() => void runLookup()}>
                  <Search />
                </Button>
              </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label>{t('books.fields.publisher')}</Label>
                <Input value={form.publisher} onChange={(event) => update('publisher', event.target.value)} />
              </div>
              <div className="space-y-1.5">
                <Label>{t('books.fields.publishPlace')}</Label>
                <Input value={form.publishPlace} onChange={(event) => update('publishPlace', event.target.value)} />
              </div>
              <div className="space-y-1.5">
                <Label>{t('books.fields.publishYear')}</Label>
                <Input value={form.publishYear} onChange={(event) => update('publishYear', event.target.value)} />
              </div>
              <div className="space-y-1.5">
                <Label>{t('books.fields.pages')}</Label>
                <Input
                  type="number"
                  min={0}
                  value={form.numOfPages}
                  onChange={(event) => update('numOfPages', event.target.value)}
                />
              </div>
              <div className="space-y-1.5">
                <Label>{t('books.fields.dimension')}</Label>
                <Input value={form.dimension} onChange={(event) => update('dimension', event.target.value)} />
              </div>
              <div className="space-y-1.5">
                <Label>{t('books.fields.udk')}</Label>
                <Input value={form.udk} onChange={(event) => update('udk', event.target.value)} />
              </div>
              <div className="space-y-1.5">
                <Label>{t('books.fields.binding')}</Label>
                <select
                  className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                  value={form.binding}
                  onChange={(event) => update('binding', event.target.value)}
                >
                  <option value="">{t('common.none')}</option>
                  <option value="t">{t('books.binding.hard')}</option>
                  <option value="b">{t('books.binding.paperback')}</option>
                  <option value="k">{t('books.binding.carton')}</option>
                  <option value="ko">{t('books.binding.leather')}</option>
                  <option value="l">{t('books.binding.luxury')}</option>
                </select>
              </div>
              <div className="space-y-1.5">
                <Label>{t('books.fields.origin')}</Label>
                <select
                  className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                  value={form.origin}
                  onChange={(event) => update('origin', event.target.value)}
                >
                  <option value="">{t('common.none')}</option>
                  <option value="ob">{t('books.origin.mandatory')}</option>
                  <option value="ku">{t('books.origin.purchase')}</option>
                  <option value="ra">{t('books.origin.exchange')}</option>
                  <option value="po">{t('books.origin.gift')}</option>
                </select>
              </div>
              <div className="space-y-1.5">
                <Label>{t('books.fields.placeOnShelf')}</Label>
                <Input value={form.placeOnShelf} onChange={(event) => update('placeOnShelf', event.target.value)} />
              </div>
              <div className="space-y-1.5">
                <Label>{t('books.fields.price')}</Label>
                <Input
                  type="number"
                  min={0}
                  step="0.01"
                  value={form.price}
                  onChange={(event) => update('price', event.target.value)}
                />
              </div>
              <div className="space-y-1.5">
                <Label>{t('books.fields.dateAdd')}</Label>
                <DateInput value={form.dateAdd} onChange={(value) => update('dateAdd', value)} />
              </div>
              <div className="space-y-1.5">
                <Label>{t('books.fields.part')}</Label>
                <Input value={form.part} onChange={(event) => update('part', event.target.value)} />
              </div>
              <div className="space-y-1.5">
                <Label>{t('books.fields.issueNumber')}</Label>
                <Input value={form.issueNumber} onChange={(event) => update('issueNumber', event.target.value)} />
              </div>
            </div>

            <div className="space-y-1.5">
              <Label>{t('books.fields.notice')}</Label>
              <Textarea rows={2} value={form.notice} onChange={(event) => update('notice', event.target.value)} />
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

      <InventoryDiscrepancyDialog
        discrepancy={discrepancy}
        open={Boolean(discrepancy)}
        onClose={() => setDiscrepancy(null)}
      />
    </>
  )
}
