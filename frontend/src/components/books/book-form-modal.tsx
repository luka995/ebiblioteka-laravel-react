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
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import {
  AlertDialog,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
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
import { useAuth } from '@/hooks/useAuth'
import type { Book, BookDuplicateCheckResult, IsbnLookupResult } from '@/types'

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
  const [duplicates, setDuplicates] = useState<Book[]>([])
  const [pendingDuplicate, setPendingDuplicate] = useState<Book[] | null>(null)

  const form = useForm<FormValues>({
    defaultValues: { name: '', authors: '', description: '', coverUrl: '' },
  })

  const watchedName = form.watch('name')
  const watchedAuthors = form.watch('authors')

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
      authors: book?.authors?.map((author) => author.name).join('; ') ?? '',
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
    setDuplicates([])
    setPendingDuplicate(null)
  }, [open, book, form])

  useEffect(() => {
    if (!open || editing || existingBook) {
      setDuplicates([])
      return
    }

    const name = watchedName.trim()

    if (name.length < 2) {
      setDuplicates([])
      return
    }

    let cancelled = false
    const handle = window.setTimeout(async () => {
      try {
        const params = new URLSearchParams({ name })
        splitAuthors(watchedAuthors).forEach((author) => params.append('authors[]', author))
        if (libraryId !== null) params.set('library_id', String(libraryId))
        const result = await api.get<BookDuplicateCheckResult>(
          `${apiPaths.bookDuplicateCheck}?${params.toString()}`,
        )
        if (!cancelled) setDuplicates(result.matches)
      } catch {
        // provera duplikata ne sme da blokira formu
      }
    }, 400)

    return () => {
      cancelled = true
      window.clearTimeout(handle)
    }
  }, [open, editing, existingBook, watchedName, watchedAuthors, libraryId])

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
        setDuplicates([])
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
        if (result.metadata.authors.length) form.setValue('authors', result.metadata.authors.join('; '))
        if (result.metadata.description) form.setValue('description', result.metadata.description)
        if (result.metadata.cover_url) form.setValue('coverUrl', result.metadata.cover_url)
        if (result.metadata.category) setCategorySeed(result.metadata.category)
        setDuplicates(result.matches)

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
      } else {
        setDuplicates([])
      }
    } catch (error) {
      if (error instanceof ApiError) {
        toast.error(error.messageText ?? t('errors.unexpected'))
      }
    } finally {
      setIsLookingUp(false)
    }
  }

  const submitCopiesTo = async (target: Book) => {
    const copiesCount = Math.max(1, Number(copy.copies) || 1)
    await api.post(apiPaths.bookCopies(target.id), {
      ...copyMetadataPayload(copy),
      ...copyQuantityPayload(copy, invNumberAuto),
    })
    await queryClient.invalidateQueries({ queryKey: ['book', target.id] })
    await queryClient.invalidateQueries({ queryKey: ['books'] })
    await queryClient.invalidateQueries({ queryKey: ['book-copies'] })
    toast.success(t('books.copies.created', { count: invNumberAuto ? copiesCount : 1 }))
    onSuccess(target.name)
    onClose()
  }

  const useExisting = (target: Book) => {
    setExistingBook(target)
    setWithCopies(true)
    setDuplicates([])
    setPendingDuplicate(null)
    setCopy((current) => ({
      ...current,
      isbn: current.isbn.trim() || isbn.trim(),
    }))
  }

  const submit = async (values: FormValues, options: { confirmDuplicate?: boolean } = {}) => {
    if (!editing && libraryId === null) {
      toast.error(t('books.libraryRequired'))
      return
    }

    try {
      if (!editing && existingBook) {
        await submitCopiesTo(existingBook)
        return
      }

      const payload: Record<string, unknown> = {
        name: values.name.trim(),
        authors: splitAuthors(values.authors),
        description: values.description.trim() || null,
        image: imagePath,
        cover_url: imagePath ? null : (values.coverUrl.trim() || null),
        category_primary_id: primary?.id ?? null,
        category_secondary_id: secondary?.id ?? null,
        ...(!editing && libraryId !== null ? { library_id: libraryId } : {}),
      }

      if (!editing && withCopies) {
        payload.with_copies = true
        Object.assign(payload, copyMetadataPayload(copy), copyQuantityPayload(copy, invNumberAuto))
      }

      if (options.confirmDuplicate) {
        payload.confirm_duplicate = true
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
        if (error.status === 409) {
          const data = error.data as { duplicate_books?: Book[] } | undefined
          if (data?.duplicate_books?.length) {
            setPendingDuplicate(data.duplicate_books)
            return
          }
        }
        toast.error(error.messageText ?? t('errors.unexpected'))
      }
    }
  }

  const onSubmit = form.handleSubmit((values) => submit(values))

  const sourceLabel = useMemo(() => {
    if (!lookup?.source) return null
    return t(`books.isbn.sources.${lookup.source}`, { defaultValue: lookup.source })
  }, [lookup, t])

  const showCopySection = !editing && (Boolean(existingBook) || withCopies)

  return (
    <>
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
            </div>
          ) : null}

          {!existingBook && duplicates.length ? (
            <div className="rounded-lg border border-brand-accent/40 bg-brand-soft/40 p-3 text-sm">
              <p className="font-medium">{t('books.duplicate.title')}</p>
              <p className="mt-0.5 text-xs text-muted-foreground">{t('books.duplicate.hint')}</p>
              <ul className="mt-2 space-y-2">
                {duplicates.map((match) => (
                  <li
                    key={match.id}
                    className="flex items-center justify-between gap-2 rounded-md border bg-background p-2"
                  >
                    <span className="min-w-0 truncate">
                      {match.name}
                      {match.authors?.length ? ` — ${match.authors.map((author) => author.name).join('; ')}` : ''}
                    </span>
                    <Button type="button" variant="outline" size="sm" onClick={() => useExisting(match)}>
                      {t('books.duplicate.useExisting')}
                    </Button>
                  </li>
                ))}
              </ul>
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

              </div>

              <BookCopyMetadataFields value={copy} onChange={updateCopy} />
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

      <AlertDialog open={Boolean(pendingDuplicate)} onOpenChange={(value) => !value && setPendingDuplicate(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('books.duplicate.confirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('books.duplicate.confirmText', { name: form.getValues('name') })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => {
                const values = form.getValues()
                setPendingDuplicate(null)
                void submit(values, { confirmDuplicate: true })
              }}
            >
              {t('books.duplicate.continueAnyway')}
            </Button>
            <Button
              type="button"
              variant="brand"
              onClick={() => {
                const first = pendingDuplicate?.[0]
                if (first) useExisting(first)
              }}
            >
              {t('books.duplicate.useExisting')}
            </Button>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}
