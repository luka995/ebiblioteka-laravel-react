import logoUrl from '@/assets/ebiblioteka-logo.svg'
import { cn } from '@/lib/utils'

export function BrandMark({ className }: { className?: string }) {
  return (
    <img
      src={logoUrl}
      alt="eBiblioteka"
      className={cn('size-9 shrink-0 rounded-lg object-contain', className)}
    />
  )
}
