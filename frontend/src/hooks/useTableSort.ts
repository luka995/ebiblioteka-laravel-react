import { useCallback } from 'react'
import { useSearchParams } from 'react-router-dom'

export type SortDirection = 'asc' | 'desc'

export interface TableSort {
  sort: string
  direction: SortDirection
  onSort: (column: string) => void
}

export function useTableSort(defaultSort: string, defaultDirection: SortDirection = 'asc'): TableSort {
  const [searchParams, setSearchParams] = useSearchParams()
  const sort = searchParams.get('sort') ?? defaultSort
  const requestedDirection = searchParams.get('direction')
  const direction: SortDirection = requestedDirection === 'desc'
    ? 'desc'
    : requestedDirection === 'asc'
      ? 'asc'
      : defaultDirection

  const onSort = useCallback((column: string) => {
    setSearchParams((previous) => {
      const next = new URLSearchParams(previous)
      const previousSort = previous.get('sort') ?? defaultSort
      const previousDirection = previous.get('direction') ?? defaultDirection
      const nextDirection = previousSort === column
        ? (previousDirection === 'asc' ? 'desc' : 'asc')
        : 'asc'

      next.set('sort', column)
      next.set('direction', nextDirection)
      next.set('page', '1')

      return next
    }, { replace: true })
  }, [defaultDirection, defaultSort, setSearchParams])

  return { sort, direction, onSort }
}
