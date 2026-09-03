import { type ReactNode } from 'react'
import { cn } from '@/lib/utils'

export function FormItem({ className, children }: { className?: string; children: ReactNode }) {
  return <div className={cn('grid gap-2', className)}>{children}</div>
}

export function FieldError({ message }: { message?: string }) {
  if (!message) return null
  return <p className="text-sm font-medium text-destructive">{message}</p>
}

export function FieldHint({ children }: { children: ReactNode }) {
  return <p className="text-xs text-muted-foreground">{children}</p>
}
