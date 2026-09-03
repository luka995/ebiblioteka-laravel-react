import { cn } from '@/lib/utils'

export function Spinner({ className }: { className?: string }) {
  return (
    <span
      role="status"
      aria-label="Loading"
      className={cn('size-5 animate-spin rounded-full border-2 border-current border-t-transparent', className)}
    />
  )
}

export function PageLoader({ className }: { className?: string }) {
  return (
    <div className={cn('flex h-full min-h-40 items-center justify-center text-muted-foreground', className)}>
      <Spinner />
    </div>
  )
}
