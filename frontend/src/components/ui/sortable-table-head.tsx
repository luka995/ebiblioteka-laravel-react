import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react'
import { TableHead } from '@/components/ui/table'
import { cn } from '@/lib/utils'
import type { SortDirection } from '@/hooks/useTableSort'

interface SortableTableHeadProps {
  column: string
  sort: string
  direction: SortDirection
  onSort: (column: string) => void
  children: React.ReactNode
  className?: string
}

export function SortableTableHead({
  column,
  sort,
  direction,
  onSort,
  children,
  className,
}: SortableTableHeadProps) {
  const active = sort === column

  return (
    <TableHead
      className={className}
      aria-sort={active ? (direction === 'asc' ? 'ascending' : 'descending') : 'none'}
    >
      <button
        type="button"
        onClick={() => onSort(column)}
        className={cn(
          'inline-flex items-center gap-1 text-left font-medium transition-colors hover:text-foreground',
          active && 'text-foreground',
        )}
      >
        {children}
        {active
          ? direction === 'asc'
            ? <ArrowUp className="size-3.5" />
            : <ArrowDown className="size-3.5" />
          : <ChevronsUpDown className="size-3.5 opacity-40" />}
      </button>
    </TableHead>
  )
}
