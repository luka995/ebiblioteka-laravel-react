import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { DateInput } from '@/components/ui/date-input'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

export interface BookCopyFilters {
  publisher: string
  publish_place: string
  publish_year: string
  issue_number: string
  dimension: string
  part: string
  udk: string
  book_number: string
  place_on_shelf: string
  seq_number: string
  notice: string
  rec_error_notice: string
  binding: string
  origin: string
  status: string
  price_from: string
  price_to: string
  num_of_pages_from: string
  num_of_pages_to: string
  date_add_from: string
  date_add_to: string
}

export const EMPTY_BOOK_COPY_FILTERS: BookCopyFilters = {
  publisher: '',
  publish_place: '',
  publish_year: '',
  issue_number: '',
  dimension: '',
  part: '',
  udk: '',
  book_number: '',
  place_on_shelf: '',
  seq_number: '',
  notice: '',
  rec_error_notice: '',
  binding: '',
  origin: '',
  status: '',
  price_from: '',
  price_to: '',
  num_of_pages_from: '',
  num_of_pages_to: '',
  date_add_from: '',
  date_add_to: '',
}

const TEXT_FIELDS: Array<{ key: keyof BookCopyFilters; label: string }> = [
  { key: 'publisher', label: 'books.fields.publisher' },
  { key: 'publish_place', label: 'books.fields.publishPlace' },
  { key: 'publish_year', label: 'books.fields.publishYear' },
  { key: 'issue_number', label: 'books.fields.issueNumber' },
  { key: 'dimension', label: 'books.fields.dimension' },
  { key: 'part', label: 'books.fields.part' },
  { key: 'udk', label: 'books.fields.udk' },
  { key: 'book_number', label: 'books.fields.bookNumber' },
  { key: 'place_on_shelf', label: 'books.fields.placeOnShelf' },
  { key: 'seq_number', label: 'books.fields.seqNumber' },
  { key: 'notice', label: 'books.fields.notice' },
  { key: 'rec_error_notice', label: 'books.copies.recErrorNotice' },
]

const ANY_VALUE = 'any'

export function countActiveFilters(filters: BookCopyFilters): number {
  return Object.values(filters).filter((value) => value !== '').length
}

interface BookCopyFiltersPanelProps {
  filters: BookCopyFilters
  onApply: (filters: BookCopyFilters) => void
  onClear: () => void
}

export function BookCopyFiltersPanel({ filters, onApply, onClear }: BookCopyFiltersPanelProps) {
  const { t } = useTranslation()
  const [draft, setDraft] = useState<BookCopyFilters>(filters)

  useEffect(() => {
    setDraft(filters)
  }, [filters])

  const setField = (key: keyof BookCopyFilters, value: string) => {
    setDraft((previous) => ({ ...previous, [key]: value }))
  }

  const selectField = (key: keyof BookCopyFilters, label: string, options: Array<{ value: string; label: string }>) => (
    <div key={key} className="space-y-1.5">
      <Label>{t(label)}</Label>
      <Select
        value={draft[key] || ANY_VALUE}
        onValueChange={(value) => setField(key, value === ANY_VALUE ? '' : value)}
      >
        <SelectTrigger className="w-full">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {options.map((option) => (
            <SelectItem key={option.value} value={option.value || ANY_VALUE}>
              {option.label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  )

  return (
    <form
      className="rounded-lg border bg-card p-4"
      onSubmit={(event) => {
        event.preventDefault()
        onApply(draft)
      }}
    >
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {TEXT_FIELDS.map(({ key, label }) => (
          <div key={key} className="space-y-1.5">
            <Label htmlFor={`copy-filter-${key}`}>{t(label)}</Label>
            <Input
              id={`copy-filter-${key}`}
              value={draft[key]}
              onChange={(event) => setField(key, event.target.value)}
            />
          </div>
        ))}

        {selectField('binding', 'books.fields.binding', [
          { value: '', label: t('books.copies.filters.bindingAll') },
          { value: 't', label: t('books.binding.hard') },
          { value: 'b', label: t('books.binding.paperback') },
          { value: 'k', label: t('books.binding.carton') },
          { value: 'ko', label: t('books.binding.leather') },
          { value: 'l', label: t('books.binding.luxury') },
        ])}

        {selectField('origin', 'books.fields.origin', [
          { value: '', label: t('books.copies.filters.originAll') },
          { value: 'ob', label: t('books.origin.mandatory') },
          { value: 'ku', label: t('books.origin.purchase') },
          { value: 'ra', label: t('books.origin.exchange') },
          { value: 'po', label: t('books.origin.gift') },
        ])}

        {selectField('status', 'books.copies.status', [
          { value: '', label: t('books.copies.filters.statusAll') },
          { value: 'available', label: t('books.status.available') },
          { value: 'borrowed', label: t('books.status.borrowed') },
          { value: 'record_error', label: t('books.status.record_error') },
          { value: 'written_off', label: t('books.status.written_off') },
        ])}
      </div>

      <div className="mt-4 grid gap-4 border-t pt-4 sm:grid-cols-2 lg:grid-cols-3">
        <div className="space-y-1.5">
          <Label>{t('books.fields.price')}</Label>
          <div className="flex items-center gap-2">
            <Input
              type="number"
              min={0}
              step="0.01"
              inputMode="decimal"
              placeholder={t('books.copies.filters.from')}
              value={draft.price_from}
              onChange={(event) => setField('price_from', event.target.value)}
            />
            <span className="text-muted-foreground">–</span>
            <Input
              type="number"
              min={0}
              step="0.01"
              inputMode="decimal"
              placeholder={t('books.copies.filters.to')}
              value={draft.price_to}
              onChange={(event) => setField('price_to', event.target.value)}
            />
          </div>
        </div>

        <div className="space-y-1.5">
          <Label>{t('books.fields.pages')}</Label>
          <div className="flex items-center gap-2">
            <Input
              type="number"
              min={0}
              inputMode="numeric"
              placeholder={t('books.copies.filters.from')}
              value={draft.num_of_pages_from}
              onChange={(event) => setField('num_of_pages_from', event.target.value)}
            />
            <span className="text-muted-foreground">–</span>
            <Input
              type="number"
              min={0}
              inputMode="numeric"
              placeholder={t('books.copies.filters.to')}
              value={draft.num_of_pages_to}
              onChange={(event) => setField('num_of_pages_to', event.target.value)}
            />
          </div>
        </div>

        <div className="space-y-1.5">
          <Label>{t('books.fields.dateAdd')}</Label>
          <div className="flex items-center gap-2">
            <DateInput
              placeholder={t('books.copies.filters.from')}
              value={draft.date_add_from}
              onChange={(value) => setField('date_add_from', value)}
            />
            <span className="text-muted-foreground">–</span>
            <DateInput
              placeholder={t('books.copies.filters.to')}
              value={draft.date_add_to}
              onChange={(value) => setField('date_add_to', value)}
            />
          </div>
        </div>
      </div>

      <div className="mt-4 flex items-center justify-end gap-2">
        <Button type="button" variant="ghost" onClick={onClear}>
          {t('books.copies.filters.clear')}
        </Button>
        <Button type="submit" variant="brand">
          {t('books.copies.filters.apply')}
        </Button>
      </div>
    </form>
  )
}
