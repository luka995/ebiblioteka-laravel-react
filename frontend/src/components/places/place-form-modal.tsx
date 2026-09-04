import { useEffect, useMemo } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useTranslation } from 'react-i18next'
import { api, apiPaths, ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { PaginatedResponse, Place, Region } from '@/types'

interface PlaceFormModalProps {
  open: boolean
  onClose: () => void
  onSuccess: (name: string) => void
  place?: Place | null
}

export function PlaceFormModal({ open, onClose, onSuccess, place }: PlaceFormModalProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const editing = Boolean(place)

  const regionsQuery = useQuery({
    queryKey: ['regions', 'options'],
    queryFn: async () => {
      const response = await api.get<PaginatedResponse<Region>>(`${apiPaths.regions}?all=1`)
      return response.data
    },
  })

  const schema = useMemo(
    () =>
      z.object({
        name: z.string().trim().min(1, t('errors.required')).max(255),
        region_id: z.string().min(1, t('errors.required')),
      }),
    [t],
  )

  type FormValues = z.infer<typeof schema>

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    mode: 'onTouched',
    defaultValues: { name: '', region_id: '' },
  })

  useEffect(() => {
    if (!open) return
    form.reset({
      name: place?.name ?? '',
      region_id: place ? String(place.region_id) : '',
    })
  }, [open, place, form])

  const onSubmit = form.handleSubmit(async (values) => {
    const payload = {
      name: values.name,
      region_id: Number(values.region_id),
    }

    try {
      if (editing && place) {
        await api.put(apiPaths.place(place.id), payload)
      } else {
        await api.post(apiPaths.places, payload)
      }
      await queryClient.invalidateQueries({ queryKey: ['places'] })
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
            {editing ? t('places.editTitle') : t('places.formTitle')}
          </DialogTitle>
          <DialogDescription>
            {editing ? t('places.editSubtitle') : t('places.formSubtitle')}
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

            <FormField
              control={form.control}
              name="region_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('places.regionLabel')}</FormLabel>
                  <Select value={field.value || undefined} onValueChange={field.onChange}>
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue placeholder={t('places.regionPlaceholder')} />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {regionsQuery.data?.map((region) => (
                        <SelectItem key={region.id} value={String(region.id)}>
                          {region.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
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
