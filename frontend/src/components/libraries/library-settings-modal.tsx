import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
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
  })

  type FormValues = z.infer<typeof schema>

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    mode: 'onTouched',
    defaultValues: { reservation_hours: '48', lend_days: '30', overdue_price_per_day: '0' },
  })

  useEffect(() => {
    if (!open) return
    form.reset({
      reservation_hours: String(library.settings?.reservation_hours ?? 48),
      lend_days: String(library.settings?.lend_days ?? 30),
      overdue_price_per_day: String(library.settings?.overdue_price_per_day ?? 0),
    })
  }, [open, library, form]) // eslint-disable-line react-hooks/exhaustive-deps

  const onSubmit = form.handleSubmit(async (values) => {
    try {
      await api.put(apiPaths.librarySettings(library.id), {
        reservation_hours: Number(values.reservation_hours),
        lend_days: Number(values.lend_days),
        overdue_price_per_day: Number(values.overdue_price_per_day.replace(',', '.')),
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
          <form onSubmit={onSubmit} className="space-y-4" noValidate>
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
