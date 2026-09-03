import { useState } from 'react'
import { Link } from 'react-router-dom'
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
import { MailCheck } from 'lucide-react'

export function ForgotPasswordPage() {
  const { t } = useTranslation()
  const [pending, setPending] = useState(false)
  const [sent, setSent] = useState(false)
  const [submittedEmail, setSubmittedEmail] = useState('')

  const schema = z.object({
    email: z.string().trim().min(1, t('errors.required')).email(t('errors.emailInvalid')),
  })

  type FormValues = z.infer<typeof schema>

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { email: '' } })

  const onSubmit = handleSubmit(async (values) => {
    setPending(true)
    try {
      await api.post(apiPaths.forgotPassword, { email: values.email })
      setSubmittedEmail(values.email)
      setSent(true)
    } catch (error) {
      if (!(error instanceof ApiError)) {
        throw error
      }
    } finally {
      setPending(false)
    }
  })

  if (sent) {
    return (
      <Card>
        <CardHeader className="items-center space-y-3 text-center">
          <span className="flex size-12 items-center justify-center rounded-full bg-brand-soft text-brand">
            <MailCheck className="size-6" />
          </span>
          <CardTitle className="font-brand-heading text-2xl">{t('auth.forgotSentTitle')}</CardTitle>
          <CardDescription>{t('auth.forgotSentText', { email: submittedEmail })}</CardDescription>
        </CardHeader>
        <CardContent className="flex justify-center">
          <Link to="/login" className={buttonVariants({ variant: 'outline' })}>
            {t('auth.backToLogin')}
          </Link>
        </CardContent>
      </Card>
    )
  }

  return (
    <Card>
      <CardHeader className="space-y-2">
        <CardTitle className="font-brand-heading text-2xl">{t('auth.forgotTitle')}</CardTitle>
        <CardDescription>{t('auth.forgotSubtitle')}</CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={onSubmit} className="space-y-5" noValidate>
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

          <Button type="submit" variant="brand" className="w-full" disabled={pending}>
            {pending ? <Spinner className="border-white/40 border-t-transparent" /> : null}
            {t('auth.forgotSubmit')}
          </Button>

          <div className="text-center">
            <Link to="/login" className="text-sm font-medium text-primary underline-offset-4 hover:underline">
              {t('auth.backToLogin')}
            </Link>
          </div>
        </form>
      </CardContent>
    </Card>
  )
}
