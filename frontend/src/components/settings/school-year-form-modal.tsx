import { useEffect, useMemo } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { DateInput } from '@/components/ui/date-input'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import type { SchoolYear } from '@/types'

interface SchoolYearFormModalProps {
  open: boolean
  onClose: () => void
  onSuccess: () => void
  schoolYear?: SchoolYear | null
  suggestedYear?: number
}

export function SchoolYearFormModal({
  open,
  onClose,
  onSuccess,
  schoolYear,
  suggestedYear,
}: SchoolYearFormModalProps) {
  const { t } = useTranslation()
  const editing = Boolean(schoolYear)

  const schema = useMemo(
    () =>
      z.object({
        year: z
          .string()
          .trim()
          .regex(/^\d{4}$/, t('settings.schoolYear.invalidYear')),
        starts_on: z.string().min(1, t('errors.required')),
        ends_on: z.string().min(1, t('errors.required')),
      }),
    [t],
  )

  type FormValues = z.infer<typeof schema>

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    mode: 'onTouched',
    defaultValues: { year: '', starts_on: '', ends_on: '' },
  })

  useEffect(() => {
    if (!open) return

    const year = schoolYear?.year ?? suggestedYear ?? new Date().getFullYear()

    form.reset({
      year: String(year),
      starts_on: schoolYear?.starts_on ?? `${year}-09-01`,
      ends_on: schoolYear?.ends_on ?? `${year + 1}-08-31`,
    })
  }, [open, schoolYear, suggestedYear, form])

  const handleYearChange = (value: string, onChange: (value: string) => void) => {
    onChange(value)

    if (editing || !/^\d{4}$/.test(value)) {
      return
    }

    const year = Number(value)
    form.setValue('starts_on', `${year}-09-01`, { shouldValidate: false, shouldDirty: true })
    form.setValue('ends_on', `${year + 1}-08-31`, { shouldValidate: false, shouldDirty: true })
  }

  const onSubmit = form.handleSubmit(async (values) => {
    const payload = {
      year: Number(values.year),
      starts_on: values.starts_on,
      ends_on: values.ends_on,
    }

    try {
      if (editing && schoolYear) {
        await api.put(apiPaths.schoolYear(schoolYear.id), payload)
      } else {
        await api.post(apiPaths.schoolYears, payload)
      }

      onSuccess()
      onClose()
    } catch (error) {
      if (error instanceof ApiError) {
        const validationErrors = error.validationErrors

        if (validationErrors) {
          let hasFieldError = false

          Object.entries(validationErrors).forEach(([field, messages]) => {
            if (field === 'year' || field === 'starts_on' || field === 'ends_on') {
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
            {editing ? t('settings.schoolYear.editTitle') : t('settings.schoolYear.formTitle')}
          </DialogTitle>
          <DialogDescription>
            {editing ? t('settings.schoolYear.editSubtitle') : t('settings.schoolYear.formSubtitle')}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={onSubmit} className="space-y-4" noValidate>
            <FormField
              control={form.control}
              name="year"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('settings.schoolYear.year')}</FormLabel>
                  <FormControl>
                    <Input
                      type="number"
                      min={2000}
                      max={2100}
                      placeholder="2026"
                      {...field}
                      onChange={(event) => handleYearChange(event.target.value, field.onChange)}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <div className="grid gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="starts_on"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t('settings.schoolYear.startsOn')}</FormLabel>
                    <FormControl>
                      <DateInput {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="ends_on"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t('settings.schoolYear.endsOn')}</FormLabel>
                    <FormControl>
                      <DateInput {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" onClick={onClose}>
                {t('common.cancel')}
              </Button>
              <Button type="submit" variant="brand" disabled={form.formState.isSubmitting}>
                {t('common.save')}
              </Button>
            </div>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  )
}
