import { useEffect } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import type { BookImportStatus } from '@/types'

export function useBookImportPolling(importId: number, status: BookImportStatus | undefined): void {
  const queryClient = useQueryClient()

  useEffect(() => {
    if (status !== 'pending' && status !== 'processing') return

    const timer = window.setInterval(() => {
      void Promise.all([
        queryClient.invalidateQueries({ queryKey: ['book-import', importId] }),
        queryClient.invalidateQueries({ queryKey: ['book-import-rows', importId] }),
      ])
    }, 5000)

    return () => window.clearInterval(timer)
  }, [importId, queryClient, status])
}
