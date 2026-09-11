import { useEffect, useMemo, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { LibrarySelect, type LibraryOption } from '@/components/libraries/library-select'
import { CategorySelect, type CategoryOption } from '@/components/categories/category-select'
import { useAuth } from '@/hooks/useAuth'
import type { Category } from '@/types'

interface CategoryFormModalProps {
  open: boolean
  onClose: () => void
  onSuccess: (name: string) => void
  category?: Category | null
}

export function CategoryFormModal({ open, onClose, onSuccess, category }: CategoryFormModalProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { user, activeLibrary } = useAuth()
  const editing = Boolean(category)
  const isSuperAdmin = user?.role === 'superadmin'
  const [selectedLibrary, setSelectedLibrary] = useState<LibraryOption[]>([])
  const [parent, setParent] = useState<CategoryOption | null>(null)

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

  const libraryId = isSuperAdmin
    ? (selectedLibrary[0]?.id ?? null)
    : (activeLibrary?.id ?? null)

  useEffect(() => {
    if (!open) return
    form.reset({ name: category?.name ?? '' })
    setSelectedLibrary(
      category ? [{ id: category.library_id, name: category.library_name ?? '' }] : [],
    )
    setParent(
      category?.parent_id
        ? {
            id: category.parent_id,
            full_name: category.parent_full_name ?? category.parent_name ?? String(category.parent_id),
          }
        : null,
    )
  }, [open, category, form])

  const handleLibraryChange = (options: LibraryOption[]) => {
    setSelectedLibrary(options)
    setParent(null)
  }

  const onSubmit = form.handleSubmit(async (values) => {
    if (isSuperAdmin && !libraryId) {
      toast.error(t('categories.libraryRequired'))
      return
    }

    try {
      if (editing && category) {
        await api.put(apiPaths.category(category.id), {
          name: values.name,
          parent_id: parent?.id ?? null,
        })
      } else {
        await api.post(apiPaths.categories, {
          name: values.name,
          ...(isSuperAdmin ? { library_id: libraryId } : {}),
          parent_id: parent?.id ?? null,
        })
      }
      await queryClient.invalidateQueries({ queryKey: ['categories'] })
      onSuccess(values.name)
      onClose()
    } catch (error) {
      if (error instanceof ApiError) {
        const validationErrors = error.validationErrors
        if (validationErrors) {
          Object.entries(validationErrors).forEach(([field, messages]) => {
            if (field === 'name') {
              form.setError('name', { message: messages[0] })
            } else {
              toast.error(messages[0])
            }
          })
        } else if (error.messageText) {
          toast.error(error.messageText)
        }
      }
    }
  })

  return (
    <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle className="font-brand-heading text-xl">
            {editing ? t('categories.editTitle') : t('categories.formTitle')}
          </DialogTitle>
          <DialogDescription>
            {editing ? t('categories.editSubtitle') : t('categories.formSubtitle')}
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

            {isSuperAdmin ? (
              <FormItem>
                <FormLabel>{t('categories.libraryLabel')}</FormLabel>
                <LibrarySelect
                  multiple={false}
                  value={selectedLibrary}
                  onChange={handleLibraryChange}
                  placeholder={t('categories.libraryPlaceholder')}
                />
              </FormItem>
            ) : null}

            <FormItem>
              <FormLabel>{t('categories.parentLabel')}</FormLabel>
              <CategorySelect
                libraryId={libraryId}
                value={parent}
                onChange={setParent}
                excludeId={category?.id}
              />
            </FormItem>

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
