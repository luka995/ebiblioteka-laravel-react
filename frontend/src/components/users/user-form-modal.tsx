import { useEffect, useMemo, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useTranslation } from 'react-i18next'
import { RefreshCw } from 'lucide-react'
import { api, apiPaths, ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { LibrarySelect, type LibraryOption } from '@/components/libraries/library-select'
import type { RoleOption, User } from '@/types'

interface UserFormModalProps {
  open: boolean
  onClose: () => void
  onSuccess: (label: string) => void
  user?: User | null
}

export function UserFormModal({ open, onClose, onSuccess, user }: UserFormModalProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const editing = Boolean(user)
  const [selectedLibraries, setSelectedLibraries] = useState<LibraryOption[]>([])
  const [barCode, setBarCode] = useState<string>('')

  const rolesQuery = useQuery({
    queryKey: ['roles', 'assignable'],
    queryFn: async () => {
      const response = await api.get<{ roles: RoleOption[] }>(apiPaths.rolesAssignable)
      return response.roles
    },
  })

  const schema = useMemo(
    () =>
      z.object({
        first_name: z.string().trim().min(1, t('errors.required')).max(255),
        last_name: z.string().trim().min(1, t('errors.required')).max(255),
        username: z
          .string()
          .trim()
          .regex(/^[a-zA-Z0-9_.-]+$/, t('errors.usernameInvalid'))
          .max(255)
          .optional()
          .or(z.literal('')),
        email: z.string().trim().min(1, t('errors.required')).email(t('errors.emailInvalid')).max(255),
        password: editing ? z.string().optional().or(z.literal('')) : z.string().min(8, t('errors.minPassword')),
        role: z.string().min(1, t('errors.required')),
        jmbg: z.string().trim().regex(/^\d{13}$/, t('errors.jmbgInvalid')).optional().or(z.literal('')),
        address: z.string().trim().max(255).optional().or(z.literal('')),
        city: z.string().trim().max(255).optional().or(z.literal('')),
        post_code: z.string().trim().max(20).optional().or(z.literal('')),
      }),
    [editing, t],
  )

  type FormValues = z.infer<typeof schema>

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    mode: 'onTouched',
    defaultValues: {
      first_name: '',
      last_name: '',
      username: '',
      email: '',
      password: '',
      role: '',
      jmbg: '',
      address: '',
      city: '',
      post_code: '',
    },
  })

  useEffect(() => {
    if (!open) return

    form.reset({
      first_name: user?.first_name ?? '',
      last_name: user?.last_name ?? '',
      username: user?.username ?? '',
      email: user?.email ?? '',
      password: '',
      role: user?.role ?? '',
      jmbg: user?.jmbg ?? '',
      address: user?.address ?? '',
      city: user?.city ?? '',
      post_code: user?.post_code ?? '',
    })
    setSelectedLibraries(user?.libraries ?? [])
    setBarCode(user?.bar_code ?? '')
    if (!user) void regenerateBarcode()
  }, [open, user, form]) // eslint-disable-line react-hooks/exhaustive-deps

  const regenerateBarcode = async () => {
    try {
      const response = await api.get<{ bar_code: string }>(apiPaths.barcodeNext)
      setBarCode(response.bar_code)
    } catch {
      // ignore
    }
  }

  const onSubmit = form.handleSubmit(async (values) => {
    const payload = {
      first_name: values.first_name,
      last_name: values.last_name,
      username: values.username || null,
      email: values.email,
      role: values.role,
      jmbg: values.jmbg || null,
      address: values.address || null,
      city: values.city || null,
      post_code: values.post_code || null,
      bar_code: barCode,
      libraries: selectedLibraries.map((library) => library.id),
      ...(values.password ? { password: values.password } : {}),
    }

    try {
      if (editing && user) {
        await api.put(apiPaths.user(user.id), payload)
      } else {
        await api.post(apiPaths.users, payload)
      }
      await queryClient.invalidateQueries({ queryKey: ['users'] })
      onSuccess(values.email)
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
      <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-lg">
        <DialogHeader>
          <DialogTitle className="font-brand-heading text-xl">
            {editing ? t('users.editTitle') : t('users.formTitle')}
          </DialogTitle>
          <DialogDescription>
            {editing ? t('users.editSubtitle') : t('users.formSubtitle')}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={onSubmit} className="space-y-4" noValidate>
            <div className="grid gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="first_name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t('fields.firstName')}</FormLabel>
                    <FormControl>
                      <Input placeholder={t('fields.firstName')} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="last_name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t('fields.lastName')}</FormLabel>
                    <FormControl>
                      <Input placeholder={t('fields.lastName')} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="username"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>
                      {t('fields.username')}{' '}
                      <span className="font-normal text-muted-foreground">({t('fields.optional')})</span>
                    </FormLabel>
                    <FormControl>
                      <Input placeholder={t('fields.username')} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="email"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t('fields.email')}</FormLabel>
                    <FormControl>
                      <Input type="email" placeholder={t('fields.email')} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              {!editing ? (
                <FormField
                  control={form.control}
                  name="password"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t('fields.password')}</FormLabel>
                      <FormControl>
                        <Input type="password" placeholder={t('fields.password')} {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              ) : null}
              <FormField
                control={form.control}
                name="role"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t('fields.role')}</FormLabel>
                    <Select value={field.value || undefined} onValueChange={field.onChange}>
                      <FormControl>
                        <SelectTrigger className="w-full">
                          <SelectValue placeholder={t('fields.selectPlaceholder')} />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {rolesQuery.data?.map((role) => (
                          <SelectItem key={role.value} value={role.value}>
                            {role.label}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="jmbg"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>
                      {t('fields.jmbg')}{' '}
                      <span className="font-normal text-muted-foreground">({t('fields.optional')})</span>
                    </FormLabel>
                    <FormControl>
                      <Input placeholder={t('fields.jmbg')} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="address"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>
                      {t('fields.address')}{' '}
                      <span className="font-normal text-muted-foreground">({t('fields.optional')})</span>
                    </FormLabel>
                    <FormControl>
                      <Input placeholder={t('fields.address')} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="city"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>
                      {t('fields.city')}{' '}
                      <span className="font-normal text-muted-foreground">({t('fields.optional')})</span>
                    </FormLabel>
                    <FormControl>
                      <Input placeholder={t('fields.city')} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="post_code"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>
                      {t('fields.postCode')}{' '}
                      <span className="font-normal text-muted-foreground">({t('fields.optional')})</span>
                    </FormLabel>
                    <FormControl>
                      <Input placeholder={t('fields.postCode')} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <div className="space-y-2">
              <Label>{t('fields.libraries')}</Label>
              <LibrarySelect value={selectedLibraries} onChange={setSelectedLibraries} />
            </div>

            <div className="space-y-2">
              <div className="flex items-center justify-between gap-2">
                <Label htmlFor="bar_code">{t('fields.barcode')}</Label>
                <button
                  type="button"
                  onClick={() => void regenerateBarcode()}
                  className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                >
                  <RefreshCw className="size-3" />
                  {t('users.regenerate')}
                </button>
              </div>
              <Input id="bar_code" readOnly value={barCode} className="bg-muted/60 font-mono" />
              <p className="text-xs text-muted-foreground">{t('users.barcodeAuto')}</p>
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
