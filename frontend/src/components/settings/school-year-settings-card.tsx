import { useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { CalendarDays, CheckCircle2, Pencil, Plus, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { SchoolYearFormModal } from '@/components/settings/school-year-form-modal'
import type { SchoolYear, SchoolYearsMeta } from '@/types'

interface SchoolYearsResponse {
  data: SchoolYear[]
  meta: SchoolYearsMeta
}

export function SchoolYearSettingsCard() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  const [formOpen, setFormOpen] = useState(false)
  const [editingYear, setEditingYear] = useState<SchoolYear | null>(null)
  const [deletingYear, setDeletingYear] = useState<SchoolYear | null>(null)
  const [activatingYear, setActivatingYear] = useState<SchoolYear | null>(null)

  const yearsQuery = useQuery({
    queryKey: ['school-years'],
    queryFn: () => api.get<SchoolYearsResponse>(apiPaths.schoolYears),
  })

  const years = yearsQuery.data?.data ?? []
  const meta = yearsQuery.data?.meta
  const activeYear = years.find((year) => year.id === meta?.active_school_year_id) ?? null
  const automaticActivation = meta?.automatic_activation ?? false

  const suggestedYear = useMemo(() => {
    const latest = years[0]
    return latest ? latest.year + 1 : new Date().getFullYear()
  }, [years])

  const errorMessage = (error: unknown): string => {
    if (!(error instanceof ApiError)) {
      return t('errors.unexpected')
    }

    const firstValidationError = error.validationErrors
      ? Object.values(error.validationErrors)[0]?.[0]
      : undefined

    return firstValidationError ?? error.messageText ?? t('errors.unexpected')
  }

  const deleteMutation = useMutation({
    mutationFn: (year: SchoolYear) => api.delete(apiPaths.schoolYear(year.id)),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['school-years'] })
      toast.success(t('settings.schoolYear.deleted'))
      setDeletingYear(null)
    },
    onError: (error) => toast.error(errorMessage(error)),
  })

  const activateMutation = useMutation({
    mutationFn: (year: SchoolYear) => api.post(apiPaths.schoolYearActivate(year.id)),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['school-years'] })
      toast.success(t('settings.schoolYear.activated'))
      setActivatingYear(null)
    },
    onError: (error) => toast.error(errorMessage(error)),
  })

  const automaticMutation = useMutation({
    mutationFn: (enabled: boolean) =>
      api.put(apiPaths.schoolYearAutomaticActivation, { automatic_activation: enabled }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['school-years'] })
      toast.success(t('settings.schoolYear.automaticUpdated'))
    },
    onError: (error) => toast.error(errorMessage(error)),
  })

  const openCreate = () => {
    setEditingYear(null)
    setFormOpen(true)
  }

  const openEdit = (year: SchoolYear) => {
    setEditingYear(year)
    setFormOpen(true)
  }

  const closeForm = () => {
    setFormOpen(false)
    setEditingYear(null)
  }

  const formSuccess = async () => {
    await queryClient.invalidateQueries({ queryKey: ['school-years'] })
    toast.success(
      editingYear
        ? t('settings.schoolYear.updated')
        : t('settings.schoolYear.created'),
    )
  }

  return (
    <Card>
      <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <CardTitle className="font-brand-heading flex items-center gap-2 text-base">
            <CalendarDays className="size-4 text-brand" />
            {t('settings.schoolYear.title')}
          </CardTitle>
          <CardDescription>{t('settings.schoolYear.description')}</CardDescription>
        </div>
        <Button type="button" variant="brand" size="sm" onClick={openCreate}>
          <Plus />
          {t('settings.schoolYear.newYear')}
        </Button>
      </CardHeader>

      <CardContent className="space-y-5">
        <div className="flex flex-wrap items-center gap-3 rounded-md border bg-muted/30 px-3 py-2 text-sm">
          <span className="font-medium">{t('settings.schoolYear.activeYear')}:</span>
          {activeYear ? (
            <Badge variant="secondary" className="gap-1">
              <CheckCircle2 className="size-3" />
              {activeYear.name}
            </Badge>
          ) : (
            <span className="text-muted-foreground">{t('settings.schoolYear.noActiveYear')}</span>
          )}
        </div>

        <div className="flex items-start gap-3 rounded-md border p-3">
          <Checkbox
            id="school-year-automatic-activation"
            checked={automaticActivation}
            disabled={automaticMutation.isPending}
            onCheckedChange={(checked) => automaticMutation.mutate(checked === true)}
          />
          <div className="space-y-1">
            <Label htmlFor="school-year-automatic-activation">
              {t('settings.schoolYear.autoActivation')}
            </Label>
            <p className="text-xs text-muted-foreground">
              {t('settings.schoolYear.autoActivationHint')}
            </p>
          </div>
        </div>

        {yearsQuery.isLoading ? (
          <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
        ) : years.length === 0 ? (
          <p className="text-sm text-muted-foreground">{t('settings.schoolYear.empty')}</p>
        ) : (
          <div className="overflow-x-auto rounded-md border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('settings.schoolYear.year')}</TableHead>
                  <TableHead>{t('settings.schoolYear.startsOn')}</TableHead>
                  <TableHead>{t('settings.schoolYear.endsOn')}</TableHead>
                  <TableHead>{t('settings.schoolYear.classes')}</TableHead>
                  <TableHead>{t('settings.schoolYear.status')}</TableHead>
                  <TableHead className="text-right">{t('common.actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {years.map((year) => (
                  <TableRow key={year.id} className={year.is_active ? 'bg-brand-soft/40' : undefined}>
                    <TableCell className="font-medium">{year.name}</TableCell>
                    <TableCell>{year.starts_on_formatted}</TableCell>
                    <TableCell>{year.ends_on_formatted}</TableCell>
                    <TableCell>{year.classes_count ?? 0}</TableCell>
                    <TableCell>
                      {year.is_active ? (
                        <Badge variant="secondary">{t('settings.schoolYear.active')}</Badge>
                      ) : (
                        <Badge variant="outline">{t('settings.schoolYear.inactive')}</Badge>
                      )}
                    </TableCell>
                    <TableCell>
                      <div className="flex justify-end gap-1">
                        {!year.is_active ? (
                          <Button
                            type="button"
                            variant="outline"
                            size="xs"
                            disabled={automaticActivation}
                            title={
                              automaticActivation
                                ? t('settings.schoolYear.manualDisabledByAuto')
                                : t('settings.schoolYear.activate')
                            }
                            onClick={() => setActivatingYear(year)}
                          >
                            <CheckCircle2 />
                            {t('settings.schoolYear.activate')}
                          </Button>
                        ) : null}
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon-xs"
                          aria-label={t('common.edit')}
                          onClick={() => openEdit(year)}
                        >
                          <Pencil />
                        </Button>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon-xs"
                          aria-label={t('common.delete')}
                          disabled={year.is_active || (year.classes_count ?? 0) > 0}
                          title={
                            year.is_active
                              ? t('settings.schoolYear.activeDeleteDisabled')
                              : (year.classes_count ?? 0) > 0
                                ? t('settings.schoolYear.usedDeleteDisabled')
                                : t('common.delete')
                          }
                          onClick={() => setDeletingYear(year)}
                        >
                          <Trash2 />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        )}
      </CardContent>

      <SchoolYearFormModal
        open={formOpen}
        onClose={closeForm}
        onSuccess={() => void formSuccess()}
        schoolYear={editingYear}
        suggestedYear={suggestedYear}
      />

      <AlertDialog open={Boolean(deletingYear)} onOpenChange={(value) => !value && setDeletingYear(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('settings.schoolYear.deleteTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('settings.schoolYear.deleteText', { name: deletingYear?.name ?? '' })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleteMutation.isPending}>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction
              variant="destructive"
              disabled={deleteMutation.isPending}
              onClick={() => deletingYear && deleteMutation.mutate(deletingYear)}
            >
              {t('common.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      <AlertDialog open={Boolean(activatingYear)} onOpenChange={(value) => !value && setActivatingYear(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('settings.schoolYear.activateTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('settings.schoolYear.activateText', { name: activatingYear?.name ?? '' })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={activateMutation.isPending}>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction
              disabled={activateMutation.isPending}
              onClick={() => activatingYear && activateMutation.mutate(activatingYear)}
            >
              {t('settings.schoolYear.activate')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </Card>
  )
}
