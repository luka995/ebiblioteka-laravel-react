import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'

interface UserBarcodeProps {
  value: string | null
  svg: string | null | undefined
  children?: ReactNode
}

export function UserBarcode({ value, svg, children }: UserBarcodeProps) {
  const { t } = useTranslation()

  if (!value || !svg) return null

  const imageSource = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`

  return (
    <div className="flex w-full flex-col items-center gap-1.5 sm:ml-2 sm:w-44 sm:shrink-0 sm:border-l sm:pl-6">
      <div className="w-full rounded-md bg-white p-2 ring-1 ring-black/5">
        <img src={imageSource} alt={t('users.barcodeAlt')} className="h-14 w-full object-contain" />
      </div>
      <span className="font-mono text-xs tracking-[0.18em] text-muted-foreground">{value}</span>
      {children}
    </div>
  )
}
