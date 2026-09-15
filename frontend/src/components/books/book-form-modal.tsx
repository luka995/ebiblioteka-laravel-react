import { useEffect, useMemo, useRef, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { ImagePlus, Search, Trash2 } from 'lucide-react'
import { api, apiPaths, ApiError } from '@/lib/api'
import { storageUrl } from '@/lib/environment'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { DateInput } from '@/components/ui/date-input'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { CategorySelect, type CategoryOption } from '@/components/categories/category-select'
import { useAuth } from '@/hooks/useAuth'
import type { Book, IsbnLookupResult } from '@/types'

interface BookFormModalProps {
  open: boolean
  onClose: () => void
  onSuccess: (name: string) => void
  book?: Book | null
}

interface FormValues {
  name: string
  authors: string
  description: string
  coverUrl: string
}

interface CopyFormValues {
  copies: string
  orderNumber: string
  isbn: string
  publisher: string
  publishPlace: string
  publishYear: string
  issueNumber: string
  numOfPages: string
  dimension: string
  part: string
  udk: string
  binding: string
  origin: string
  bookNumber: string
  placeOnShelf: string
  price: string
  dateAdd: string
  notice: string
}

const EMPTY_COPY: CopyFormValues = {
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

export function BookFormModal({ open, onClose, onSuccess, book }: BookFormModalProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { activeLibrary } = useAuth()
  const editing = Boolean(book)

  const fileRef = useRef<HTMLInputElement>(null)
  const [imagePath, setImagePath] = useState<string | null>(null)
  const [imagePreview, setImagePreview] = useState<string | null>(null)
  const [uploading, setUploading] = useState(false)
  const [primary, setPrimary] = useState<CategoryOption | null>(null)
  const [secondary, setSecondary] = useState<CategoryOption | null>(null)
  const [categorySeed, setCategorySeed] = useState('')
  const [isbn, setIsbn] = useState('')
  const [isLookingUp, setIsLookingUp] = useState(false)
  const [lookup, setLookup] = useState<IsbnLookupResult | null>(null)
  const [existingBook, setExistingBook] = useState<Book | null>(null)
  const [withCopies, setWithCopies] = useState(false)
  const [copy, setCopy] = useState<CopyFormValues>({ ...EMPTY_COPY })

  const form = useForm<FormValues>({
    defaultValues: { name: '', authors: '', description: '', coverUrl: '' },
  })

  const libraryId = editing ? (book?.library_id ?? null) : (activeLibrary?.id ?? null)
  const invNumberAuto = existingBook
    ? existingBook.inv_number_auto !== false
    : activeLibrary?.inv_number_auto !== false

  const updateCopy = (key: keyof CopyFormValues, value: string) =>
    setCopy((current) => ({ ...current, [key]: value }))

  useEffect(() => {
    if (!open) return
    form.reset({
      name: book?.name ?? '',
      authors: book?.authors?.map((author) => author.name).join(', ') ?? '',
      description: book?.description ?? '',
      coverUrl: book?.cover_url ?? '',
    })
    setImagePath(book?.image ?? null)
    setImagePreview(storageUrl(book?.image_url))
    setPrimary(
      book?.category_primary_id
        ? { id: book.category_primary_id, full_name: book.category_primary_name ?? '' }
        : null,
    )
    setSecondary(
      book?.category_secondary_id
        ? { id: book.category_secondary_id, full_name: book.category_secondary_name ?? '' }
        : null,
    )
    setIsbn('')
    setCategorySeed('')
    setLookup(null)
    setExistingBook(null)
    setWithCopies(false)
    setCopy({ ...EMPTY_COPY })
  }, [open, book, form])

  const parseAuthors = (value: string) =>
    value
      .split(',')
      .map((name) => name.trim())
      .filter(Boolean)

  const handleFileChange = async (file: File | undefined) => {
    if (!file) return
    setUploading(true)
    try {
      const result = await api.upload<{ path: string; url: string }>(apiPaths.bookUploadCover, file)
      setImagePath(result.path)
      setImagePreview(storageUrl(result.url))
      form.setValue('coverUrl', '')
    } catch (error) {
      if (error instanceof ApiError) toast.error(error.messageText ?? t('errors.unexpected'))
      else toast.error(t('errors.unexpected'))
    } finally {
      setUploading(false)
    }
  }

  const copyMeta = (): Record<string, unknown> => ({
    isbn: copy.isbn.trim() || null,
    publisher: copy.publisher.trim() || null,
    publish_place: copy.publishPlace.trim() || null,
    publish_year: copy.publishYear.trim() || null,
    issue_number: copy.issueNumber.trim() || null,
    num_of_pages: copy.numOfPages === '' ? null : Number(copy.numOfPages),
    dimension: copy.dimension.trim() || null,
    part: copy.part.trim() || null,
    udk: copy.udk.trim() || null,
    binding: copy.binding || null,
    origin: copy.origin || null,
    book_number: copy.bookNumber.trim() || null,
    place_on_shelf: copy.placeOnShelf.trim() || null,
    price: copy.price === '' ? 0 : Number(copy.price),
    date_add: copy.dateAdd || null,
    notice: copy.notice.trim() || null,
  })

  const copyQuantity = (): Record<string, unknown> =>
    invNumberAuto
      ? { copies: Math.max(1, Number(copy.copies) || 1) }
      : { order_number: copy.orderNumber.trim() }

  const runLookup = async () => {
    if (!isbn.trim()) return
    setIsLookingUp(true)
    try {
      const params = new URLSearchParams({ isbn: isbn.trim() })
      if (libraryId !== null) params.set('library_id', String(libraryId))
      const result = await api.get<IsbnLookupResult>(`${apiPaths.bookIsbnLookup}?${params.toString()}`)
      setLookup(result)

      if (result.book) {
        const first = result.existing_copies[0]
        setExistingBook(result.book)
        setWithCopies(true)
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

      setExistingBook(null)

      if (result.metadata) {
        if (result.metadata.title) form.setValue('name', result.metadata.title)
        if (result.metadata.authors.length) form.setValue('authors', result.metadata.authors.join(', '))
        if (result.metadata.description) form.setValue('description', result.metadata.description)
        if (result.metadata.cover_url) form.setValue('coverUrl', result.metadata.cover_url)
        if (result.metadata.category) setCategorySeed(result.metadata.category)

        setCopy((current) => ({
          ...current,
          isbn: result.metadata?.isbn ?? current.isbn,
          publisher: result.metadata?.publisher ?? current.publisher,
          publishPlace: result.metadata?.publish_place ?? current.publishPlace,
          publishYear: result.metadata?.publish_year ?? current.publishYear,
          numOfPages: result.metadata?.pages ? String(result.metadata.pages) : current.numOfPages,
          dimension: result.metadata?.dimensions ?? current.dimension,
          udk: result.metadata?.udk ?? current.udk,
        }))
        setWithCopies(true)
      }
    } catch (error) {
      if (error instanceof ApiError) {
        toast.error(error.messageText ?? t('errors.unexpected'))
      }
    } finally {
      setIsLookingUp(false)
    }
  }

  const onSubmit = form.handleSubmit(async (values) => {
    if (!editing && libraryId === null) {
      toast.error(t('books.libraryRequired'))
      return
    }

    const copiesCount = Math.max(1, Number(copy.copies) || 1)

    try {
      if (!editing && existingBook) {
        await api.post(apiPaths.bookCopies(existingBook.id), {
          ...copyMeta(),
          ...copyQuantity(),
        })
        await queryClient.invalidateQueries({ queryKey: ['book', existingBook.id] })
        await queryClient.invalidateQueries({ queryKey: ['books'] })
        await queryClient.invalidateQueries({ queryKey: ['book-copies'] })
        toast.success(t('books.copies.created', { count: invNumberAuto ? copiesCount : 1 }))
        onSuccess(existingBook.name)
        onClose()
        return
      }

      const payload: Record<string, unknown> = {
        name: values.name.trim(),
        authors: parseAuthors(values.authors),
        description: values.description.trim() || null,
        image: imagePath,
        cover_url: imagePath ? null : (values.coverUrl.trim() || null),
        category_primary_id: primary?.id ?? null,
        category_secondary_id: secondary?.id ?? null,
        ...(!editing && libraryId !== null ? { library_id: libraryId } : {}),
      }

      if (!editing && withCopies) {
        payload.with_copies = true
        Object.assign(payload, copyMeta(), copyQuantity())
      }

      if (editing && book) {
        await api.put(apiPaths.book(book.id), payload)
      } else {
        await api.post(apiPaths.books, payload)
      }

      await queryClient.invalidateQueries({ queryKey: ['books'] })
      if (!editing && withCopies) {
        await queryClient.invalidateQueries({ queryKey: ['book-copies'] })
      }
      onSuccess(payload.name as string)
      onClose()
    } catch (error) {
      if (error instanceof ApiError) {
        toast.error(error.messageText ?? t('errors.unexpected'))
      }
    }
  })

  const sourceLabel = useMemo(() => {
    if (!lookup?.source) return null
    return t(`books.isbn.sources.${lookup.source}`, { defaultValue: lookup.source })
  }, [lookup, t])

  const showCopySection = !editing && (Boolean(existingBook) || withCopies)

  return (
    <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle className="font-brand-heading text-xl">
            {editing ? t('books.editTitle') : t('books.formTitle')}
          </DialogTitle>
          <DialogDescription>
            {editing ? t('books.editSubtitle') : t('books.formSubtitle')}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {!editing ? (
            <div className="rounded-lg border bg-muted/40 p-3">
              <Label htmlFor="book-isbn">{t('books.isbn.label')}</Label>
              <div className="mt-1.5 flex gap-2">
                <Input
                  id="book-isbn"
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

              {sourceLabel ? (
                <p className="mt-2 text-xs text-muted-foreground">
                  {t('books.isbn.source')}: <Badge variant="secondary">{sourceLabel}</Badge>
                </p>
              ) : null}

              {lookup?.existing_copies.length ? (
                <div className="mt-2 rounded-md border bg-background p-2 text-xs">
                  <p className="font-medium">{t('books.isbn.existingCopies', { count: lookup.existing_copies.length })}</p>
                  <p className="mt-0.5 text-muted-foreground">
                    {t('books.isbn.existingCopiesHint')}
                  </p>
                </div>
              ) : null}

              {lookup?.matches.length ? (
                <div className="mt-2 rounded-md border bg-background p-2 text-xs">
                  <p className="font-medium">{t('books.isbn.matches')}</p>
                  <ul className="mt-1 space-y-0.5 text-muted-foreground">
                    {lookup.matches.map((match) => (
                      <li key={match.id}>
                        {match.name}
                        {match.authors?.length ? ` — ${match.authors.map((a) => a.name).join(', ')}` : ''}
                      </li>
                    ))}
                  </ul>
                </div>
              ) : null}
            </div>
          ) : null}

          {existingBook ? (
            <div className="rounded-lg border border-brand-accent/40 bg-brand-soft/40 p-3 text-sm">
              <p className="font-medium">{t('books.copies.existingTitle')}</p>
              <p className="mt-0.5 text-muted-foreground">{existingBook.name}</p>
              <p className="mt-1 text-xs text-muted-foreground">{t('books.copies.existingHint')}</p>
            </div>
          ) : (
            <>
              <div className="space-y-1.5">
                <Label htmlFor="book-name">{t('books.fields.name')}</Label>
                <Input id="book-name" {...form.register('name')} />
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="book-authors">{t('books.fields.authors')}</Label>
                <Input id="book-authors" placeholder={t('books.fields.authorsPlaceholder')} {...form.register('authors')} />
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
                  <CategorySelect libraryId={libraryId} value={secondary} onChange={setSecondary} excludeId={primary?.id} />
                </div>
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="book-cover">{t('books.fields.coverUrl')}</Label>
                <Input
                  id="book-cover"
                  placeholder="https://..."
                  {...form.register('coverUrl', {
                    onChange: () => {
                      setImagePath(null)
                      setImagePreview(null)
                    },
                  })}
                />
                <div className="flex items-center gap-3 pt-1">
                  {imagePreview ? (
                    <div className="relative h-24 w-16 overflow-hidden rounded-md border">
                      <img src={imagePreview} alt="" className="h-full w-full object-cover" />
                      <button
                        type="button"
                        onClick={() => {
                          setImagePath(null)
                          setImagePreview(null)
                        }}
                        className="absolute right-1 top-1 rounded bg-black/60 p-1 text-white hover:bg-black/80"
                        aria-label={t('books.fields.removeImage')}
                      >
                        <Trash2 className="size-3.5" />
                      </button>
                    </div>
                  ) : (
                    <button
                      type="button"
                      onClick={() => fileRef.current?.click()}
                      className="grid h-24 w-16 place-items-center rounded-md border border-dashed text-muted-foreground transition-colors hover:border-brand-accent hover:text-brand-accent"
                      aria-label={t('books.fields.uploadCover')}
                    >
                      <ImagePlus className="size-5" />
                    </button>
                  )}
                  <div className="flex flex-col gap-1">
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      disabled={uploading}
                      onClick={() => fileRef.current?.click()}
                    >
                      {uploading ? t('common.loading') : t('books.fields.uploadCover')}
                    </Button>
                    <p className="text-xs text-muted-foreground">{t('books.fields.uploadCoverHint')}</p>
                  </div>
                  <input
                    ref={fileRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    className="hidden"
                    onChange={(event) => {
                      void handleFileChange(event.target.files?.[0])
                      event.target.value = ''
                    }}
                  />
                </div>
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="book-description">{t('books.fields.description')}</Label>
                <Textarea id="book-description" rows={3} {...form.register('description')} />
              </div>
            </>
          )}

          <div className="space-y-1.5">
            <Label>{t('books.fields.library')}</Label>
            <p className="flex h-9 items-center rounded-md border border-input bg-muted/40 px-3 text-sm text-muted-foreground">
              {editing ? (book?.library_name ?? '—') : (existingBook?.library_name ?? activeLibrary?.name ?? '—')}
            </p>
          </div>

          {!editing && !existingBook ? (
            <div className="flex items-center gap-2 rounded-lg border bg-muted/40 p-3">
              <Checkbox
                id="book-with-copies"
                checked={withCopies}
                onCheckedChange={(value) => setWithCopies(value === true)}
              />
              <Label htmlFor="book-with-copies" className="cursor-pointer">
                {t('books.copies.add')}
              </Label>
            </div>
          ) : null}

          {showCopySection ? (
            <div className="space-y-4 rounded-lg border p-3">
              <p className="font-brand-heading text-sm font-semibold">{t('books.copies.section')}</p>

              <div className="grid gap-4 sm:grid-cols-2">
                {invNumberAuto ? (
                  <div className="space-y-1.5">
                    <Label htmlFor="copy-count">{t('books.copies.count')}</Label>
                    <Input
                      id="copy-count"
                      type="number"
                      min={1}
                      max={100}
                      value={copy.copies}
                      onChange={(event) => updateCopy('copies', event.target.value)}
                    />
                  </div>
                ) : (
                  <div className="space-y-1.5">
                    <Label htmlFor="copy-order">{t('books.copies.orderNumber')}</Label>
                    <Input
                      id="copy-order"
                      value={copy.orderNumber}
                      onChange={(event) => updateCopy('orderNumber', event.target.value)}
                      placeholder={t('books.copies.orderNumberPlaceholder')}
                    />
                  </div>
                )}

                <div className="space-y-1.5">
                  <Label htmlFor="copy-isbn">{t('books.fields.isbn')}</Label>
                  <Input
                    id="copy-isbn"
                    value={copy.isbn}
                    onChange={(event) => updateCopy('isbn', event.target.value)}
                  />
                </div>

                <div className="space-y-1.5">
                  <Label>{t('books.fields.publisher')}</Label>
                  <Input value={copy.publisher} onChange={(event) => updateCopy('publisher', event.target.value)} />
                </div>
                <div className="space-y-1.5">
                  <Label>{t('books.fields.publishPlace')}</Label>
                  <Input value={copy.publishPlace} onChange={(event) => updateCopy('publishPlace', event.target.value)} />
                </div>
                <div className="space-y-1.5">
                  <Label>{t('books.fields.publishYear')}</Label>
                  <Input value={copy.publishYear} onChange={(event) => updateCopy('publishYear', event.target.value)} />
                </div>
                <div className="space-y-1.5">
                  <Label>{t('books.fields.pages')}</Label>
                  <Input
                    type="number"
                    min={0}
                    value={copy.numOfPages}
                    onChange={(event) => updateCopy('numOfPages', event.target.value)}
                  />
                </div>
                <div className="space-y-1.5">
                  <Label>{t('books.fields.dimension')}</Label>
                  <Input value={copy.dimension} onChange={(event) => updateCopy('dimension', event.target.value)} />
                </div>
                <div className="space-y-1.5">
                  <Label>{t('books.fields.udk')}</Label>
                  <Input value={copy.udk} onChange={(event) => updateCopy('udk', event.target.value)} />
                </div>
                <div className="space-y-1.5">
                  <Label>{t('books.fields.binding')}</Label>
                  <select
                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                    value={copy.binding}
                    onChange={(event) => updateCopy('binding', event.target.value)}
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
                    value={copy.origin}
                    onChange={(event) => updateCopy('origin', event.target.value)}
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
                  <Input value={copy.placeOnShelf} onChange={(event) => updateCopy('placeOnShelf', event.target.value)} />
                </div>
                <div className="space-y-1.5">
                  <Label>{t('books.fields.price')}</Label>
                  <Input
                    type="number"
                    min={0}
                    step="0.01"
                    value={copy.price}
                    onChange={(event) => updateCopy('price', event.target.value)}
                  />
                </div>
                <div className="space-y-1.5">
                  <Label>{t('books.fields.dateAdd')}</Label>
                  <DateInput value={copy.dateAdd} onChange={(value) => updateCopy('dateAdd', value)} />
                </div>
                <div className="space-y-1.5">
                  <Label>{t('books.fields.part')}</Label>
                  <Input value={copy.part} onChange={(event) => updateCopy('part', event.target.value)} />
                </div>
                <div className="space-y-1.5">
                  <Label>{t('books.fields.issueNumber')}</Label>
                  <Input value={copy.issueNumber} onChange={(event) => updateCopy('issueNumber', event.target.value)} />
                </div>
                <div className="space-y-1.5">
                  <Label>{t('books.fields.bookNumber')}</Label>
                  <Input value={copy.bookNumber} onChange={(event) => updateCopy('bookNumber', event.target.value)} />
                </div>
              </div>

              <div className="space-y-1.5">
                <Label>{t('books.fields.notice')}</Label>
                <Textarea rows={2} value={copy.notice} onChange={(event) => updateCopy('notice', event.target.value)} />
              </div>
            </div>
          ) : null}
        </div>

        <div className="flex justify-end gap-2 pt-2">
          <Button type="button" variant="outline" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="button" variant="brand" onClick={() => void onSubmit()} disabled={form.formState.isSubmitting}>
            {t('common.save')}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}
