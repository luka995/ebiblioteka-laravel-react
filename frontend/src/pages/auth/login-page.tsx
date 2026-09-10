import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useTranslation } from 'react-i18next'
import { useAuth } from '@/hooks/useAuth'
import { ApiError } from '@/lib/api'
import { Eye, EyeOff } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Spinner } from '@/components/ui/loader'

export function LoginPage() {
  const { t } = useTranslation()
  const { login } = useAuth()
  const navigate = useNavigate()
  const [pending, setPending] = useState(false)
  const [remember, setRemember] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [showPassword, setShowPassword] = useState(false)

  const schema = z.object({
    email: z.string().trim().min(1, t('errors.required')).email(t('errors.emailInvalid')),
    password: z.string().min(1, t('errors.required')),
  })

  type FormValues = z.infer<typeof schema>

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    mode: 'onTouched',
    defaultValues: { email: '', password: '' },
  })

  const onSubmit = form.handleSubmit(async (values) => {
    setPending(true)
    setFormError(null)
    try {
      await login({ email: values.email, password: values.password, remember })
      navigate('/', { replace: true })
    } catch (error) {
      if (error instanceof ApiError) {
        const email = error.validationErrors?.email?.[0]
        const password = error.validationErrors?.password?.[0]
        if (email) form.setError('email', { message: email })
        if (password) form.setError('password', { message: password })
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
      <CardHeader>
        <CardTitle className="font-brand-heading text-2xl">{t('auth.loginTitle')}</CardTitle>
        <CardDescription>{t('auth.loginSubtitle')}</CardDescription>
      </CardHeader>
      <CardContent>
        <Form {...form}>
          <form onSubmit={onSubmit} className="space-y-5" noValidate>
            {formError ? (
              <p className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                {formError}
              </p>
            ) : null}

            <FormField
              control={form.control}
              name="email"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('auth.emailLabel')}</FormLabel>
                  <FormControl>
                    <Input
                      type="email"
                      autoComplete="email"
                      autoCapitalize="none"
                      placeholder={t('auth.emailLabel')}
                      {...field}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="password"
              render={({ field }) => (
                <FormItem>
                  <div className="flex items-center justify-between">
                    <FormLabel>{t('auth.passwordLabel')}</FormLabel>
                    <Link
                      to="/forgot-password"
                      className="text-sm font-medium text-primary underline-offset-4 hover:underline"
                    >
                      {t('auth.forgotLink')}
                    </Link>
                  </div>
                  <FormControl>
                    <div className="relative">
                      <Input
                        type={showPassword ? 'text' : 'password'}
                        autoComplete="current-password"
                        className="pr-10"
                        {...field}
                      />
                      <button
                        type="button"
                        onClick={() => setShowPassword((v) => !v)}
                        aria-label={showPassword ? t('auth.hidePassword') : t('auth.showPassword')}
                        className="absolute inset-y-0 right-0 flex items-center px-3 text-muted-foreground hover:text-foreground"
                      >
                        {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                      </button>
                    </div>
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <div className="flex items-center gap-2">
              <Checkbox id="remember" checked={remember} onCheckedChange={(value) => setRemember(value === true)} />
              <Label htmlFor="remember" className="font-normal text-muted-foreground">
                {t('auth.remember')}
              </Label>
            </div>

            <Button type="submit" variant="brand" className="w-full" disabled={pending}>
              {pending ? <Spinner className="border-white/40 border-t-transparent" /> : null}
              {t('auth.submit')}
            </Button>
          </form>
        </Form>
      </CardContent>
    </Card>
  )
}
