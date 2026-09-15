import { useEffect, useMemo, useRef, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useTranslation } from 'react-i18next'
import { ImagePlus, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { storageUrl } from '@/lib/environment'
import { Button } from '@/components/ui/button'
import { DateInput } from '@/components/ui/date-input'
import { Input } from '@/components/ui/input'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { RichTextEditor } from '@/components/ui/rich-text-editor'
import type { News } from '@/types'

interface NewsFormModalProps {
  open: boolean
  onClose: () => void
  onSuccess: (title: string) => void
  news?: News | null
}

function todayIso(): string {
  return new Date().toISOString().slice(0, 10)
}

export function NewsFormModal({ open, onClose, onSuccess, news }: NewsFormModalProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const editing = Boolean(news)
  const fileRef = useRef<HTMLInputElement>(null)
  const [imagePath, setImagePath] = useState<string | null>(null)
  const [imagePreview, setImagePreview] = useState<string | null>(null)
  const [uploading, setUploading] = useState(false)

  const schema = useMemo(
    () =>
      z.object({
        title: z.string().trim().min(1, t('errors.required')).max(255),
        date: z.string().min(1, t('errors.required')),
        body: z.string().min(1, t('errors.required')),
      }),
    [t],
  )

  type FormValues = z.infer<typeof schema>

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    mode: 'onTouched',
    defaultValues: { title: '', date: todayIso(), body: '' },
  })

  useEffect(() => {
    if (!open) return
    form.reset({
      title: news?.title ?? '',
      date: news?.date ?? todayIso(),
      body: news?.body ?? '',
    })
    setImagePath(news?.image ?? null)
    setImagePreview(storageUrl(news?.image_url))
  }, [open, news, form])

  const handleFileChange = async (file: File | undefined) => {
    if (!file) return
    setUploading(true)
    try {
      const result = await api.upload<{ path: string; url: string }>(apiPaths.newsUploadImage, file)
      setImagePath(result.path)
      setImagePreview(storageUrl(result.url))
      form.setValue('body', form.getValues('body'))
    } catch (error) {
      if (error instanceof ApiError) {
        toast.error(error.messageText ?? t('errors.unexpected'))
      } else {
        toast.error(t('errors.unexpected'))
      }
    } finally {
      setUploading(false)
    }
  }

  const onSubmit = form.handleSubmit(async (values) => {
    const payload = {
      title: values.title,
      date: values.date,
      body: values.body,
      image: imagePath,
    }

    try {
      if (editing && news) {
        await api.put(apiPaths.newsItem(news.id), payload)
      } else {
        await api.post(apiPaths.news, payload)
      }
      await queryClient.invalidateQueries({ queryKey: ['news'] })
      onSuccess(values.title)
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
      <DialogContent className="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle className="font-brand-heading text-xl">
            {editing ? t('news.editTitle') : t('news.formTitle')}
          </DialogTitle>
          <DialogDescription>{editing ? t('news.editSubtitle') : t('news.formSubtitle')}</DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={onSubmit} className="space-y-4" noValidate>
            <div className="grid gap-4 sm:grid-cols-[1fr_180px]">
              <FormField
                control={form.control}
                name="title"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t('news.fields.title')}</FormLabel>
                    <FormControl>
                      <Input placeholder={t('news.fields.titlePlaceholder')} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="date"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t('news.fields.date')}</FormLabel>
                    <FormControl>
                      <DateInput {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <FormItem>
              <FormLabel>{t('news.fields.image')}</FormLabel>
              <div className="flex items-center gap-3">
                {imagePreview ? (
                  <div className="relative h-20 w-32 overflow-hidden rounded-md border">
                    <img src={imagePreview} alt="" className="h-full w-full object-cover" />
                    <button
                      type="button"
                      onClick={() => {
                        setImagePath(null)
                        setImagePreview(null)
                      }}
                      className="absolute right-1 top-1 rounded bg-black/60 p-1 text-white hover:bg-black/80"
                      aria-label={t('news.fields.removeImage')}
                    >
                      <Trash2 className="size-3.5" />
                    </button>
                  </div>
                ) : (
                  <div className="grid h-20 w-32 place-items-center rounded-md border border-dashed text-muted-foreground">
                    <ImagePlus className="size-5" />
                  </div>
                )}
                <div className="flex flex-col gap-2">
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={uploading}
                    onClick={() => fileRef.current?.click()}
                  >
                    {uploading ? t('common.loading') : t('news.fields.chooseImage')}
                  </Button>
                  <input
                    ref={fileRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    className="hidden"
                    onChange={(event) => {
                      void handleFileChange(event.target.files?.[0])
                      event.target.value = ''
                    }}
                  />
                </div>
              </div>
            </FormItem>

            <FormField
              control={form.control}
              name="body"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('news.fields.body')}</FormLabel>
                  <FormControl>
                    <RichTextEditor value={field.value} onChange={field.onChange} />
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
