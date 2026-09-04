import { useEffect, useMemo } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { PageLoader } from '@/components/ui/loader'
import type { User } from '@/types'

function initialsOf(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean)
  const first = parts[0]?.[0] ?? '?'
  const last = parts.length > 1 ? parts[parts.length - 1][0] : ''
  return (first + last).toUpperCase()
}

function ReadOnlyField({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="space-y-1">
      <p className="text-sm font-medium text-muted-foreground">{label}</p>
      <p className="text-sm">{children || '—'}</p>
    </div>
  )
}

export function ProfilePage() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  const profileQuery = useQuery({
    queryKey: ['profile'],
    queryFn: async () => {
      const response = await api.get<{ data: User }>(apiPaths.profile)
      return response.data
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
        jmbg: z.string().trim().regex(/^\d{13}$/, t('errors.jmbgInvalid')).optional().or(z.literal('')),
        address: z.string().trim().max(255).optional().or(z.literal('')),
        city: z.string().trim().max(255).optional().or(z.literal('')),
        post_code: z.string().trim().max(20).optional().or(z.literal('')),
      }),
    [t],
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
      jmbg: '',
      address: '',
      city: '',
      post_code: '',
    },
  })

  const profile = profileQuery.data

  useEffect(() => {
    if (!profile) return
    form.reset({
      first_name: profile.first_name ?? '',
      last_name: profile.last_name ?? '',
      username: profile.username ?? '',
      email: profile.email ?? '',
      jmbg: profile.jmbg ?? '',
      address: profile.address ?? '',
      city: profile.city ?? '',
      post_code: profile.post_code ?? '',
    })
  }, [profile, form])

  if (profileQuery.isLoading) return <PageLoader />

  if (profileQuery.isError || !profile) {
    return <p className="text-sm text-muted-foreground">{t('errors.unexpected')}</p>
  }

  const memberLibraries = (profile.libraries ?? []).map((library) => library.name)

  const onSubmit = form.handleSubmit(async (values) => {
    const payload = {
      first_name: values.first_name,
      last_name: values.last_name,
      username: values.username || null,
      email: values.email,
      jmbg: values.jmbg || null,
      address: values.address || null,
      city: values.city || null,
      post_code: values.post_code || null,
    }

    try {
      await api.put(apiPaths.profile, payload)
      await queryClient.invalidateQueries({ queryKey: ['profile'] })
      await queryClient.invalidateQueries({ queryKey: ['auth', 'me'] })
      toast.success(t('profile.updated'))
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
    <div className="mx-auto max-w-2xl space-y-6">
      <Card>
        <CardContent className="flex flex-col gap-4 p-6 sm:flex-row sm:items-center">
          <Avatar className="size-16 text-lg">
            <AvatarFallback className="bg-brand-soft font-semibold text-brand">
              {initialsOf(profile.name)}
            </AvatarFallback>
          </Avatar>

          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <h2 className="font-brand-heading text-xl font-bold tracking-tight">{profile.name}</h2>
              <Badge className="border-transparent bg-brand-soft text-brand">{profile.role_label}</Badge>
            </div>
            {profile.username ? <p className="text-sm text-muted-foreground">@{profile.username}</p> : null}
            <p className="text-sm text-muted-foreground">{profile.email}</p>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="font-brand-heading text-base">{t('profile.title')}</CardTitle>
        </CardHeader>
        <CardContent>
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

              <div className="rounded-md border p-4">
                <p className="mb-3 text-sm font-medium text-muted-foreground">{t('profile.readOnlyHint')}</p>
                <div className="grid gap-4 sm:grid-cols-3">
                  <ReadOnlyField label={t('fields.role')}>{profile.role_label}</ReadOnlyField>
                  <ReadOnlyField label={t('fields.libraries')}>
                    {memberLibraries.length > 0 ? memberLibraries.join(', ') : null}
                  </ReadOnlyField>
                  <ReadOnlyField label={t('fields.barcode')}>
                    <span className="font-mono">{profile.bar_code ?? '—'}</span>
                  </ReadOnlyField>
                </div>
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <Button type="submit" variant="brand" disabled={form.formState.isSubmitting}>
                  {t('common.save')}
                </Button>
              </div>
            </form>
          </Form>
        </CardContent>
      </Card>
    </div>
  )
}
