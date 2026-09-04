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
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import type { User } from '@/types'

interface ChangePasswordDialogProps {
  open: boolean
  onClose: () => void
  target?: User | null
}

export function ChangePasswordDialog({ open, onClose, target }: ChangePasswordDialogProps) {
  const { t } = useTranslation()
  const isAdmin = Boolean(target)

  const schema = z
    .object({
      current_password: isAdmin ? z.string().optional().or(z.literal('')) : z.string().min(1, t('errors.required')),
      password: z.string().min(8, t('errors.minPassword')),
      password_confirmation: z.string().min(1, t('errors.required')),
    })
    .refine((data) => data.password === data.password_confirmation, {
      message: t('errors.passwordMismatch'),
      path: ['password_confirmation'],
    })

  type FormValues = z.infer<typeof schema>

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    mode: 'onTouched',
    defaultValues: {
      current_password: '',
      password: '',
      password_confirmation: '',
    },
  })

  useEffect(() => {
    if (!open) return
    form.reset({ current_password: '', password: '', password_confirmation: '' })
  }, [open, form])

  const onSubmit = form.handleSubmit(async (values) => {
    try {
      if (isAdmin && target) {
        await api.put(apiPaths.userPassword(target.id), {
          password: values.password,
          password_confirmation: values.password_confirmation,
        })
      } else {
        await api.put(apiPaths.profilePassword, {
          current_password: values.current_password,
          password: values.password,
          password_confirmation: values.password_confirmation,
        })
      }
      toast.success(t('profile.passwordUpdated'))
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
          <DialogTitle className="font-brand-heading text-xl">{t('profile.changePassword')}</DialogTitle>
          <DialogDescription>
            {isAdmin ? t('profile.passwordAdminHint', { name: target?.name ?? '' }) : t('profile.passwordHint')}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={onSubmit} className="space-y-4" noValidate>
            {!isAdmin ? (
              <FormField
                control={form.control}
                name="current_password"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t('profile.currentPassword')}</FormLabel>
                    <FormControl>
                      <Input type="password" autoComplete="current-password" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            ) : null}

            <FormField
              control={form.control}
              name="password"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('profile.newPassword')}</FormLabel>
                  <FormControl>
                    <Input type="password" autoComplete="new-password" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="password_confirmation"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('profile.confirmPassword')}</FormLabel>
                  <FormControl>
                    <Input type="password" autoComplete="new-password" {...field} />
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
