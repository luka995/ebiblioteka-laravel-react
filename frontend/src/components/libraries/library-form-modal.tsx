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
import type { Library, PaginatedResponse, Place, Region } from '@/types'

interface LibraryFormModalProps {
  open: boolean
  onClose: () => void
  onSuccess: (name: string) => void
  library?: Library | null
}

export function LibraryFormModal({ open, onClose, onSuccess, library }: LibraryFormModalProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const editing = Boolean(library)

  const regionsQuery = useQuery({
    queryKey: ['regions'],
    queryFn: async () => {
      const response = await api.get<PaginatedResponse<Region>>(apiPaths.regions)
      return response.data
    },
  })

  const schema = useMemo(
    () =>
      z.object({
        name: z.string().trim().min(1, t('errors.required')).max(255),
        region: z.string().min(1, t('errors.required')),
        place: z.string().min(1, t('errors.required')),
        address: z.string().trim().min(1, t('errors.required')).max(255),
        work_time: z.string().max(255).optional().or(z.literal('')),
      }),
    [t],
  )

  type FormValues = z.infer<typeof schema>

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    mode: 'onTouched',
    defaultValues: {
      name: '',
      region: '',
      place: '',
      address: '',
      work_time: '',
    },
  })

  const selectedRegion = form.watch('region')

  const placesQuery = useQuery({
    queryKey: ['places', selectedRegion],
    enabled: !!selectedRegion,
    queryFn: async () => {
      const response = await api.get<PaginatedResponse<Place>>(`${apiPaths.places}?region_id=${selectedRegion}`)
      return response.data
    },
  })

  useEffect(() => {
    if (!open) return

    const region = library?.place?.region_id ? String(library.place.region_id) : ''
    form.reset({
      name: library?.name ?? '',
      region,
      place: library ? String(library.place_id) : '',
      address: library?.address ?? '',
      work_time: library?.work_time ?? '',
    })
  }, [open, library, form]) // eslint-disable-line react-hooks/exhaustive-deps

  const onSubmit = form.handleSubmit(async (values) => {
    const payload = {
      name: values.name,
      address: values.address,
      place_id: Number(values.place),
      work_time: values.work_time || null,
    }

    try {
      if (editing && library) {
        await api.put(apiPaths.library(library.id), payload)
      } else {
        await api.post(apiPaths.libraries, payload)
      }
      await queryClient.invalidateQueries({ queryKey: ['libraries'] })
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
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle className="font-brand-heading text-xl">
            {editing ? t('libs.editTitle') : t('libs.formTitle')}
          </DialogTitle>
          <DialogDescription>
            {editing ? t('libs.editSubtitle') : t('libs.formSubtitle')}
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

            <div className="grid gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="region"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t('fields.region')}</FormLabel>
                    <Select
                      value={field.value || undefined}
                      onValueChange={(value) => {
                        field.onChange(value)
                        form.setValue('place', '')
                      }}
                    >
                      <FormControl>
                        <SelectTrigger className="w-full">
                          <SelectValue placeholder={t('libs.regionPlaceholder')} />
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

              <FormField
                control={form.control}
                name="place"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t('fields.place')}</FormLabel>
                    <Select
                      value={field.value || undefined}
                      onValueChange={field.onChange}
                      disabled={!selectedRegion || placesQuery.isLoading}
                    >
                      <FormControl>
                        <SelectTrigger className="w-full">
                          <SelectValue placeholder={t('libs.placePlaceholder')} />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {placesQuery.data?.map((place) => (
                          <SelectItem key={place.id} value={String(place.id)}>
                            {place.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <FormField
              control={form.control}
              name="address"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('fields.address')}</FormLabel>
                  <FormControl>
                    <Input placeholder={t('fields.address')} {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="work_time"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>
                    {t('fields.workTime')}{' '}
                    <span className="font-normal text-muted-foreground">({t('fields.optional')})</span>
                  </FormLabel>
                  <FormControl>
                    <Input placeholder={t('fields.workTime')} {...field} />
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
