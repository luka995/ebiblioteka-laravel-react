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
import { Textarea } from '@/components/ui/textarea'
import { CategorySelect, type CategoryOption } from '@/components/categories/category-select'
import { BookCopyMetadataFields } from '@/components/books/book-copy-metadata-fields'
import {
  copyMetadataPayload,
  copyQuantityPayload,
  EMPTY_COPY,
  splitAuthors,
  type CopyFormValues,
} from '@/components/books/copy-form'
import { InventoryDiscrepancyDialog } from '@/components/books/inventory-discrepancy-dialog'
import { useAuth } from '@/hooks/useAuth'
import type { Book, BookCopy, InventoryDiscrepancy, IsbnLookupResult } from '@/types'

interface BookCopyQuickAddModalProps {
  open: boolean
  onClose: () => void
  onSuccess?: (book?: Book) => void
  initialIsbn?: string
}

type Mode = 'idle' | 'reuse' | 'create'

export function BookCopyQuickAddModal({ open, onClose, onSuccess, initialIsbn }: BookCopyQuickAddModalProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { activeLibrary } = useAuth()

  const [isbn, setIsbn] = useState('')
  const [isLookingUp, setIsLookingUp] = useState(false)
  const [lookup, setLookup] = useState<IsbnLookupResult | null>(null)
  const [mode, setMode] = useState<Mode>('idle')
  const [resolvedBook, setResolvedBook] = useState<Book | null>(null)
  const [copy, setCopy] = useState<CopyFormValues>({ ...EMPTY_COPY })
  const [title, setTitle] = useState('')
  const [authors, setAuthors] = useState('')
  const [description, setDescription] = useState('')
  const [coverUrl, setCoverUrl] = useState('')
  const [primary, setPrimary] = useState<CategoryOption | null>(null)
  const [secondary, setSecondary] = useState<CategoryOption | null>(null)
  const [categorySeed, setCategorySeed] = useState('')
  const [discrepancy, setDiscrepancy] = useState<InventoryDiscrepancy | null>(null)
  const [isSaving, setIsSaving] = useState(false)

  useEffect(() => {
    if (!open) return
    setIsbn(initialIsbn ?? '')
    setLookup(null)
    setMode('idle')
    setResolvedBook(null)
    setCopy({ ...EMPTY_COPY, isbn: (initialIsbn ?? '').trim() })
    setTitle('')
    setAuthors('')
    setDescription('')
    setCoverUrl('')
    setPrimary(null)
    setSecondary(null)
    setCategorySeed('')
    setDiscrepancy(null)
  }, [open, initialIsbn])

  const libraryId = resolvedBook?.library_id ?? activeLibrary?.id ?? null
  const invNumberAuto = resolvedBook
    ? resolvedBook.inv_number_auto !== false
    : activeLibrary?.inv_number_auto !== false

  const updateCopy = (key: keyof CopyFormValues, value: string) =>
    setCopy((current) => ({ ...current, [key]: value }))

  const chooseReuse = (target: Book) => {
    setMode('reuse')
    setResolvedBook(target)
  }

  const chooseCreate = () => {
    setMode('create')
    setResolvedBook(null)
  }

  const runLookup = async () => {
    if (!isbn.trim()) return
    setIsLookingUp(true)
    try {
      const params = new URLSearchParams({ isbn: isbn.trim() })
      if (activeLibrary?.id) params.set('library_id', String(activeLibrary.id))
      const result = await api.get<IsbnLookupResult>(`${apiPaths.bookIsbnLookup}?${params.toString()}`)
      setLookup(result)

      if (result.book) {
        const first = result.existing_copies[0]
        setMode('reuse')
        setResolvedBook(result.book)
        setCopy((current) => ({
          ...current,
          isbn: first?.isbn ?? isbn.trim(),
          publisher: first?.publisher ?? '',
          publishPlace: first?.publish_place ?? '',
          publishYear: first?.publish_year ?? '',
          issueNumber: first?.issue_number ?? '',
          numOfPages: first?.num_of_pages != null ? String(first.num_of_pages) : '',
          dimension: first?.dimension ?? '',
          part: first?.part ?? '',
          udk: first?.udk ?? '',
          binding: first?.binding ?? '',
          origin: first?.origin ?? '',
          bookNumber: first?.book_number ?? '',
          placeOnShelf: first?.place_on_shelf ?? '',
          price: first?.price != null ? String(first.price) : '0',
          dateAdd: first?.date_add ?? '',
          notice: first?.notice ?? '',
        }))
        return
      }

      if (result.metadata) {
        const meta = result.metadata

        setMode('create')
        setResolvedBook(null)
        setTitle(meta.title ?? '')
        setAuthors(meta.authors.join('; '))
        setDescription(meta.description ?? '')
        setCoverUrl(meta.cover_url ?? '')
        setCategorySeed(meta.category ?? '')
        setCopy((current) => ({
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

      setMode('idle')
      setResolvedBook(null)
      toast.info(t('books.isbn.noMetadata'))
    } catch (error) {
      if (error instanceof ApiError) toast.error(error.messageText ?? t('errors.unexpected'))
    } finally {
      setIsLookingUp(false)
    }
  }

  const invalidate = (book?: Book) => {
    void queryClient.invalidateQueries({ queryKey: ['book-copies'] })
    void queryClient.invalidateQueries({ queryKey: ['books'] })
    if (book) void queryClient.invalidateQueries({ queryKey: ['book', book.id] })
  }

  const submitCopies = async (target: Book) => {
    const payload: Record<string, unknown> = {
      ...copyMetadataPayload(copy),
      ...copyQuantityPayload(copy, invNumberAuto),
    }

    const response = await api.post<{ data: BookCopy[] }>(apiPaths.bookCopies(target.id), payload)
    invalidate(target)
    toast.success(t('books.copies.created', { count: response.data?.length ?? 1 }))
    onSuccess?.(target)
    onClose()
  }

  const submitNewTitle = async () => {
    if (libraryId === null) {
      toast.error(t('books.libraryRequired'))
      return
    }

    const payload: Record<string, unknown> = {
      name: title.trim(),
      authors: splitAuthors(authors),
      description: description.trim() || null,
      cover_url: coverUrl.trim() || null,
      category_primary_id: primary?.id ?? null,
      category_secondary_id: secondary?.id ?? null,
      library_id: libraryId,
      with_copies: true,
      ...copyMetadataPayload(copy),
      ...copyQuantityPayload(copy, invNumberAuto),
    }

    if (lookup?.matches?.length) {
      payload.confirm_duplicate = true
    }

    await api.post(apiPaths.books, payload)
    invalidate()
    toast.success(t('books.quickAdd.created'))
    onSuccess?.()
    onClose()
  }

  const submit = async () => {
    setIsSaving(true)
    try {
      if (mode === 'reuse' && resolvedBook) {
        await submitCopies(resolvedBook)
        return
      }

      if (mode === 'create') {
        await submitNewTitle()
        return
      }

      toast.info(t('books.quickAdd.lookupFirst'))
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

  const canSubmit = mode === 'reuse' ? Boolean(resolvedBook) : mode === 'create' ? title.trim() !== '' : false

  return (
    <>
      <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle className="font-brand-heading text-xl">{t('books.quickAdd.title')}</DialogTitle>
            <DialogDescription>{t('books.quickAdd.subtitle')}</DialogDescription>
          </DialogHeader>

          <div className="space-y-4">
            <div className="rounded-lg border bg-muted/40 p-3">
              <Label htmlFor="quick-isbn">{t('books.isbn.label')}</Label>
              <div className="mt-1.5 flex gap-2">
                <Input
                  id="quick-isbn"
                  value={isbn}
                  onChange={(event) => setIsbn(event.target.value)}
                  placeholder={t('books.isbn.placeholder')}
                  onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                      event.preventDefault()
                      void runLookup()
                    }
                  }}
                />
                <Button type="button" variant="outline" onClick={() => void runLookup()} disabled={isLookingUp}>
                  <Search />
                  {t('books.isbn.search')}
                </Button>
              </div>

              {lookup?.source ? (
                <p className="mt-2 text-xs text-muted-foreground">
                  {t('books.isbn.source')}:{' '}
                  <Badge variant="secondary">
                    {t(`books.isbn.sources.${lookup.source}`, { defaultValue: lookup.source })}
                  </Badge>
                </p>
              ) : null}
            </div>

            {lookup?.matches?.length && mode === 'create' ? (
              <div className="rounded-lg border border-brand-accent/40 bg-brand-soft/40 p-3 text-sm">
                <p className="font-medium">{t('books.duplicate.title')}</p>
                <p className="mt-0.5 text-xs text-muted-foreground">{t('books.duplicate.hint')}</p>
                <ul className="mt-2 space-y-2">
                  {lookup.matches.map((match) => (
                    <li
                      key={match.id}
                      className="flex items-center justify-between gap-2 rounded-md border bg-background p-2"
                    >
                      <span className="min-w-0 truncate">
                        {match.name}
                        {match.authors?.length ? ` — ${match.authors.map((author) => author.name).join('; ')}` : ''}
                      </span>
                      <Button type="button" variant="outline" size="sm" onClick={() => chooseReuse(match)}>
                        {t('books.duplicate.useExisting')}
                      </Button>
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}

            {mode === 'reuse' && resolvedBook ? (
              <div className="rounded-lg border border-brand-accent/40 bg-brand-soft/40 p-3 text-sm">
                <p className="font-medium">{t('books.quickAdd.existingTitle')}</p>
                <p className="mt-0.5 text-muted-foreground">{resolvedBook.name}</p>
                {lookup?.existing_copies.length ? (
                  <p className="mt-1 text-xs text-muted-foreground">
                    {t('books.isbn.existingCopies', { count: lookup.existing_copies.length })}
                  </p>
                ) : null}
                {lookup?.metadata ? (
                  <Button type="button" variant="outline" size="sm" className="mt-2" onClick={chooseCreate}>
                    {t('books.quickAdd.createNew')}
                  </Button>
                ) : null}
              </div>
            ) : null}

            {mode === 'create' ? (
              <div className="space-y-4 rounded-lg border p-3">
                <p className="font-brand-heading text-sm font-semibold">{t('books.quickAdd.newTitle')}</p>

                <div className="space-y-1.5">
                  <Label htmlFor="quick-title">{t('books.fields.name')}</Label>
                  <Input id="quick-title" value={title} onChange={(event) => setTitle(event.target.value)} />
                </div>

                <div className="space-y-1.5">
                  <Label htmlFor="quick-authors">{t('books.fields.authors')}</Label>
                  <Input id="quick-authors" value={authors} onChange={(event) => setAuthors(event.target.value)} />
                  <p className="text-xs text-muted-foreground">{t('books.fields.authorsHint')}</p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-1.5">
                    <Label>{t('books.fields.categoryPrimary')}</Label>
                    <CategorySelect
                      libraryId={libraryId}
                      value={primary}
                      onChange={setPrimary}
                      seed={categorySeed}
                      allowCreate
                      autoSelectSingle
                    />
                  </div>
                  <div className="space-y-1.5">
                    <Label>{t('books.fields.categorySecondary')}</Label>
                    <CategorySelect
                      libraryId={libraryId}
                      value={secondary}
                      onChange={setSecondary}
                      excludeId={primary?.id}
                    />
                  </div>
                </div>

                <div className="space-y-1.5">
                  <Label htmlFor="quick-cover">{t('books.fields.coverUrl')}</Label>
                  <Input
                    id="quick-cover"
                    value={coverUrl}
                    onChange={(event) => setCoverUrl(event.target.value)}
                    placeholder="https://..."
                  />
                </div>

                <div className="space-y-1.5">
                  <Label htmlFor="quick-description">{t('books.fields.description')}</Label>
                  <Textarea
                    id="quick-description"
                    rows={3}
                    value={description}
                    onChange={(event) => setDescription(event.target.value)}
                  />
                </div>
              </div>
            ) : null}

            {mode !== 'idle' ? (
              <div className="space-y-4 rounded-lg border p-3">
                <p className="font-brand-heading text-sm font-semibold">{t('books.copies.section')}</p>

                <div className="grid gap-4 sm:grid-cols-2">
                  {invNumberAuto ? (
                    <div className="space-y-1.5">
                      <Label htmlFor="quick-copy-count">{t('books.copies.count')}</Label>
                      <Input
                        id="quick-copy-count"
                        type="number"
                        min={1}
                        max={100}
                        value={copy.copies}
                        onChange={(event) => updateCopy('copies', event.target.value)}
                      />
                    </div>
                  ) : (
                    <div className="space-y-1.5">
                      <Label htmlFor="quick-copy-order">{t('books.copies.orderNumber')}</Label>
                      <Input
                        id="quick-copy-order"
                        value={copy.orderNumber}
                        onChange={(event) => updateCopy('orderNumber', event.target.value)}
                        placeholder={t('books.copies.orderNumberPlaceholder')}
                      />
                    </div>
                  )}

                  <div className="space-y-1.5">
                    <Label htmlFor="quick-copy-isbn">{t('books.fields.isbn')}</Label>
                    <Input
                      id="quick-copy-isbn"
                      value={copy.isbn}
                      onChange={(event) => updateCopy('isbn', event.target.value)}
                    />
                  </div>
                </div>

                <BookCopyMetadataFields value={copy} onChange={updateCopy} />
              </div>
            ) : null}
          </div>

          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" onClick={onClose}>
              {t('common.cancel')}
            </Button>
            <Button type="button" variant="brand" onClick={() => void submit()} disabled={isSaving || !canSubmit}>
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
