import { useEffect, useMemo, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useTranslation } from 'react-i18next'
import { Building2, Plus, Search } from 'lucide-react'
import { api, apiPaths, ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select } from '@/components/ui/select'
import { FieldError, FieldHint, FormItem } from '@/components/ui/form'
import { Modal } from '@/components/ui/modal'
import { PageLoader } from '@/components/ui/loader'
import { useAuth } from '@/hooks/useAuth'
import type { Library, PaginatedResponse, Place, Region } from '@/types'

interface NewLibraryFormValues {
  name: string
  region: string
  place: string
  address: string
  work_time: string
}

function NewLibraryModal({
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
  const [pending, setPending] = useState(false)

  const regionsQuery = useQuery({
    queryKey: ['regions'],
    queryFn: async () => {
      const response = await api.get<PaginatedResponse<Region>>(apiPaths.regions)
      return response.data
    },
  })

  const schema = useMemo(
    () =>
      z.object({
        name: z.string().trim().min(1, t('errors.required')),
        region: z.string().min(1, t('errors.required')),
        place: z.string().min(1, t('errors.required')),
        address: z.string().trim().min(1, t('errors.required')),
        work_time: z.string(),
      }),
    [t],
  )

  type FormValues = z.infer<typeof schema>

  const {
    register,
    handleSubmit,
    setError,
    reset,
    setValue,
    control,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { name: '', region: '', place: '', address: '', work_time: '' },
  })

  const selectedRegion = useWatch({ control, name: 'region' })

  const placesQuery = useQuery({
    queryKey: ['places', selectedRegion],
    enabled: !!selectedRegion,
    queryFn: async () => {
      const response = await api.get<PaginatedResponse<Place>>(`${apiPaths.places}?region_id=${selectedRegion}`)
      return response.data
    },
  })

  useEffect(() => {
    if (!open) return
    reset()
  }, [open]) // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    setValue('place', '')
  }, [selectedRegion, setValue])

  const onSubmit = handleSubmit(async (values) => {
    setPending(true)
    try {
      await api.post(apiPaths.libraries, {
        name: values.name,
        address: values.address,
        place_id: Number(values.place),
        work_time: values.work_time || null,
      })
      await queryClient.invalidateQueries({ queryKey: ['libraries'] })
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
    <Modal open={open} onClose={onClose} title={t('libs.formTitle')} description={t('libs.formSubtitle')}>
      <form onSubmit={onSubmit} className="space-y-4" noValidate>
        <FormItem>
          <Label htmlFor="name">{t('fields.name')}</Label>
          <Input id="name" {...register('name')} aria-invalid={!!errors.name} />
          <FieldError message={errors.name?.message} />
        </FormItem>

        <div className="grid gap-4 sm:grid-cols-2">
          <FormItem>
            <Label htmlFor="region">{t('fields.region')}</Label>
            <Select id="region" {...register('region')} aria-invalid={!!errors.region}>
              <option value="">{t('libs.regionPlaceholder')}</option>
              {regionsQuery.data?.map((region) => (
                <option key={region.id} value={String(region.id)}>
                  {region.name}
                </option>
              ))}
            </Select>
            <FieldError message={errors.region?.message} />
          </FormItem>

          <FormItem>
            <Label htmlFor="place">{t('fields.place')}</Label>
            <Select id="place" {...register('place')} disabled={!selectedRegion} aria-invalid={!!errors.place}>
              <option value="">{t('libs.placePlaceholder')}</option>
              {placesQuery.data?.map((place) => (
                <option key={place.id} value={String(place.id)}>
                  {place.name}
                </option>
              ))}
            </Select>
            <FieldError message={errors.place?.message} />
          </FormItem>
        </div>

        <FormItem>
          <Label htmlFor="address">{t('fields.address')}</Label>
          <Input id="address" {...register('address')} aria-invalid={!!errors.address} />
          <FieldError message={errors.address?.message} />
        </FormItem>

        <FormItem>
          <Label htmlFor="work_time">
            {t('fields.workTime')} <span className="font-normal text-muted-foreground">({t('fields.optional')})</span>
          </Label>
          <Input id="work_time" {...register('work_time')} />
          <FieldHint>{t('fields.selectPlaceholder')}</FieldHint>
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

export function LibrariesPage() {
  const { t } = useTranslation()
  const { user } = useAuth()
  const [search, setSearch] = useState('')
  const [appliedSearch, setAppliedSearch] = useState('')
  const [modalOpen, setModalOpen] = useState(false)
  const [notice, setNotice] = useState<string | null>(null)

  const librariesQuery = useQuery({
    queryKey: ['libraries', appliedSearch],
    queryFn: async () => {
      const query = new URLSearchParams({ per_page: '100' })
      if (appliedSearch) query.set('search', appliedSearch)
      return api.get<PaginatedResponse<Library>>(`${apiPaths.libraries}?${query.toString()}`)
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
        <h2 className="font-brand-heading text-2xl font-bold tracking-tight">{t('libs.title')}</h2>
        <Button variant="brand" onClick={() => setModalOpen(true)}>
          <Plus />
          {t('libs.add')}
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
          placeholder={t('libs.search')}
          className="max-w-xs"
        />
        <Button variant="outline" type="submit" aria-label={t('common.search')}>
          <Search />
        </Button>
      </form>

      {librariesQuery.isLoading ? (
        <PageLoader />
      ) : (
        <div className="overflow-hidden rounded-lg border bg-card">
          <table className="min-w-full text-sm">
            <thead className="border-b bg-muted/50 text-muted-foreground">
              <tr>
                <th className="px-4 py-3 text-left font-medium">{t('libs.columns.name')}</th>
                <th className="px-4 py-3 text-left font-medium">{t('libs.columns.place')}</th>
                <th className="px-4 py-3 text-left font-medium">{t('libs.columns.region')}</th>
                <th className="px-4 py-3 text-left font-medium">{t('libs.columns.address')}</th>
                <th className="px-4 py-3 text-left font-medium">{t('libs.columns.workTime')}</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {librariesQuery.data?.data.map((library) => (
                <tr key={library.id} className="hover:bg-muted/40">
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2 font-medium">
                      <Building2 className="size-4 text-muted-foreground" />
                      {library.name}
                    </div>
                  </td>
                  <td className="px-4 py-3">{library.place?.name ?? '—'}</td>
                  <td className="px-4 py-3 text-muted-foreground">{library.place?.region?.name ?? '—'}</td>
                  <td className="px-4 py-3 text-muted-foreground">{library.address}</td>
                  <td className="px-4 py-3 text-muted-foreground">{library.work_time ?? '—'}</td>
                </tr>
              ))}
              {!librariesQuery.data?.data.length ? (
                <tr>
                  <td colSpan={5} className="px-4 py-10 text-center text-muted-foreground">
                    {t('libs.empty')}
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      )}

      {modalOpen ? (
        <NewLibraryModal
          open={modalOpen}
          onClose={() => setModalOpen(false)}
          onCreated={() => showNotice(t('libs.created'))}
        />
      ) : null}
    </div>
  )
}
