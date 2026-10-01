import { useEffect, useRef } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { api, apiPaths } from '@/lib/api'
import type { BarcodePrintJob, BarcodePrintJobStatus, PaginatedResponse } from '@/types'

/** Interval osvezavanja dok postoji posao u toku. */
const POLL_INTERVAL_MS = 10_000

export function isBarcodePrintJobPending(job: BarcodePrintJob): boolean {
  return job.status === 'pending' || job.status === 'processing'
}

/**
 * Lista poslova za stampu bar-kodova sa automatskim polling-om na 10s.
 *
 * Polling se izvrsava samo dok je bar jedan posao u statusu `pending` ili
 * `processing`, i pauzira se kada je tab u pozadini. Kada posao pređe iz
 * nedovrsenog u `completed`/`failed`, prikazuje se toast notifikacija.
 */
export function useBarcodePrintJobStatus(libraryId: number | null) {
  const { t } = useTranslation()

  const query = useQuery({
    queryKey: ['barcode-print-jobs', libraryId],
    queryFn: () =>
      api.get<PaginatedResponse<BarcodePrintJob>>(`${apiPaths.barcodePrintJobs}?per_page=50`),
    enabled: libraryId !== null,
    refetchInterval: (q) => {
      const items = q.state.data?.data ?? []
      return items.some(isBarcodePrintJobPending) ? POLL_INTERVAL_MS : false
    },
    refetchIntervalInBackground: false,
  })

  const previousStatuses = useRef<Map<number, BarcodePrintJobStatus> | null>(null)

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

    if (completed) toast.success(t('books.barcodePrint.ready'))
    if (failed) toast.error(t('books.barcodePrint.failed'))
  }, [query.data, t])

  return query
}
