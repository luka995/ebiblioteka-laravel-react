import { useDeferredValue, useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Search, UserPlus } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { PageLoader } from '@/components/ui/loader'
import type { ClassCandidate, PaginatedResponse } from '@/types'

interface AddClassStudentsModalProps {
  open: boolean
  onClose: () => void
  onSuccess: () => void
  classId: number
}

export function AddClassStudentsModal({
  open,
  onClose,
  onSuccess,
  classId,
}: AddClassStudentsModalProps) {
  const { t } = useTranslation()
  const [search, setSearch] = useState('')
  const deferredSearch = useDeferredValue(search)
  const [selected, setSelected] = useState<Set<number>>(new Set())
  const [isSubmitting, setIsSubmitting] = useState(false)

  const candidatesQuery = useQuery({
    queryKey: ['class-candidates', classId, deferredSearch],
    queryFn: () =>
      api.get<PaginatedResponse<ClassCandidate>>(apiPaths.classAvailableStudents(classId, deferredSearch)),
    enabled: open,
  })

  useEffect(() => {
    if (!open) return
    setSelected(new Set())
    setSearch('')
  }, [open, classId])

  const toggle = (userId: number) => {
    setSelected((current) => {
      const next = new Set(current)
      if (next.has(userId)) next.delete(userId)
      else next.add(userId)
      return next
    })
  }

  const submit = async () => {
    if (selected.size === 0) return
    setIsSubmitting(true)

    try {
      const result = await api.post<{
        assigned: number
        moved: number
        already_assigned: number
      }>(apiPaths.classAssignStudents(classId), {
        user_ids: Array.from(selected),
      })

      toast.success(t('classes.students.assigned'), {
        description: t('classes.students.assignedSummary', {
          assigned: result.assigned,
          moved: result.moved,
        }),
      })
      onSuccess()
      onClose()
    } catch (error) {
      toast.error(
        error instanceof ApiError
          ? (Object.values(error.validationErrors ?? {})[0]?.[0] ?? error.messageText ?? t('errors.unexpected'))
          : t('errors.unexpected'),
      )
    } finally {
      setIsSubmitting(false)
    }
  }

  const candidates = candidatesQuery.data?.data ?? []

  return (
    <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
      <DialogContent className="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle className="font-brand-heading flex items-center gap-2 text-xl">
            <UserPlus className="size-5 text-brand" />
            {t('classes.students.addTitle')}
          </DialogTitle>
          <DialogDescription>{t('classes.students.addSubtitle')}</DialogDescription>
        </DialogHeader>

        <div className="relative">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder={t('classes.students.searchPlaceholder')}
            className="pl-9"
          />
        </div>

        <div className="max-h-80 space-y-2 overflow-y-auto pr-1">
          {candidatesQuery.isLoading ? (
            <PageLoader />
          ) : candidates.length === 0 ? (
            <p className="py-6 text-center text-sm text-muted-foreground">{t('classes.students.noCandidates')}</p>
          ) : (
            candidates.map((candidate) => (
              <label
                key={candidate.id}
                htmlFor={`class-candidate-${candidate.id}`}
                className="flex cursor-pointer items-start gap-3 rounded-md border p-3 transition-colors hover:bg-muted/40"
              >
                <Checkbox
                  id={`class-candidate-${candidate.id}`}
                  checked={selected.has(candidate.id)}
                  onCheckedChange={() => toggle(candidate.id)}
                  className="mt-0.5"
                />
                <span className="min-w-0 flex-1">
                  <span className="block text-sm font-medium">{candidate.name}</span>
                  <span className="block truncate text-xs text-muted-foreground">
                    {candidate.email}
                    {candidate.username ? ` · ${candidate.username}` : ''}
                  </span>
                  {candidate.current_class ? (
                    <span className="mt-1 block text-xs text-amber-600 dark:text-amber-400">
                      {t('classes.students.currentClass', { name: candidate.current_class.name })}
                    </span>
                  ) : null}
                </span>
              </label>
            ))
          )}
        </div>

        <DialogFooter>
          <span className="mr-auto self-center text-sm text-muted-foreground">
            {t('classes.students.selectedCount', { count: selected.size })}
          </span>
          <Button type="button" variant="outline" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button
            type="button"
            variant="brand"
            disabled={selected.size === 0 || isSubmitting}
            onClick={() => void submit()}
          >
            {t('classes.students.assign')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
