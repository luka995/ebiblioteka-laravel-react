import { useEffect, useMemo } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useTranslation } from 'react-i18next'
import { api, apiPaths, ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import type { Region } from '@/types'

interface RegionFormModalProps {
  open: boolean
  onClose: () => void
  onSuccess: (name: string) => void
  region?: Region | null
}

export function RegionFormModal({ open, onClose, onSuccess, region }: RegionFormModalProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const editing = Boolean(region)

  const schema = useMemo(
    () =>
      z.object({
        name: z.string().trim().min(1, t('errors.required')).max(255),
      }),
    [t],
  )

  type FormValues = z.infer<typeof schema>

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    mode: 'onTouched',
    defaultValues: { name: '' },
  })

  useEffect(() => {
    if (!open) return
    form.reset({ name: region?.name ?? '' })
  }, [open, region, form])

  const onSubmit = form.handleSubmit(async (values) => {
    try {
      if (editing && region) {
        await api.put(apiPaths.region(region.id), values)
      } else {
        await api.post(apiPaths.regions, values)
      }
      await queryClient.invalidateQueries({ queryKey: ['regions'] })
      onSuccess(values.name)
      onClose()
    } catch (error) {
      if (error instanceof ApiError) {
        const validationErrors = error.validationErrors
        if (validationErrors) {
          Object.entries(validationErrors).forEach(([field, messages]) => {
            form.setError(field as keyof FormValues, { message: messages[0] })
          })
        }
      }
    }
  })

  return (
    <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle className="font-brand-heading text-xl">
            {editing ? t('regions.editTitle') : t('regions.formTitle')}
          </DialogTitle>
          <DialogDescription>
            {editing ? t('regions.editSubtitle') : t('regions.formSubtitle')}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={onSubmit} className="space-y-4" noValidate>
            <FormField
              control={form.control}
              name="name"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('fields.name')}</FormLabel>
                  <FormControl>
                    <Input placeholder={t('fields.name')} {...field} />
                  </FormControl>
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
