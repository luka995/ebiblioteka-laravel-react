import { useEffect, useState, type FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { GraduationCap, Pencil, Plus, Search, Trash2, Users, ArrowLeftRight } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardDescription, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { PageLoader } from '@/components/ui/loader'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { PaginationBar } from '@/components/pagination-bar'
import { SchoolClassFormModal } from '@/components/classes/school-class-form-modal'
import { useAuth } from '@/hooks/useAuth'
import type { PaginatedResponse, SchoolClass, SchoolClassOptions } from '@/types'

const PAGE_SIZE = 10

function useClassesQuery(page: number, schoolYearId: string, grade: string, search: string) {
  return useQuery({
    queryKey: ['school-classes', { page, schoolYearId, grade, search }],
    queryFn: async () => {
      const query = new URLSearchParams({
        per_page: String(PAGE_SIZE),
        page: String(page),
      })

      if (schoolYearId) query.set('school_year_id', schoolYearId)
      if (grade) query.set('grade', grade)
      if (search) query.set('search', search)

      return api.get<PaginatedResponse<SchoolClass>>(`${apiPaths.schoolClasses}?${query.toString()}`)
    },
  })
}

export function ClassesPage() {
  const { t } = useTranslation()
  const { can, activeLibrary } = useAuth()
  const [searchParams, setSearchParams] = useSearchParams()

  const page = Math.max(1, Number(searchParams.get('page')) || 1)
  const search = searchParams.get('q') ?? ''
  const grade = searchParams.get('grade') ?? ''
  const [searchInput, setSearchInput] = useState(search)
  const [formOpen, setFormOpen] = useState(false)
  const [editingClass, setEditingClass] = useState<SchoolClass | null>(null)
  const [deletingClass, setDeletingClass] = useState<SchoolClass | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)

  const optionsQuery = useQuery({
    queryKey: ['school-class-options'],
    queryFn: () => api.get<{ data: SchoolClassOptions }>(apiPaths.schoolClassOptions),
  })

  const options = optionsQuery.data?.data
  const schoolYearParam = searchParams.get('school_year_id')
  const schoolYearId =
    schoolYearParam === 'all'
      ? ''
      : schoolYearParam ??
        (options?.active_school_year_id ? String(options.active_school_year_id) : '')

  const classesQuery = useClassesQuery(page, schoolYearId, grade, search)

  useEffect(() => {
    setSearchInput(search)
  }, [search])

  if (!can('classes.viewAny')) {
    return <p className="text-sm text-muted-foreground">{t('errors.forbidden')}</p>
  }

  if (!activeLibrary) {
    return <p className="text-sm text-muted-foreground">{t('classes.activeLibraryRequired')}</p>
  }

  const setParams = (patch: Record<string, string | number | null>) => {
    setSearchParams(
      (previous) => {
        const next = new URLSearchParams(previous)
        Object.entries(patch).forEach(([key, value]) => {
          if (value === null || value === '') next.delete(key)
          else next.set(key, String(value))
        })
        return next
      },
      { replace: true },
    )
  }

  const submitSearch = (event: FormEvent) => {
    event.preventDefault()
    setParams({ q: searchInput.trim() || null, page: null })
  }

  const openCreate = () => {
    setEditingClass(null)
    setFormOpen(true)
  }

  const openEdit = (schoolClass: SchoolClass) => {
    setEditingClass(schoolClass)
    setFormOpen(true)
  }

  const closeForm = () => {
    setFormOpen(false)
    setEditingClass(null)
  }

  const formSuccess = async () => {
    await classesQuery.refetch()
    toast.success(editingClass ? t('classes.updated') : t('classes.created'))
  }

  const confirmDelete = async () => {
    if (!deletingClass) return
    setIsDeleting(true)

    try {
      await api.delete(apiPaths.schoolClass(deletingClass.id))
      const nextPage =
        classesQuery.data && classesQuery.data.data.length === 1 && page > 1 ? page - 1 : page
      setParams({ page: nextPage === page ? page : nextPage })
      await classesQuery.refetch()
      toast.success(t('classes.deleted'), { description: deletingClass.name })
      setDeletingClass(null)
    } catch (error) {
      toast.error(
        error instanceof ApiError
          ? (Object.values(error.validationErrors ?? {})[0]?.[0] ?? error.messageText ?? t('errors.unexpected'))
          : t('errors.unexpected'),
      )
    } finally {
      setIsDeleting(false)
    }
  }

  const canCreate = can('classes.create') && Boolean(options?.active_school_year_id)
  const activeYear = options?.school_years.find((year) => year.is_active)
  const previousYear = activeYear?.previous_school_year_id
    ? options?.school_years.find((year) => year.id === activeYear.previous_school_year_id)
    : undefined
  const showTransferSuggestion = Boolean(
    activeYear
    && activeYear.classes_count === 0
    && previousYear
    && previousYear.classes_count > 0,
  )

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h2 className="font-brand-heading flex items-center gap-2 text-2xl font-bold tracking-tight">
            <GraduationCap className="size-6 text-brand" />
            {t('classes.title')}
          </h2>
          <p className="mt-1 text-sm text-muted-foreground">{t('classes.subtitle')}</p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {can('classes.transferStudents') ? (
            <Button asChild variant="outline">
              <Link to="/classes/transfer">
                <ArrowLeftRight />
                {t('classes.transfer.open')}
              </Link>
            </Button>
          ) : null}
          {canCreate ? (
            <Button type="button" variant="brand" onClick={openCreate}>
              <Plus />
              {t('classes.add')}
            </Button>
          ) : null}
        </div>
      </div>

      <div className="flex flex-col gap-3 rounded-lg border bg-card p-4 sm:flex-row sm:items-center">
        <form onSubmit={submitSearch} className="flex flex-1 gap-2">
          <div className="relative flex-1">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              placeholder={t('classes.searchPlaceholder')}
              className="pl-9"
            />
          </div>
          <Button type="submit" variant="outline" aria-label={t('common.search')}>
            <Search />
          </Button>
        </form>

        <div className="flex flex-col gap-2 sm:flex-row">
          <Select
            value={schoolYearId || 'all'}
            onValueChange={(value) => setParams({ school_year_id: value, page: null })}
          >
            <SelectTrigger className="w-full sm:w-44">
              <SelectValue placeholder={t('classes.filters.schoolYear')} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{t('classes.filters.allYears')}</SelectItem>
              {(options?.school_years ?? []).map((year) => (
                <SelectItem key={year.id} value={String(year.id)}>
                  {year.name}
                  {year.is_active ? ` · ${t('classes.fields.activeYear')}` : ''}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          <Select
            value={grade || 'all'}
            onValueChange={(value) => setParams({ grade: value === 'all' ? null : value, page: null })}
          >
            <SelectTrigger className="w-full sm:w-36">
              <SelectValue placeholder={t('classes.filters.grade')} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{t('classes.filters.allGrades')}</SelectItem>
              {(options?.grades ?? []).map((item) => (
                <SelectItem key={item.value} value={String(item.value)}>
                  {item.full_label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      {showTransferSuggestion && previousYear ? (
        <div className="flex flex-col gap-3 rounded-lg border border-brand-accent/40 bg-brand-accent/25 p-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-start gap-3">
            <ArrowLeftRight className="mt-0.5 size-5 shrink-0 text-brand" />
            <div>
              <p className="font-medium text-brand">{t('classes.transfer.bannerTitle')}</p>
              <p className="text-sm text-brand/70">
                {t('classes.transfer.bannerText', { year: previousYear.name })}
              </p>
            </div>
          </div>
          <Button asChild variant="brand">
            <Link to={`/classes/transfer?source_school_year_id=${previousYear.id}`}>
              {t('classes.transfer.bannerAction')}
            </Link>
          </Button>
        </div>
      ) : null}

      <div className="space-y-4">
        {classesQuery.isLoading ? (
          <div className="rounded-lg border bg-card py-10">
            <PageLoader />
          </div>
        ) : (classesQuery.data?.data.length ?? 0) === 0 ? (
          <div className="flex flex-col items-center gap-2 rounded-lg border bg-card px-6 py-16 text-center">
            <GraduationCap className="size-8 text-muted-foreground" />
            <p className="font-medium">{t('classes.emptyTitle')}</p>
            <p className="max-w-md text-sm text-muted-foreground">{t('classes.emptyText')}</p>
          </div>
        ) : (
          <>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
              {(classesQuery.data?.data ?? []).map((schoolClass) => (
                <Card
                  key={schoolClass.id}
                  className="group h-full gap-0 overflow-hidden py-0 transition-colors hover:border-brand-accent"
                >
                  <Link to={`/classes/${schoolClass.id}`} className="flex flex-1 flex-col">
                    <div className="border-b border-brand-accent/30 bg-brand-accent/25 px-5 py-4">
                      <div className="flex items-start justify-between gap-3">
                        <span className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-brand text-brand-foreground shadow-sm">
                          <GraduationCap className="size-5" />
                        </span>
                        {schoolClass.is_active ? (
                          <Badge variant="secondary">{t('classes.active')}</Badge>
                        ) : (
                          <Badge variant="outline">{t('classes.inactive')}</Badge>
                        )}
                      </div>

                      <div className="mt-3 space-y-1">
                        <CardTitle className="font-brand-heading text-lg text-brand transition-colors group-hover:text-brand/80">
                          {schoolClass.name}
                        </CardTitle>
                        <CardDescription className="text-brand/70">
                          {schoolClass.grade_full_label ?? schoolClass.grade_label ?? '—'}
                          {' · '}
                          {schoolClass.school_year_name ?? '—'}
                        </CardDescription>
                      </div>
                    </div>

                    <div className="flex flex-1 flex-col gap-4 p-5">
                      <div className="mt-auto flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-muted-foreground">
                        <span className="inline-flex items-center gap-1.5">
                          <Users className="size-3.5" />
                          {schoolClass.students_count ?? 0}
                        </span>
                        <span className="truncate">
                          {schoolClass.teacher_name ?? t('classes.noTeacher')}
                        </span>
                      </div>
                    </div>
                  </Link>

                  {schoolClass.can?.update || schoolClass.can?.delete ? (
                    <div className="flex justify-end gap-1 border-t px-4 py-2">
                      {schoolClass.can?.update ? (
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon-xs"
                          aria-label={t('common.edit')}
                          onClick={() => openEdit(schoolClass)}
                        >
                          <Pencil />
                        </Button>
                      ) : null}
                      {schoolClass.can?.delete ? (
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon-xs"
                          aria-label={t('common.delete')}
                          disabled={(schoolClass.students_count ?? 0) > 0}
                          title={
                            (schoolClass.students_count ?? 0) > 0
                              ? t('classes.deleteDisabledStudents')
                              : t('common.delete')
                          }
                          onClick={() => setDeletingClass(schoolClass)}
                        >
                          <Trash2 />
                        </Button>
                      ) : null}
                    </div>
                  ) : null}
                </Card>
              ))}
            </div>

            {classesQuery.data ? (
              <PaginationBar
                currentPage={classesQuery.data.meta.current_page}
                lastPage={classesQuery.data.meta.last_page}
                total={classesQuery.data.meta.total}
                perPage={classesQuery.data.meta.per_page}
                onPageChange={(nextPage) => setParams({ page: nextPage })}
                className="rounded-lg border bg-card"
              />
            ) : null}
          </>
        )}
      </div>

      <SchoolClassFormModal
        open={formOpen}
        onClose={closeForm}
        onSuccess={() => void formSuccess()}
        schoolClass={editingClass}
        options={options}
      />

      <AlertDialog open={Boolean(deletingClass)} onOpenChange={(value) => !value && setDeletingClass(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('classes.deleteTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('classes.deleteText', { name: deletingClass?.name ?? '' })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={isDeleting}>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction variant="destructive" disabled={isDeleting} onClick={() => void confirmDelete()}>
              {t('common.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
