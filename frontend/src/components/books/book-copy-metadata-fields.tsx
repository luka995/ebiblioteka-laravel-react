import { useTranslation } from 'react-i18next'
import { DateInput } from '@/components/ui/date-input'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import type { CopyFormValues } from '@/components/books/copy-form'

interface BookCopyMetadataFieldsProps {
  value: CopyFormValues
  onChange: (key: keyof CopyFormValues, value: string) => void
}

/**
 * Zajednicka bibliografska polja fizicke jedinice. Broj kopija / inventarni
 * broj i ISBN lookup ostaju u modalima koji ih koriste.
 */
export function BookCopyMetadataFields({ value, onChange }: BookCopyMetadataFieldsProps) {
  const { t } = useTranslation()

  return (
    <>
      <div className="grid gap-4 sm:grid-cols-2">
        <div className="space-y-1.5">
          <Label>{t('books.fields.publisher')}</Label>
          <Input value={value.publisher} onChange={(event) => onChange('publisher', event.target.value)} />
        </div>
        <div className="space-y-1.5">
          <Label>{t('books.fields.publishPlace')}</Label>
          <Input value={value.publishPlace} onChange={(event) => onChange('publishPlace', event.target.value)} />
        </div>
        <div className="space-y-1.5">
          <Label>{t('books.fields.publishYear')}</Label>
          <Input value={value.publishYear} onChange={(event) => onChange('publishYear', event.target.value)} />
        </div>
        <div className="space-y-1.5">
          <Label>{t('books.fields.pages')}</Label>
          <Input
            type="number"
            min={0}
            value={value.numOfPages}
            onChange={(event) => onChange('numOfPages', event.target.value)}
          />
        </div>
        <div className="space-y-1.5">
          <Label>{t('books.fields.dimension')}</Label>
          <Input value={value.dimension} onChange={(event) => onChange('dimension', event.target.value)} />
        </div>
        <div className="space-y-1.5">
          <Label>{t('books.fields.udk')}</Label>
          <Input value={value.udk} onChange={(event) => onChange('udk', event.target.value)} />
        </div>
        <div className="space-y-1.5">
          <Label>{t('books.fields.binding')}</Label>
          <select
            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
            value={value.binding}
            onChange={(event) => onChange('binding', event.target.value)}
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
            value={value.origin}
            onChange={(event) => onChange('origin', event.target.value)}
          >
            <option value="">{t('common.none')}</option>
            <option value="ob">{t('books.origin.mandatory')}</option>
            <option value="ku">{t('books.origin.purchase')}</option>
            <option value="ra">{t('books.origin.exchange')}</option>
            <option value="po">{t('books.origin.gift')}</option>
          </select>
        </div>
        <div className="space-y-1.5">
          <Label>{t('books.fields.bookNumber')}</Label>
          <Input value={value.bookNumber} onChange={(event) => onChange('bookNumber', event.target.value)} />
        </div>
        <div className="space-y-1.5">
          <Label>{t('books.fields.placeOnShelf')}</Label>
          <Input value={value.placeOnShelf} onChange={(event) => onChange('placeOnShelf', event.target.value)} />
        </div>
        <div className="space-y-1.5">
          <Label>{t('books.fields.price')}</Label>
          <Input
            type="number"
            min={0}
            step="0.01"
            value={value.price}
            onChange={(event) => onChange('price', event.target.value)}
          />
        </div>
        <div className="space-y-1.5">
          <Label>{t('books.fields.dateAdd')}</Label>
          <DateInput value={value.dateAdd} onChange={(next) => onChange('dateAdd', next)} />
        </div>
        <div className="space-y-1.5">
          <Label>{t('books.fields.part')}</Label>
          <Input value={value.part} onChange={(event) => onChange('part', event.target.value)} />
        </div>
        <div className="space-y-1.5">
          <Label>{t('books.fields.issueNumber')}</Label>
          <Input value={value.issueNumber} onChange={(event) => onChange('issueNumber', event.target.value)} />
        </div>
      </div>

      <div className="space-y-1.5">
        <Label>{t('books.fields.notice')}</Label>
        <Textarea rows={2} value={value.notice} onChange={(event) => onChange('notice', event.target.value)} />
      </div>
    </>
  )
}
