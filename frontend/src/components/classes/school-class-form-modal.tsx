import { useEffect, useMemo } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useAuth } from '@/hooks/useAuth'
import type { SchoolClass, SchoolClassOptions } from '@/types'

interface SchoolClassFormModalProps {
  open: boolean
  onClose: () => void
  onSuccess: () => void
  schoolClass?: SchoolClass | null
  options?: SchoolClassOptions
}

export function SchoolClassFormModal({
  open,
  onClose,
  onSuccess,
  schoolClass,
  options,
}: SchoolClassFormModalProps) {
  const { t } = useTranslation()
  const { user } = useAuth()
  const editing = Boolean(schoolClass)
  const schoolYears = options?.school_years ?? []
  const selectableYears = editing
    ? schoolYears
    : schoolYears.filter((year) => year.id === options?.active_school_year_id)
  const grades = options?.grades ?? []

  const schema = useMemo(
    () =>
      z.object({
        school_year_id: z.string().min(1, t('errors.required')),
        grade: z.string().min(1, t('errors.required')),
        name: z.string().trim().min(1, t('errors.required')).max(50),
        teacher_name: z.string().max(255),
        is_active: z.boolean(),
      }),
    [t],
  )

  type FormValues = z.infer<typeof schema>

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    mode: 'onTouched',
    defaultValues: {
      school_year_id: '',
      grade: '1',
      name: '',
      teacher_name: '',
      is_active: true,
    },
  })

  useEffect(() => {
    if (!open) return

    const defaultYearId = options?.active_school_year_id

    form.reset({
      school_year_id: schoolClass
        ? String(schoolClass.school_year_id)
        : defaultYearId
          ? String(defaultYearId)
          : '',
      grade: schoolClass?.grade ? String(schoolClass.grade) : '1',
      name: schoolClass?.name ?? '',
      teacher_name: schoolClass?.teacher_name ?? '',
      is_active: schoolClass?.is_active ?? true,
    })
  }, [open, schoolClass, options, form])

  const onSubmit = form.handleSubmit(async (values) => {
    const payload = {
      school_year_id: Number(values.school_year_id),
      grade: Number(values.grade),
      name: values.name,
      teacher_name: values.teacher_name.trim() === '' ? null : values.teacher_name,
      is_active: values.is_active,
    }

    try {
      if (editing && schoolClass) {
        await api.put(apiPaths.schoolClass(schoolClass.id), payload)
      } else {
        await api.post(apiPaths.schoolClasses, payload)
      }

      onSuccess()
      onClose()
    } catch (error) {
      if (error instanceof ApiError) {
        const validationErrors = error.validationErrors

        if (validationErrors) {
          let hasFieldError = false

          Object.entries(validationErrors).forEach(([field, messages]) => {
            if (field === 'school_year_id' || field === 'grade' || field === 'name' || field === 'teacher_name') {
              form.setError(field, { message: messages[0] })
              hasFieldError = true
            }
          })

          if (hasFieldError) {
            return
          }
        }

        toast.error(error.messageText ?? t('errors.unexpected'))
        return
      }

      toast.error(t('errors.unexpected'))
    }
  })

  return (
    <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle className="font-brand-heading text-xl">
            {editing ? t('classes.editTitle') : t('classes.formTitle')}
          </DialogTitle>
          <DialogDescription>
            {editing ? t('classes.editSubtitle') : t('classes.formSubtitle')}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={onSubmit} className="space-y-4" noValidate>
            <FormField
              control={form.control}
              name="school_year_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('classes.fields.schoolYear')}</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange} disabled={editing && !user?.is_super_admin}>
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue placeholder={t('classes.fields.schoolYearPlaceholder')} />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {selectableYears.map((year) => (
                        <SelectItem key={year.id} value={String(year.id)}>
                          {year.name}
                          {year.is_active ? ` · ${t('classes.fields.activeYear')}` : ''}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {!editing && options && !options.active_school_year_id && (
                    <p className="text-sm text-destructive">{t('classes.activeSchoolYearRequired')}</p>
                  )}
                  <FormMessage />
                </FormItem>
              )}
            />

            <div className="grid gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="grade"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t('classes.fields.grade')}</FormLabel>
                    <Select value={field.value} onValueChange={field.onChange}>
                      <FormControl>
                        <SelectTrigger className="w-full">
                          <SelectValue placeholder={t('classes.fields.gradePlaceholder')} />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {grades.map((grade) => (
                          <SelectItem key={grade.value} value={String(grade.value)}>
                            {grade.full_label}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t('classes.fields.name')}</FormLabel>
                    <FormControl>
                      <Input placeholder="1-1" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <FormField
              control={form.control}
              name="teacher_name"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('classes.fields.teacher')}</FormLabel>
                  <FormControl>
                    <Input placeholder={t('classes.fields.teacherPlaceholder')} {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="is_active"
              render={({ field }) => (
                <FormItem className="flex flex-row items-center gap-3 space-y-0 rounded-md border p-3">
                  <FormControl>
                    <Checkbox
                      checked={field.value}
                      onCheckedChange={(checked) => field.onChange(checked === true)}
                    />
                  </FormControl>
                  <div className="space-y-1">
                    <FormLabel>{t('classes.fields.active')}</FormLabel>
                    <p className="text-xs text-muted-foreground">{t('classes.fields.activeHint')}</p>
                  </div>
                </FormItem>
              )}
            />

            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" onClick={onClose}>
                {t('common.cancel')}
              </Button>
              <Button type="submit" variant="brand" disabled={form.formState.isSubmitting || schoolYears.length === 0 || (!editing && !options?.active_school_year_id)}>
                {t('common.save')}
              </Button>
            </div>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  )
}
