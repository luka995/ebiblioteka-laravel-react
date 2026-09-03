import { useTranslation } from 'react-i18next'
import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from '@/components/ui/pagination'
import { cn } from '@/lib/utils'

function buildPageItems(current: number, total: number): Array<number | 'start' | 'end'> {
  if (total <= 7) {
    return Array.from({ length: total }, (_, index) => index + 1)
  }

  const items: Array<number | 'start' | 'end'> = [1]
  const left = Math.max(2, current - 2)
  const right = Math.min(total - 1, current + 2)

  if (left > 2) items.push('start')
  for (let index = left; index <= right; index += 1) items.push(index)
  if (right < total - 1) items.push('end')
  items.push(total)

  return items
}

interface PaginationBarProps {
  currentPage: number
  lastPage: number
  total: number
  perPage: number
  onPageChange: (page: number) => void
  className?: string
}

export function PaginationBar({
  currentPage,
  lastPage,
  total,
  perPage,
  onPageChange,
  className,
}: PaginationBarProps) {
  const { t } = useTranslation()
  const from = total === 0 ? 0 : (currentPage - 1) * perPage + 1
  const to = Math.min(currentPage * perPage, total)

  return (
    <div
      className={cn(
        'flex flex-col gap-3 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between',
        className,
      )}
    >
      <p className="text-sm text-muted-foreground">
        {t('pagination.showing', { from, to, total })}
      </p>

      <Pagination className="w-auto justify-end">
        <PaginationContent>
          <PaginationItem>
            <PaginationPrevious
              aria-label={t('pagination.previous')}
              onClick={() => currentPage > 1 && onPageChange(currentPage - 1)}
              className={cn(currentPage <= 1 && 'pointer-events-none opacity-50')}
            />
          </PaginationItem>

          {buildPageItems(currentPage, lastPage).map((item, index) => {
            if (item === 'start' || item === 'end') {
              return (
                <PaginationItem key={`${item}-${index}`}>
                  <PaginationEllipsis />
                </PaginationItem>
              )
            }

            return (
              <PaginationItem key={item}>
                <PaginationLink
                  isActive={item === currentPage}
                  onClick={() => item !== currentPage && onPageChange(item)}
                >
                  {item}
                </PaginationLink>
              </PaginationItem>
            )
          })}

          <PaginationItem>
            <PaginationNext
              aria-label={t('pagination.next')}
              onClick={() => currentPage < lastPage && onPageChange(currentPage + 1)}
              className={cn(currentPage >= lastPage && 'pointer-events-none opacity-50')}
            />
          </PaginationItem>
        </PaginationContent>
      </Pagination>
    </div>
  )
}
