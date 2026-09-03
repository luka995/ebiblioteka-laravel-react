import { BookOpen } from 'lucide-react'
import { cn } from '@/lib/utils'

export function BrandMark({ className }: { className?: string }) {
  return (
    <span
      className={cn(
        'inline-flex size-9 items-center justify-center rounded-lg bg-brand-accent text-brand shadow-sm',
        className,
      )}
    >
      <BookOpen className="size-5" strokeWidth={2.2} />
    </span>
  )
}
