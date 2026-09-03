import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useTranslation } from 'react-i18next'
import { useAuth } from '@/hooks/useAuth'
import { ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { FieldError, FormItem } from '@/components/ui/form'
import { Spinner } from '@/components/ui/loader'

export function LoginPage() {
  const { t } = useTranslation()
  const { login } = useAuth()
  const navigate = useNavigate()
  const [pending, setPending] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  const schema = z.object({
    email: z.string().trim().min(1, t('errors.required')).email(t('errors.emailInvalid')),
    password: z.string().min(1, t('errors.required')),
    remember: z.boolean().optional(),
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
    defaultValues: { email: '', password: '', remember: false },
  })

  const onSubmit = handleSubmit(async (values) => {
    setPending(true)
    setFormError(null)
    try {
      await login({ email: values.email, password: values.password, remember: !!values.remember })
      navigate('/', { replace: true })
    } catch (error) {
      if (error instanceof ApiError) {
        const email = error.validationErrors?.email?.[0]
        const password = error.validationErrors?.password?.[0]
        if (email) setError('email', { message: email })
        if (password) setError('password', { message: password })
        if (!email && !password) {
          setFormError(error.messageText ?? t('auth.loginError'))
        }
      } else {
        setFormError(t('errors.unexpected'))
      }
    } finally {
      setPending(false)
    }
  })

  return (
    <Card>
      <CardHeader className="space-y-2">
        <CardTitle className="font-brand-heading text-2xl">{t('auth.loginTitle')}</CardTitle>
        <CardDescription>{t('auth.loginSubtitle')}</CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={onSubmit} className="space-y-5" noValidate>
          {formError ? (
            <p className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
              {formError}
            </p>
          ) : null}

          <FormItem>
            <Label htmlFor="email">{t('auth.emailLabel')}</Label>
            <Input
              id="email"
              type="email"
              autoComplete="email"
              autoCapitalize="none"
              placeholder={t('auth.emailLabel')}
              aria-invalid={!!errors.email}
              {...register('email')}
            />
            <FieldError message={errors.email?.message} />
          </FormItem>

          <FormItem>
            <div className="flex items-center justify-between">
              <Label htmlFor="password">{t('auth.passwordLabel')}</Label>
              <Link
                to="/forgot-password"
                className="text-sm font-medium text-primary underline-offset-4 hover:underline"
              >
                {t('auth.forgotLink')}
              </Link>
            </div>
            <Input
              id="password"
              type="password"
              autoComplete="current-password"
              aria-invalid={!!errors.password}
              {...register('password')}
            />
            <FieldError message={errors.password?.message} />
          </FormItem>

          <label className="flex cursor-pointer items-center gap-2 text-sm text-muted-foreground">
            <input
              type="checkbox"
              className="size-4 rounded accent-[#ffd968]"
              {...register('remember')}
            />
            {t('auth.remember')}
          </label>

          <Button type="submit" variant="brand" className="w-full" disabled={pending}>
            {pending ? <Spinner className="border-white/40 border-t-transparent" /> : null}
            {t('auth.submit')}
          </Button>
        </form>
      </CardContent>
    </Card>
  )
}
