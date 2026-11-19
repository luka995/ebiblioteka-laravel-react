import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { ArrowLeft, GraduationCap, Pencil, Trash2, UserPlus, Users } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { PageLoader } from '@/components/ui/loader'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { AddClassStudentsModal } from '@/components/classes/add-class-students-modal'
import { SchoolClassFormModal } from '@/components/classes/school-class-form-modal'
import { useAuth } from '@/hooks/useAuth'
import type { ClassStudent, SchoolClass, SchoolClassOptions } from '@/types'

export function ClassDetailPage() {
  const { t } = useTranslation()
  const { id } = useParams()
  const classId = Number(id)
  const queryClient = useQueryClient()
  const { can, activeLibrary } = useAuth()

  const [editOpen, setEditOpen] = useState(false)
  const [addOpen, setAddOpen] = useState(false)
  const [removingStudent, setRemovingStudent] = useState<ClassStudent | null>(null)
  const [isRemoving, setIsRemoving] = useState(false)

  const classQuery = useQuery({
    queryKey: ['school-class', classId],
    queryFn: () => api.get<{ data: SchoolClass }>(apiPaths.schoolClass(classId)),
    enabled: Number.isFinite(classId) && classId > 0,
  })

  const studentsQuery = useQuery({
    queryKey: ['school-class-students', classId],
    queryFn: () => api.get<{ data: ClassStudent[] }>(apiPaths.classStudents(classId)),
    enabled: Number.isFinite(classId) && classId > 0,
  })

  const optionsQuery = useQuery({
    queryKey: ['school-class-options'],
    queryFn: () => api.get<{ data: SchoolClassOptions }>(apiPaths.schoolClassOptions),
  })

  const schoolClass = classQuery.data?.data
  const students = studentsQuery.data?.data ?? []

  if (!can('classes.viewAny')) {
    return <p className="text-sm text-muted-foreground">{t('errors.forbidden')}</p>
  }

  if (!activeLibrary) {
    return <p className="text-sm text-muted-foreground">{t('classes.activeLibraryRequired')}</p>
  }

  if (classQuery.isLoading) {
    return <PageLoader />
  }

  if (classQuery.isError || !schoolClass) {
    return (
      <div className="space-y-4">
        <Button asChild variant="outline" size="sm">
          <Link to="/classes">
            <ArrowLeft />
            {t('common.back')}
          </Link>
        </Button>
        <p className="text-sm text-muted-foreground">{t('classes.notFound')}</p>
      </div>
    )
  }

  const refreshClass = async () => {
    await queryClient.invalidateQueries({ queryKey: ['school-class', classId] })
    await queryClient.invalidateQueries({ queryKey: ['school-class-students', classId] })
    await queryClient.invalidateQueries({ queryKey: ['school-classes'] })
  }

  const classFormSuccess = async () => {
    await refreshClass()
    toast.success(t('classes.updated'))
  }

  const confirmRemove = async () => {
    if (!removingStudent) return
    setIsRemoving(true)

    try {
      await api.post(apiPaths.classUnassignStudents(classId), {
        user_ids: [removingStudent.user_id],
      })
      await refreshClass()
      toast.success(t('classes.students.removed'), { description: removingStudent.name ?? '' })
      setRemovingStudent(null)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.messageText ?? t('errors.unexpected') : t('errors.unexpected'))
    } finally {
      setIsRemoving(false)
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <Button asChild variant="outline" size="sm">
          <Link to="/classes">
            <ArrowLeft />
            {t('common.back')}
          </Link>
        </Button>
      </div>

      <Card className="border-brand-accent/30 bg-brand-accent/25">
        <CardHeader className="gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div className="space-y-2">
            <CardTitle className="font-brand-heading flex items-center gap-2 text-2xl text-brand">
              <GraduationCap className="size-6 text-brand" />
              {schoolClass.name}
            </CardTitle>
            <div className="flex flex-wrap items-center gap-2 text-sm text-brand/70">
              <Badge variant="outline">{schoolClass.grade_full_label ?? schoolClass.grade_label}</Badge>
              <Badge variant="outline">{schoolClass.school_year_name}</Badge>
              {schoolClass.is_active ? (
                <Badge variant="secondary">{t('classes.active')}</Badge>
              ) : (
                <Badge variant="outline">{t('classes.inactive')}</Badge>
              )}
              <span className="inline-flex items-center gap-1.5">
                <Users className="size-3.5" />
                {t('classes.students.count', { count: schoolClass.students_count ?? students.length })}
              </span>
            </div>
            <p className="text-sm text-brand/70">
              {schoolClass.teacher_name
                ? t('classes.teacherValue', { name: schoolClass.teacher_name })
                : t('classes.noTeacher')}
            </p>
          </div>

          <div className="flex flex-wrap gap-2">
            {schoolClass.can?.update ? (
              <Button type="button" variant="outline" onClick={() => setEditOpen(true)}>
                <Pencil />
                {t('common.edit')}
              </Button>
            ) : null}
            {schoolClass.can?.assignStudents ? (
              <Button type="button" variant="brand" onClick={() => setAddOpen(true)}>
                <UserPlus />
                {t('classes.students.add')}
              </Button>
            ) : null}
          </div>
        </CardHeader>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="font-brand-heading text-base">{t('classes.students.title')}</CardTitle>
        </CardHeader>
        <CardContent>
          {studentsQuery.isLoading ? (
            <PageLoader />
          ) : students.length === 0 ? (
            <div className="flex flex-col items-center gap-2 py-10 text-center">
              <Users className="size-8 text-muted-foreground" />
              <p className="font-medium">{t('classes.students.emptyTitle')}</p>
              <p className="max-w-md text-sm text-muted-foreground">{t('classes.students.emptyText')}</p>
            </div>
          ) : (
            <div className="overflow-x-auto rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('classes.students.columns.name')}</TableHead>
                    <TableHead>{t('classes.students.columns.account')}</TableHead>
                    <TableHead>{t('classes.students.columns.barcode')}</TableHead>
                    <TableHead>{t('classes.students.columns.assignedAt')}</TableHead>
                    <TableHead className="text-right">{t('common.actions')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {students.map((student) => (
                    <TableRow key={student.id}>
                      <TableCell className="font-medium">{student.name ?? '—'}</TableCell>
                      <TableCell>
                        <span className="block">{student.email ?? '—'}</span>
                        {student.username ? (
                          <span className="block text-xs text-muted-foreground">{student.username}</span>
                        ) : null}
                      </TableCell>
                      <TableCell>{student.bar_code ?? '—'}</TableCell>
                      <TableCell>
                        <span className="block">{student.assigned_at_formatted ?? '—'}</span>
                        {student.assigned_by ? (
                          <span className="block text-xs text-muted-foreground">{student.assigned_by}</span>
                        ) : null}
                      </TableCell>
                      <TableCell>
                        <div className="flex justify-end">
                          {schoolClass.can?.unassignStudents ? (
                            <Button
                              type="button"
                              variant="ghost"
                              size="icon-xs"
                              aria-label={t('classes.students.remove')}
                              onClick={() => setRemovingStudent(student)}
                            >
                              <Trash2 />
                            </Button>
                          ) : null}
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>

      <SchoolClassFormModal
        open={editOpen}
        onClose={() => setEditOpen(false)}
        onSuccess={() => void classFormSuccess()}
        schoolClass={schoolClass}
        options={optionsQuery.data?.data}
      />

      <AddClassStudentsModal
        open={addOpen}
        onClose={() => setAddOpen(false)}
        onSuccess={() => void refreshClass()}
        classId={classId}
      />

      <AlertDialog open={Boolean(removingStudent)} onOpenChange={(value) => !value && setRemovingStudent(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('classes.students.removeTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('classes.students.removeText', { name: removingStudent?.name ?? '' })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={isRemoving}>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction variant="destructive" disabled={isRemoving} onClick={() => void confirmRemove()}>
              {t('classes.students.remove')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
