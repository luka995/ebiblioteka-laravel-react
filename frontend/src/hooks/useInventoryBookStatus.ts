import { useEffect, useRef } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { api, apiPaths } from '@/lib/api'
import type { InventoryBook, InventoryBookStatus, PaginatedResponse } from '@/types'

/** Interval osvezavanja dok postoji zahtev u toku. */
const POLL_INTERVAL_MS = 10_000

export function isInventoryBookPending(book: InventoryBook): boolean {
  return book.status === 'pending' || book.status === 'processing'
}

/**
 * Lista inventarnih knjiga sa automatskim polling-om na 10s.
 *
 * Polling se izvrsava samo dok je bar jedan zahtev u statusu `pending` ili
 * `processing`, i pauzira se kada je tab u pozadini. Kada zahtev pređe iz
 * nedovrsenog u `completed`/`failed`, prikazuje se toast notifikacija.
 */
export function useInventoryBookStatus(libraryId: number | null) {
  const { t } = useTranslation()

  const query = useQuery({
    queryKey: ['inventory-books', libraryId],
    queryFn: () =>
      api.get<PaginatedResponse<InventoryBook>>(`${apiPaths.inventoryBooks}?per_page=50`),
    enabled: libraryId !== null,
    refetchInterval: (q) => {
      const items = q.state.data?.data ?? []
      return items.some(isInventoryBookPending) ? POLL_INTERVAL_MS : false
    },
    refetchIntervalInBackground: false,
  })

  const previousStatuses = useRef<Map<number, InventoryBookStatus> | null>(null)

  useEffect(() => {
    previousStatuses.current = null
  }, [libraryId])

  useEffect(() => {
    const items = query.data?.data
    if (!items) return

    // Prvi prolaz samo zapamti stanje da se ne prikazuje toast za vec gotove.
    if (previousStatuses.current === null) {
      previousStatuses.current = new Map(items.map((item) => [item.id, item.status]))
      return
    }

    const previous = previousStatuses.current
    let completed = false
    let failed = false

    for (const item of items) {
      const before = previous.get(item.id)

      if (item.status === 'completed' && before !== undefined && before !== 'completed') {
        completed = true
      }
      if (item.status === 'failed' && before !== undefined && before !== 'failed') {
        failed = true
      }

      previous.set(item.id, item.status)
    }

    if (completed) toast.success(t('books.inventory.ready'))
    if (failed) toast.error(t('books.inventory.failed'))
  }, [query.data, t])

  return query
}
