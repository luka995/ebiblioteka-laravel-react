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
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { LibrarySelect, type LibraryOption } from '@/components/libraries/library-select'
import { useAuth } from '@/hooks/useAuth'
import type { Author } from '@/types'

interface AuthorFormModalProps {
  open: boolean
  onClose: () => void
  onSuccess: (name: string) => void
  author?: Author | null
}

export function AuthorFormModal({ open, onClose, onSuccess, author }: AuthorFormModalProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { user } = useAuth()
  const editing = Boolean(author)
  const isSuperAdmin = user?.role === 'superadmin'
  const [selectedLibrary, setSelectedLibrary] = useState<LibraryOption[]>([])

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
    form.reset({ name: author?.name ?? '' })
    setSelectedLibrary(
      author ? [{ id: author.library_id, name: author.library_name ?? '' }] : [],
    )
  }, [open, author, form])

  const onSubmit = form.handleSubmit(async (values) => {
    const libraryId = selectedLibrary[0]?.id

    if (!editing && isSuperAdmin && !libraryId) {
      toast.error(t('authors.libraryRequired'))
      return
    }

    try {
      if (editing && author) {
        await api.put(apiPaths.author(author.id), { name: values.name })
      } else {
        await api.post(apiPaths.authors, {
          name: values.name,
          ...(isSuperAdmin ? { library_id: libraryId } : {}),
        })
      }
      await queryClient.invalidateQueries({ queryKey: ['authors'] })
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
            {editing ? t('authors.editTitle') : t('authors.formTitle')}
          </DialogTitle>
          <DialogDescription>
            {editing ? t('authors.editSubtitle') : t('authors.formSubtitle')}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={onSubmit} className="space-y-4" noValidate>
            <FormField
              control={form.control}
              name="name"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('authors.nameLabel')}</FormLabel>
                   <FormControl>
                     <Input placeholder={t('authors.namePlaceholder')} {...field} />
                   </FormControl>
                   <FormDescription>{t('authors.nameDescription')}</FormDescription>
                   <FormMessage />
                </FormItem>
              )}
            />

            {!editing && isSuperAdmin ? (
              <FormItem>
                <FormLabel>{t('authors.libraryLabel')}</FormLabel>
                <LibrarySelect
                  multiple={false}
                  value={selectedLibrary}
                  onChange={setSelectedLibrary}
                  placeholder={t('authors.libraryPlaceholder')}
                />
              </FormItem>
            ) : null}

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
