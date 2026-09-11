import { useTranslation } from 'react-i18next'

export type BarcodePrintFormat = 'label' | 'a4'

interface BarcodePrintFormatPickerProps {
  value: BarcodePrintFormat
  onChange: (value: BarcodePrintFormat) => void
  name?: string
}

export function BarcodePrintFormatPicker({
  value,
  onChange,
  name = 'barcode-print-format',
}: BarcodePrintFormatPickerProps) {
  const { t } = useTranslation()

  const options: { value: BarcodePrintFormat; label: string; hint: string }[] = [
    {
      value: 'label',
      label: t('users.printBarcodeFormatLabel'),
      hint: t('users.printBarcodeFormatLabelHint'),
    },
    {
      value: 'a4',
      label: t('users.printBarcodeFormatA4'),
      hint: t('users.printBarcodeFormatA4Hint'),
    },
  ]

  return (
    <div className="space-y-3">
      {options.map((option) => (
        <label
          key={option.value}
          className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors ${
            value === option.value ? 'border-brand bg-brand-soft/40' : 'border-input hover:bg-muted/50'
          }`}
        >
          <input
            type="radio"
            name={name}
            value={option.value}
            checked={value === option.value}
            onChange={() => onChange(option.value)}
            className="mt-1 size-4 accent-brand"
          />
          <span className="space-y-0.5">
            <span className="block text-sm font-medium">{option.label}</span>
            <span className="block text-xs text-muted-foreground">{option.hint}</span>
          </span>
        </label>
      ))}
    </div>
  )
}
