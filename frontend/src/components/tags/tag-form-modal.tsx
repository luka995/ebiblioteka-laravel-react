import { useEffect, useMemo, useState } from 'react'
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
import { LibrarySelect, type LibraryOption } from '@/components/libraries/library-select'
import { useAuth } from '@/hooks/useAuth'
import type { Library, PaginatedResponse, Tag } from '@/types'

interface TagFormModalProps {
  open: boolean
  onClose: () => void
  onSuccess: (name: string) => void
  tag?: Tag | null
}

export function TagFormModal({ open, onClose, onSuccess, tag }: TagFormModalProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { user } = useAuth()
  const editing = Boolean(tag)
  const isSuperAdmin = user?.role === 'superadmin'
  const [selectedLibrary, setSelectedLibrary] = useState<LibraryOption[]>([])

  const librariesQuery = useQuery({
    queryKey: ['libraries', 'options'],
    queryFn: async () => {
      const response = await api.get<PaginatedResponse<Library>>(`${apiPaths.libraries}?all=1`)
      return response.data
    },
    enabled: !isSuperAdmin,
  })

  const schema = useMemo(
    () =>
      z.object({
        name: z.string().trim().min(1, t('errors.required')).max(255),
        library_id: z.string().min(1, t('errors.required')),
      }),
    [t],
  )

  type FormValues = z.infer<typeof schema>

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    mode: 'onTouched',
    defaultValues: { name: '', library_id: '' },
  })

  useEffect(() => {
    if (!open) return
    form.reset({
      name: tag?.name ?? '',
      library_id: tag ? String(tag.library_id) : '',
    })
    setSelectedLibrary(tag ? [{ id: tag.library_id, name: tag.library_name ?? '' }] : [])
  }, [open, tag, form])

  const handleLibraryChange = (options: LibraryOption[]) => {
    setSelectedLibrary(options)
    form.setValue('library_id', options[0] ? String(options[0].id) : '', {
      shouldValidate: false,
      shouldDirty: true,
    })
  }

  const onSubmit = form.handleSubmit(async (values) => {
    const payload = {
      name: values.name,
      library_id: Number(values.library_id),
    }

    try {
      if (editing && tag) {
        await api.put(apiPaths.tag(tag.id), payload)
      } else {
        await api.post(apiPaths.tags, payload)
      }
      await queryClient.invalidateQueries({ queryKey: ['tags'] })
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
            {editing ? t('tags.editTitle') : t('tags.formTitle')}
          </DialogTitle>
          <DialogDescription>
            {editing ? t('tags.editSubtitle') : t('tags.formSubtitle')}
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
              name="library_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('tags.libraryLabel')}</FormLabel>
                  {isSuperAdmin ? (
                    <LibrarySelect
                      multiple={false}
                      value={selectedLibrary}
                      onChange={handleLibraryChange}
                      placeholder={t('tags.libraryPlaceholder')}
                    />
                  ) : (
                    <Select value={field.value || undefined} onValueChange={field.onChange}>
                      <FormControl>
                        <SelectTrigger className="w-full">
                          <SelectValue placeholder={t('tags.libraryPlaceholder')} />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {librariesQuery.data?.map((library) => (
                          <SelectItem key={library.id} value={String(library.id)}>
                            {library.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  )}
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
