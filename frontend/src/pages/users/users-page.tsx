import { useEffect, useMemo, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useTranslation } from 'react-i18next'
import { Plus, RefreshCw, Search } from 'lucide-react'
import { api, apiPaths, ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select } from '@/components/ui/select'
import { FieldError, FieldHint, FormItem } from '@/components/ui/form'
import { Modal } from '@/components/ui/modal'
import { PageLoader } from '@/components/ui/loader'
import { useAuth } from '@/hooks/useAuth'
import type { Library, PaginatedResponse, RoleOption, User } from '@/types'

interface NewUserFormValues {
  first_name: string
  last_name: string
  username: string
  email: string
  password: string
  role: string
  jmbg: string
  address: string
  city: string
  post_code: string
}

function NewUserModal({
  open,
  onClose,
  onCreated,
}: {
  open: boolean
  onClose: () => void
  onCreated: () => void
}) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [selectedLibraries, setSelectedLibraries] = useState<number[]>([])
  const [barCode, setBarCode] = useState<string>('')
  const [pending, setPending] = useState(false)

  const rolesQuery = useQuery({
    queryKey: ['roles', 'assignable'],
    queryFn: async () => {
      const response = await api.get<{ roles: RoleOption[] }>(apiPaths.rolesAssignable)
      return response.roles
    },
  })

  const librariesQuery = useQuery({
    queryKey: ['libraries', 'options'],
    queryFn: async () => {
      const response = await api.get<PaginatedResponse<Library>>(`${apiPaths.libraries}?all=1`)
      return response.data.filter((library) => !library.deleted)
    },
  })

  const schema = useMemo(
    () =>
      z.object({
        first_name: z.string().trim().min(1, t('errors.required')),
        last_name: z.string().trim().min(1, t('errors.required')),
        username: z.string().trim().regex(/^[a-zA-Z0-9_.-]*$/, t('errors.usernameInvalid')),
        email: z.string().trim().min(1, t('errors.required')).email(t('errors.emailInvalid')),
        password: z.string().min(8, t('errors.minPassword')),
        role: z.string().min(1, t('errors.required')),
        jmbg: z.string().trim().regex(/^\d*$/, t('errors.jmbgInvalid')),
        address: z.string(),
        city: z.string(),
        post_code: z.string(),
      }),
    [t],
  )

  type FormValues = z.infer<typeof schema>

  const {
    register,
    handleSubmit,
    setError,
    reset,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    mode: 'onTouched',
    defaultValues: { role: '' },
  })

  useEffect(() => {
    if (!open) return
    reset()
    setSelectedLibraries([])
    setBarCode('')
    void regenerateBarcode()
  }, [open]) // eslint-disable-line react-hooks/exhaustive-deps

  const regenerateBarcode = async () => {
    try {
      const response = await api.get<{ bar_code: string }>(apiPaths.barcodeNext)
      setBarCode(response.bar_code)
    } catch {
      // ignore
    }
  }

  const toggleLibrary = (id: number) => {
    setSelectedLibraries((current) =>
      current.includes(id) ? current.filter((item) => item !== id) : [...current, id],
    )
  }

  const onSubmit = handleSubmit(async (values) => {
    setPending(true)
    try {
      await api.post(apiPaths.users, { ...values, bar_code: barCode, libraries: selectedLibraries })
      await queryClient.invalidateQueries({ queryKey: ['users'] })
      onCreated()
    } catch (error) {
      if (error instanceof ApiError) {
        const validationErrors = error.validationErrors
        if (validationErrors) {
          Object.entries(validationErrors).forEach(([field, messages]) => {
            setError(field as keyof FormValues, { message: messages[0] })
          })
        }
      }
    } finally {
      setPending(false)
    }
  })

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={t('users.formTitle')}
      description={t('users.formSubtitle')}
    >
      <form onSubmit={onSubmit} className="space-y-4" noValidate>
        <div className="grid gap-4 sm:grid-cols-2">
          <FormItem>
            <Label htmlFor="first_name">{t('fields.firstName')}</Label>
            <Input id="first_name" {...register('first_name')} aria-invalid={!!errors.first_name} />
            <FieldError message={errors.first_name?.message} />
          </FormItem>
          <FormItem>
            <Label htmlFor="last_name">{t('fields.lastName')}</Label>
            <Input id="last_name" {...register('last_name')} aria-invalid={!!errors.last_name} />
            <FieldError message={errors.last_name?.message} />
          </FormItem>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <FormItem>
            <Label htmlFor="username">
              {t('fields.username')}{' '}
              <span className="font-normal text-muted-foreground">({t('fields.optional')})</span>
            </Label>
            <Input id="username" {...register('username')} aria-invalid={!!errors.username} />
            <FieldError message={errors.username?.message} />
          </FormItem>
          <FormItem>
            <Label htmlFor="email">{t('fields.email')}</Label>
            <Input id="email" type="email" {...register('email')} aria-invalid={!!errors.email} />
            <FieldError message={errors.email?.message} />
          </FormItem>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <FormItem>
            <Label htmlFor="password">{t('fields.password')}</Label>
            <Input id="password" type="password" {...register('password')} aria-invalid={!!errors.password} />
            <FieldError message={errors.password?.message} />
          </FormItem>
          <FormItem>
            <Label htmlFor="role">{t('fields.role')}</Label>
            <Select id="role" {...register('role')} aria-invalid={!!errors.role}>
              <option value="">{t('fields.selectPlaceholder')}</option>
              {rolesQuery.data?.map((role) => (
                <option key={role.value} value={role.value}>
                  {role.label}
                </option>
              ))}
            </Select>
            <FieldError message={errors.role?.message} />
          </FormItem>
        </div>

        <FormItem>
          <Label>{t('fields.libraries')}</Label>
          {librariesQuery.isLoading ? (
            <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
          ) : (
            <div className="max-h-40 space-y-1 overflow-y-auto rounded-md border p-2">
              {librariesQuery.data?.length === 0 ? (
                <p className="px-2 py-1 text-sm text-muted-foreground">{t('libs.empty')}</p>
              ) : (
                librariesQuery.data?.map((library) => (
                  <label
                    key={library.id}
                    className="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-muted"
                  >
                    <input
                      type="checkbox"
                      className="size-4 accent-[#ffd968]"
                      checked={selectedLibraries.includes(library.id)}
                      onChange={() => toggleLibrary(library.id)}
                    />
                    {library.name}
                  </label>
                ))
              )}
            </div>
          )}
        </FormItem>

        <FormItem>
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
          <FieldHint>{t('users.barcodeAuto')}</FieldHint>
        </FormItem>

        <div className="flex justify-end gap-2 pt-2">
          <Button variant="outline" type="button" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" variant="brand" disabled={pending}>
            {t('common.save')}
          </Button>
        </div>
      </form>
    </Modal>
  )
}

export function UsersPage() {
  const { t } = useTranslation()
  const { user } = useAuth()
  const [search, setSearch] = useState('')
  const [appliedSearch, setAppliedSearch] = useState('')
  const [modalOpen, setModalOpen] = useState(false)
  const [notice, setNotice] = useState<string | null>(null)

  const usersQuery = useQuery({
    queryKey: ['users', appliedSearch],
    queryFn: async () => {
      const query = new URLSearchParams({ per_page: '100' })
      if (appliedSearch) query.set('search', appliedSearch)
      return api.get<PaginatedResponse<User>>(`${apiPaths.users}?${query.toString()}`)
    },
  })

  if (user?.role !== 'superadmin') {
    return <p className="text-sm text-muted-foreground">{t('errors.forbidden')}</p>
  }

  const showNotice = (message: string) => {
    setNotice(message)
    window.setTimeout(() => setNotice(null), 4000)
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 className="font-brand-heading text-2xl font-bold tracking-tight">{t('users.title')}</h2>
        <Button variant="brand" onClick={() => setModalOpen(true)}>
          <Plus />
          {t('users.add')}
        </Button>
      </div>

      {notice ? (
        <p className="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
          {notice}
        </p>
      ) : null}

      <form
        className="flex gap-2"
        onSubmit={(event) => {
          event.preventDefault()
          setAppliedSearch(search.trim())
        }}
      >
        <Input
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          placeholder={t('users.search')}
          className="max-w-xs"
        />
        <Button variant="outline" type="submit" aria-label={t('common.search')}>
          <Search />
        </Button>
      </form>

      {usersQuery.isLoading ? (
        <PageLoader />
      ) : (
        <div className="overflow-hidden rounded-lg border bg-card">
          <table className="min-w-full text-sm">
            <thead className="border-b bg-muted/50 text-muted-foreground">
              <tr>
                <th className="px-4 py-3 text-left font-medium">{t('users.columns.user')}</th>
                <th className="px-4 py-3 text-left font-medium">{t('users.columns.email')}</th>
                <th className="px-4 py-3 text-left font-medium">{t('users.columns.role')}</th>
                <th className="px-4 py-3 text-left font-medium">{t('users.columns.libraries')}</th>
                <th className="px-4 py-3 text-left font-medium">{t('users.columns.barcode')}</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {usersQuery.data?.data.map((item) => (
                <tr key={item.id} className="hover:bg-muted/40">
                  <td className="px-4 py-3">
                    <p className="font-medium">{item.name}</p>
                    {item.username ? <p className="text-xs text-muted-foreground">@{item.username}</p> : null}
                  </td>
                  <td className="px-4 py-3 text-muted-foreground">{item.email}</td>
                  <td className="px-4 py-3">
                    <Badge className="border-transparent bg-brand-soft text-brand">{item.role_label}</Badge>
                  </td>
                  <td className="px-4 py-3 text-muted-foreground">
                    {item.libraries?.map((library) => library.name).join(', ') || '—'}
                  </td>
                  <td className="px-4 py-3 font-mono text-xs text-muted-foreground">{item.bar_code ?? '—'}</td>
                </tr>
              ))}
              {!usersQuery.data?.data.length ? (
                <tr>
                  <td colSpan={5} className="px-4 py-10 text-center text-muted-foreground">
                    {t('users.empty')}
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      )}

      {modalOpen ? (
        <NewUserModal open={modalOpen} onClose={() => setModalOpen(false)} onCreated={() => showNotice(t('users.created'))} />
      ) : null}
    </div>
  )
}
