import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import type { Library } from '@/types'

interface LibrarySettingsModalProps {
  open: boolean
  onClose: () => void
  library: Library
  onSuccess: () => void
}

export function LibrarySettingsModal({ open, onClose, library, onSuccess }: LibrarySettingsModalProps) {
  const { t } = useTranslation()

  const schema = z.object({
    reservation_hours: z
      .string()
      .trim()
      .min(1, t('errors.required'))
      .refine((value) => {
        const hours = Number(value)

        return Number.isInteger(hours) && hours >= 1 && hours <= 720
      }, t('libs.settings.invalidHours')),
    lend_days: z
      .string()
      .trim()
      .min(1, t('errors.required'))
      .refine((value) => {
        const days = Number(value)

        return Number.isInteger(days) && days >= 1 && days <= 365
      }, t('libs.settings.invalidLendDays')),
    overdue_price_per_day: z
      .string()
      .trim()
      .min(1, t('errors.required'))
      .refine((value) => {
        const price = Number(value.replace(',', '.'))

        return Number.isFinite(price) && price >= 0 && price <= 100000
      }, t('libs.settings.invalidOverduePrice')),
    reservation_reminder_hours: z
      .string()
      .trim()
      .min(1, t('errors.required'))
      .refine((value) => {
        const hours = Number(value)

        return Number.isInteger(hours) && hours >= 1 && hours <= 720
      }, t('libs.settings.invalidHours')),
    loan_reminder_days: z
      .string()
      .trim()
      .min(1, t('errors.required'))
      .refine((value) => {
        const days = Number(value)

        return Number.isInteger(days) && days >= 1 && days <= 365
      }, t('libs.settings.invalidLendDays')),
    overdue_reminder_days: z
      .string()
      .trim()
      .min(1, t('errors.required'))
      .refine((value) => {
        const days = Number(value)

        return Number.isInteger(days) && days >= 1 && days <= 365
      }, t('libs.settings.invalidLendDays')),
    notify_staff_on_reservation: z.boolean(),
    notify_staff_on_issue: z.boolean(),
    notify_staff_on_return: z.boolean(),
    notify_staff_on_cancellation: z.boolean(),
    notify_staff_daily_digest: z.boolean(),
    notify_user_reservation_expiry: z.boolean(),
    notify_user_loan_due: z.boolean(),
  })

  type FormValues = z.infer<typeof schema>

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    mode: 'onTouched',
    defaultValues: {
      reservation_hours: '48',
      reservation_reminder_hours: '24',
      lend_days: '30',
      loan_reminder_days: '3',
      overdue_reminder_days: '3',
      overdue_price_per_day: '0',
      notify_staff_on_reservation: true,
      notify_staff_on_issue: false,
      notify_staff_on_return: false,
      notify_staff_on_cancellation: true,
      notify_staff_daily_digest: true,
      notify_user_reservation_expiry: true,
      notify_user_loan_due: true,
    },
  })

  useEffect(() => {
    if (!open) return
    form.reset({
      reservation_hours: String(library.settings?.reservation_hours ?? 48),
      reservation_reminder_hours: String(library.settings?.reservation_reminder_hours ?? 24),
      lend_days: String(library.settings?.lend_days ?? 30),
      loan_reminder_days: String(library.settings?.loan_reminder_days ?? 3),
      overdue_reminder_days: String(library.settings?.overdue_reminder_days ?? 3),
      overdue_price_per_day: String(library.settings?.overdue_price_per_day ?? 0),
      notify_staff_on_reservation: library.settings?.notify_staff_on_reservation ?? true,
      notify_staff_on_issue: library.settings?.notify_staff_on_issue ?? false,
      notify_staff_on_return: library.settings?.notify_staff_on_return ?? false,
      notify_staff_on_cancellation: library.settings?.notify_staff_on_cancellation ?? true,
      notify_staff_daily_digest: library.settings?.notify_staff_daily_digest ?? true,
      notify_user_reservation_expiry: library.settings?.notify_user_reservation_expiry ?? true,
      notify_user_loan_due: library.settings?.notify_user_loan_due ?? true,
    })
  }, [open, library, form]) // eslint-disable-line react-hooks/exhaustive-deps

  const onSubmit = form.handleSubmit(async (values) => {
    try {
      await api.put(apiPaths.librarySettings(library.id), {
        reservation_hours: Number(values.reservation_hours),
        reservation_reminder_hours: Number(values.reservation_reminder_hours),
        lend_days: Number(values.lend_days),
        loan_reminder_days: Number(values.loan_reminder_days),
        overdue_reminder_days: Number(values.overdue_reminder_days),
        overdue_price_per_day: Number(values.overdue_price_per_day.replace(',', '.')),
        notify_staff_on_reservation: values.notify_staff_on_reservation,
        notify_staff_on_issue: values.notify_staff_on_issue,
        notify_staff_on_return: values.notify_staff_on_return,
        notify_staff_on_cancellation: values.notify_staff_on_cancellation,
        notify_staff_daily_digest: values.notify_staff_daily_digest,
        notify_user_reservation_expiry: values.notify_user_reservation_expiry,
        notify_user_loan_due: values.notify_user_loan_due,
      })
      toast.success(t('libs.settings.updated'), { description: library.name })
      onSuccess()
      onClose()
    } catch (error) {
      if (error instanceof ApiError) {
        const validationErrors = error.validationErrors
        if (validationErrors) {
          Object.entries(validationErrors).forEach(([field, messages]) => {
            form.setError(field as keyof FormValues, { message: messages[0] })
          })
        } else {
          toast.error(error.messageText ?? t('errors.unexpected'))
        }
      }
    }
  })

  return (
    <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle className="font-brand-heading text-xl">{t('libs.settings.title')}</DialogTitle>
          <DialogDescription>{t('libs.settings.subtitle')}</DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={onSubmit} className="max-h-[65vh] space-y-4 overflow-y-auto pr-1" noValidate>
            <FormField
              control={form.control}
              name="reservation_hours"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('libs.settings.reservationHours')}</FormLabel>
                  <FormControl>
                    <Input type="number" min={1} max={720} step={1} inputMode="numeric" {...field} />
                  </FormControl>
                  <FormDescription>{t('libs.settings.reservationHoursHint')}</FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="lend_days"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('libs.settings.lendDays')}</FormLabel>
                  <FormControl>
                    <Input type="number" min={1} max={365} step={1} inputMode="numeric" {...field} />
                  </FormControl>
                  <FormDescription>{t('libs.settings.lendDaysHint')}</FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="overdue_price_per_day"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('libs.settings.overduePrice')}</FormLabel>
                  <FormControl>
                    <Input type="text" inputMode="decimal" {...field} />
                  </FormControl>
                  <FormDescription>{t('libs.settings.overduePriceHint')}</FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="reservation_reminder_hours"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('libs.settings.reservationReminderHours')}</FormLabel>
                  <FormControl>
                    <Input type="number" min={1} max={720} step={1} inputMode="numeric" {...field} />
                  </FormControl>
                  <FormDescription>{t('libs.settings.reservationReminderHoursHint')}</FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="loan_reminder_days"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('libs.settings.loanReminderDays')}</FormLabel>
                  <FormControl>
                    <Input type="number" min={1} max={365} step={1} inputMode="numeric" {...field} />
                  </FormControl>
                  <FormDescription>{t('libs.settings.loanReminderDaysHint')}</FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="overdue_reminder_days"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('libs.settings.overdueReminderDays')}</FormLabel>
                  <FormControl>
                    <Input type="number" min={1} max={365} step={1} inputMode="numeric" {...field} />
                  </FormControl>
                  <FormDescription>{t('libs.settings.overdueReminderDaysHint')}</FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />

            <div className="space-y-3 border-t pt-4">
              <p className="text-sm font-semibold">{t('libs.settings.notificationsTitle')}</p>

              {([
                ['notify_staff_on_reservation', 'notifyStaffOnReservation'],
                ['notify_staff_on_issue', 'notifyStaffOnIssue'],
                ['notify_staff_on_return', 'notifyStaffOnReturn'],
                ['notify_staff_on_cancellation', 'notifyStaffOnCancellation'],
                ['notify_staff_daily_digest', 'notifyStaffDailyDigest'],
                ['notify_user_reservation_expiry', 'notifyUserReservationExpiry'],
                ['notify_user_loan_due', 'notifyUserLoanDue'],
              ] as const).map(([fieldName, labelKey]) => (
                <FormField
                  key={fieldName}
                  control={form.control}
                  name={fieldName}
                  render={({ field }) => (
                    <FormItem className="flex flex-row items-start gap-3 space-y-0">
                      <FormControl>
                        <Checkbox
                          checked={field.value}
                          onCheckedChange={(value) => field.onChange(value === true)}
                        />
                      </FormControl>
                      <FormLabel className="!mt-0 font-normal">
                        {t(`libs.settings.${labelKey}`)}
                      </FormLabel>
                    </FormItem>
                  )}
                />
              ))}
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
