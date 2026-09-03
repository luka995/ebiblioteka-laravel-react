import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useTranslation } from 'react-i18next'
import { api, ApiError, apiPaths } from '@/lib/api'
import { Button, buttonVariants } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { FieldError, FormItem } from '@/components/ui/form'
import { Spinner } from '@/components/ui/loader'
import { CheckCircle2 } from 'lucide-react'

export function ResetPasswordPage() {
  const { t } = useTranslation()
  const [searchParams] = useSearchParams()
  const token = searchParams.get('token') ?? ''
  const email = searchParams.get('email') ?? ''

  const [pending, setPending] = useState(false)
  const [success, setSuccess] = useState(false)

  const schema = z
    .object({
      password: z.string().min(8, t('errors.minPassword')),
      password_confirmation: z.string(),
    })
    .refine((data) => data.password === data.password_confirmation, {
      message: t('errors.passwordMismatch'),
      path: ['password_confirmation'],
    })

  type FormValues = z.infer<typeof schema>

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    mode: 'onTouched',
    defaultValues: { password: '', password_confirmation: '' },
  })

  if (!token || !email) {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="font-brand-heading text-2xl">{t('auth.resetTitle')}</CardTitle>
          <CardDescription>{t('errors.unexpected')}</CardDescription>
        </CardHeader>
        <CardContent>
          <Link to="/forgot-password" className={buttonVariants({ variant: 'outline' })}>
            {t('auth.forgotTitle')}
          </Link>
        </CardContent>
      </Card>
    )
  }

  const onSubmit = handleSubmit(async (values) => {
    setPending(true)
    try {
      await api.post(apiPaths.resetPassword, {
        token,
        email,
        password: values.password,
        password_confirmation: values.password_confirmation,
      })
      setSuccess(true)
    } catch (error) {
      if (error instanceof ApiError) {
        const fieldErrors = error.validationErrors
        if (fieldErrors?.email?.[0]) setError('password', { message: fieldErrors.email[0] })
        if (fieldErrors?.password?.[0]) setError('password', { message: fieldErrors.password[0] })
      }
    } finally {
      setPending(false)
    }
  })

  if (success) {
    return (
      <Card>
        <CardHeader className="items-center space-y-3 text-center">
          <span className="flex size-12 items-center justify-center rounded-full bg-brand-soft text-brand">
            <CheckCircle2 className="size-6" />
          </span>
          <CardTitle className="font-brand-heading text-2xl">{t('auth.resetSuccessTitle')}</CardTitle>
          <CardDescription>{t('auth.resetSuccessText')}</CardDescription>
        </CardHeader>
        <CardContent className="flex justify-center">
          <Link to="/login" className={buttonVariants({ variant: 'brand' })}>
            {t('auth.submit')}
          </Link>
        </CardContent>
      </Card>
    )
  }

  return (
    <Card>
      <CardHeader className="space-y-2">
        <CardTitle className="font-brand-heading text-2xl">{t('auth.resetTitle')}</CardTitle>
        <CardDescription>{t('auth.resetSubtitle')}</CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={onSubmit} className="space-y-5" noValidate>
          <FormItem>
            <Label htmlFor="password">{t('auth.newPasswordLabel')}</Label>
            <Input
              id="password"
              type="password"
              autoComplete="new-password"
              aria-invalid={!!errors.password}
              {...register('password')}
            />
            <p className="text-xs text-muted-foreground">{t('auth.passwordHint')}</p>
            <FieldError message={errors.password?.message} />
          </FormItem>

          <FormItem>
            <Label htmlFor="password_confirmation">{t('auth.confirmPasswordLabel')}</Label>
            <Input
              id="password_confirmation"
              type="password"
              autoComplete="new-password"
              aria-invalid={!!errors.password_confirmation}
              {...register('password_confirmation')}
            />
            <FieldError message={errors.password_confirmation?.message} />
          </FormItem>

          <Button type="submit" variant="brand" className="w-full" disabled={pending}>
            {pending ? <Spinner className="border-white/40 border-t-transparent" /> : null}
            {t('auth.resetSubmit')}
          </Button>
        </form>
      </CardContent>
    </Card>
  )
}
