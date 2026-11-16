import { useEffect, useMemo, useRef, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Check, ClipboardList, Eye, Search, X } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { PageLoader } from '@/components/ui/loader'
import { PaginationBar } from '@/components/pagination-bar'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useAuth } from '@/hooks/useAuth'
import type { Library, LibraryRequest, LibraryRequestStatus, PaginatedResponse, Place, Region } from '@/types'

const PAGE_SIZE = 15

function statusVariant(status: LibraryRequestStatus): 'default' | 'secondary' | 'destructive' {
  if (status === 'approved') return 'default'
  if (status === 'rejected') return 'destructive'
  return 'secondary'
}

function Detail({ label, value }: { label: string; value: string | number | null | undefined }) {
  return (
    <div className="space-y-1">
      <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
      <p className="text-sm">{value ?? '—'}</p>
    </div>
  )
}

export function LibraryRequestsPage() {
  const { t } = useTranslation()
  const { user } = useAuth()
  const queryClient = useQueryClient()

  const [page, setPage] = useState(1)
  const [status, setStatus] = useState<'all' | LibraryRequestStatus>('all')
  const [search, setSearch] = useState('')
  const [searchInput, setSearchInput] = useState('')

  const [selected, setSelected] = useState<LibraryRequest | null>(null)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [name, setName] = useState('')
  const [address, setAddress] = useState('')
  const [region, setRegion] = useState('')
  const [place, setPlace] = useState('')
  const [workTime, setWorkTime] = useState('')
  const [invNumberAuto, setInvNumberAuto] = useState(true)
  const autoMatchedFor = useRef<number | null>(null)

  const isSuperAdmin = user?.is_super_admin === true

  const requestsQuery = useQuery({
    queryKey: ['library-requests', { page, status, search }],
    enabled: isSuperAdmin,
    queryFn: async () => {
      const params = new URLSearchParams({ per_page: String(PAGE_SIZE), page: String(page) })
      if (status !== 'all') params.set('status', status)
      if (search) params.set('search', search)
      return api.get<PaginatedResponse<LibraryRequest>>(`${apiPaths.libraryRequests}?${params.toString()}`)
    },
  })

  const regionsQuery = useQuery({
    queryKey: ['regions', 'all'],
    enabled: isSuperAdmin && dialogOpen,
    queryFn: async () => api.get<PaginatedResponse<Region>>(`${apiPaths.regions}?all=1`),
  })

  const cityPlacesQuery = useQuery({
    queryKey: ['places', 'city', selected?.city],
    enabled: isSuperAdmin && dialogOpen && Boolean(selected?.city) && selected?.status === 'pending',
    queryFn: async () => api.get<PaginatedResponse<Place>>(`${apiPaths.places}?all=1&search=${encodeURIComponent(selected?.city ?? '')}`),
  })

  const placesQuery = useQuery({
    queryKey: ['places', 'region', region],
    enabled: isSuperAdmin && dialogOpen && Boolean(region),
    queryFn: async () => api.get<PaginatedResponse<Place>>(`${apiPaths.places}?all=1&region_id=${region}`),
  })

  useEffect(() => {
    if (!selected) return

    setName(selected.school_name)
    setAddress(selected.address)
    setRegion('')
    setPlace('')
    setWorkTime('')
    setInvNumberAuto(true)
    autoMatchedFor.current = null
  }, [selected])

  useEffect(() => {
    if (!dialogOpen || !selected || selected.status !== 'pending' || autoMatchedFor.current === selected.id) {
      return
    }

    const matches = cityPlacesQuery.data?.data ?? []

    if (matches.length === 1) {
      setRegion(String(matches[0].region_id))
      setPlace(String(matches[0].id))
      autoMatchedFor.current = selected.id
    }
  }, [cityPlacesQuery.data, dialogOpen, selected])

  const approveMutation = useMutation({
    mutationFn: async () => {
      if (!selected) return
      return api.post<Library>(apiPaths.libraryRequestApprove(selected.id), {
        name,
        address,
        place_id: Number(place),
        work_time: workTime || null,
        inv_number_auto: invNumberAuto,
      })
    },
    onSuccess: async () => {
      toast.success(t('libraryRequests.approveSuccess'), { description: name })
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['library-requests'] }),
        queryClient.invalidateQueries({ queryKey: ['libraries'] }),
      ])
      closeDialog()
    },
    onError: (error) => {
      if (error instanceof ApiError) {
        toast.error(error.messageText ?? t('errors.unexpected'))
      } else {
        toast.error(t('errors.unexpected'))
      }
    },
  })

  const rejectMutation = useMutation({
    mutationFn: async () => {
      if (!selected) return
      return api.post(apiPaths.libraryRequestReject(selected.id))
    },
    onSuccess: async () => {
      toast.success(t('libraryRequests.rejectSuccess'))
      await queryClient.invalidateQueries({ queryKey: ['library-requests'] })
      closeDialog()
    },
    onError: (error) => {
      if (error instanceof ApiError) {
        toast.error(error.messageText ?? t('errors.unexpected'))
      } else {
        toast.error(t('errors.unexpected'))
      }
    },
  })

  const lastPage = requestsQuery.data?.meta.last_page ?? 1
  const requests = requestsQuery.data?.data ?? []
  const places = useMemo(() => placesQuery.data?.data ?? [], [placesQuery.data])

  const openRequest = (request: LibraryRequest) => {
    setSelected(request)
    setDialogOpen(true)
  }

  const closeDialog = () => {
    setDialogOpen(false)
    setSelected(null)
  }

  const submitSearch = () => {
    setSearch(searchInput.trim())
    setPage(1)
  }

  if (!isSuperAdmin) {
    return <p className="text-sm text-muted-foreground">{t('errors.forbidden')}</p>
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="font-brand-heading flex items-center gap-2 text-2xl font-bold tracking-tight">
          <ClipboardList className="size-6 text-brand" />
          {t('libraryRequests.title')}
        </h2>
        <p className="mt-1 text-sm text-muted-foreground">{t('libraryRequests.subtitle')}</p>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <form
          className="flex gap-2"
          onSubmit={(event) => {
            event.preventDefault()
            submitSearch()
          }}
        >
          <Input
            value={searchInput}
            onChange={(event) => setSearchInput(event.target.value)}
            placeholder={t('libraryRequests.searchPlaceholder')}
            className="max-w-xs"
          />
          <Button variant="outline" type="submit" aria-label={t('common.search')}>
            <Search />
          </Button>
        </form>

        <Select
          value={status}
          onValueChange={(value) => {
            setStatus(value as 'all' | LibraryRequestStatus)
            setPage(1)
          }}
        >
          <SelectTrigger className="w-52">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">{t('libraryRequests.allStatuses')}</SelectItem>
            <SelectItem value="pending">{t('libraryRequests.status.pending')}</SelectItem>
            <SelectItem value="approved">{t('libraryRequests.status.approved')}</SelectItem>
            <SelectItem value="rejected">{t('libraryRequests.status.rejected')}</SelectItem>
          </SelectContent>
        </Select>
      </div>

      {requestsQuery.isLoading ? (
        <PageLoader />
      ) : (
        <div className="rounded-lg border bg-card">
          <div className="hidden lg:block">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="px-4">{t('libraryRequests.columns.school')}</TableHead>
                  <TableHead className="px-4">{t('libraryRequests.columns.city')}</TableHead>
                  <TableHead className="px-4">{t('libraryRequests.columns.contact')}</TableHead>
                  <TableHead className="px-4">{t('libraryRequests.columns.created')}</TableHead>
                  <TableHead className="px-4">{t('libraryRequests.columns.status')}</TableHead>
                  <TableHead className="px-4 text-right">{t('common.actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {requests.map((request) => (
                  <TableRow key={request.id}>
                    <TableCell className="px-4">
                      <p className="font-medium">{request.school_name}</p>
                      <p className="text-xs text-muted-foreground">{request.school_type_label}</p>
                    </TableCell>
                    <TableCell className="px-4 text-muted-foreground">{request.city}</TableCell>
                    <TableCell className="px-4">
                      <p>{request.contact_name}</p>
                      <p className="text-xs text-muted-foreground">{request.contact_email}</p>
                    </TableCell>
                    <TableCell className="px-4 text-muted-foreground">{request.created_at_formatted}</TableCell>
                    <TableCell className="px-4">
                      <Badge variant={statusVariant(request.status)}>{request.status_label}</Badge>
                    </TableCell>
                    <TableCell className="px-4 text-right">
                      <Button variant="ghost" size="icon-sm" aria-label={t('libraryRequests.open')} onClick={() => openRequest(request)}>
                        <Eye />
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
                {requests.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                      {t('libraryRequests.empty')}
                    </TableCell>
                  </TableRow>
                ) : null}
              </TableBody>
            </Table>
          </div>

          <div className="divide-y lg:hidden">
            {requests.map((request) => (
              <button
                key={request.id}
                type="button"
                onClick={() => openRequest(request)}
                className="flex w-full items-start justify-between gap-3 p-4 text-left transition-colors hover:bg-muted"
              >
                <span className="min-w-0 space-y-1">
                  <span className="block font-medium">{request.school_name}</span>
                  <span className="block text-xs text-muted-foreground">
                    {request.school_type_label} · {request.city}
                  </span>
                  <span className="block text-xs text-muted-foreground">
                    {request.contact_name} · {request.created_at_formatted}
                  </span>
                </span>
                <Badge variant={statusVariant(request.status)}>{request.status_label}</Badge>
              </button>
            ))}
            {requests.length === 0 ? (
              <p className="p-6 text-center text-sm text-muted-foreground">{t('libraryRequests.empty')}</p>
            ) : null}
          </div>

          {requestsQuery.data && requestsQuery.data.meta.total > 0 ? (
            <PaginationBar
              currentPage={requestsQuery.data.meta.current_page}
              lastPage={lastPage}
              total={requestsQuery.data.meta.total}
              perPage={requestsQuery.data.meta.per_page}
              onPageChange={setPage}
            />
          ) : null}
        </div>
      )}

      <Dialog open={dialogOpen} onOpenChange={(value) => !value && closeDialog()}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
          {selected ? (
            <>
              <DialogHeader>
                <DialogTitle className="font-brand-heading">{selected.school_name}</DialogTitle>
                <DialogDescription>
                  {selected.school_type_label} · {selected.city} · {selected.created_at_formatted}
                </DialogDescription>
              </DialogHeader>

              <div className="grid gap-4 sm:grid-cols-2">
                <Detail label={t('libraryRequests.columns.school')} value={selected.school_name} />
                <Detail label={t('libraryRequests.columns.type')} value={selected.school_type_label} />
                <Detail label={t('libraryRequests.columns.city')} value={selected.city} />
                <Detail label={t('libraryRequests.libraryAddress')} value={selected.address} />
                <Detail label={t('libraryRequests.contact')} value={`${selected.contact_name} · ${selected.contact_phone}`} />
                <Detail label={t('libraryRequests.email')} value={selected.contact_email} />
                <Detail label={t('libraryRequests.students')} value={selected.student_count} />
                <Detail label={t('libraryRequests.employees')} value={selected.employee_count} />
              </div>

              {selected.message ? (
                <div className="space-y-1">
                  <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    {t('libraryRequests.message')}
                  </p>
                  <p className="whitespace-pre-line text-sm">{selected.message}</p>
                </div>
              ) : null}

              <div className="flex flex-wrap items-center gap-3 border-t pt-4">
                <Badge variant={statusVariant(selected.status)}>{selected.status_label}</Badge>
                {selected.handled_by ? (
                  <p className="text-xs text-muted-foreground">
                    {t('libraryRequests.handledBy')}: {selected.handled_by}
                    {selected.handled_at_formatted ? ` · ${selected.handled_at_formatted}` : ''}
                  </p>
                ) : null}
              </div>

              {selected.status === 'pending' ? (
                <div className="space-y-4 border-t pt-4">
                  <div>
                    <h3 className="font-brand-heading text-lg font-semibold">{t('libraryRequests.approveTitle')}</h3>
                    <p className="text-sm text-muted-foreground">{t('libraryRequests.approveSubtitle')}</p>
                  </div>

                  <div className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-2 sm:col-span-2">
                      <Label htmlFor="request-library-name">{t('libraryRequests.libraryName')}</Label>
                      <Input id="request-library-name" value={name} onChange={(event) => setName(event.target.value)} />
                    </div>
                    <div className="space-y-2 sm:col-span-2">
                      <Label htmlFor="request-library-address">{t('libraryRequests.libraryAddress')}</Label>
                      <Input id="request-library-address" value={address} onChange={(event) => setAddress(event.target.value)} />
                    </div>
                    <div className="space-y-2">
                      <Label>{t('libraryRequests.region')}</Label>
                      <Select
                        value={region || undefined}
                        onValueChange={(value) => {
                          setRegion(value)
                          setPlace('')
                        }}
                      >
                        <SelectTrigger className="w-full">
                          <SelectValue placeholder={t('libraryRequests.selectRegion')} />
                        </SelectTrigger>
                        <SelectContent>
                          {regionsQuery.data?.data.map((item) => (
                            <SelectItem key={item.id} value={String(item.id)}>
                              {item.name}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                    <div className="space-y-2">
                      <Label>{t('libraryRequests.place')}</Label>
                      <Select value={place || undefined} onValueChange={setPlace} disabled={!region || placesQuery.isLoading}>
                        <SelectTrigger className="w-full">
                          <SelectValue placeholder={t('libraryRequests.selectPlace')} />
                        </SelectTrigger>
                        <SelectContent>
                          {places.map((item) => (
                            <SelectItem key={item.id} value={String(item.id)}>
                              {item.name}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="request-library-work-time">{t('libraryRequests.workTime')}</Label>
                      <Input
                        id="request-library-work-time"
                        value={workTime}
                        onChange={(event) => setWorkTime(event.target.value)}
                        placeholder={t('libraryRequests.workTimePlaceholder')}
                      />
                    </div>
                    <div className="flex items-end gap-2 pb-1">
                      <Checkbox
                        id="request-inv-auto"
                        checked={invNumberAuto}
                        onCheckedChange={(value) => setInvNumberAuto(value === true)}
                      />
                      <Label htmlFor="request-inv-auto" className="font-normal">
                        {t('libraryRequests.invNumberAuto')}
                      </Label>
                    </div>
                  </div>

                  <p className="text-xs text-muted-foreground">
                    {t('libraryRequests.placeHint', { city: selected.city })}
                  </p>

                  <div className="flex flex-wrap justify-end gap-2">
                    <Button
                      type="button"
                      variant="outline"
                      disabled={rejectMutation.isPending}
                      onClick={() => rejectMutation.mutate()}
                    >
                      <X />
                      {t('libraryRequests.reject')}
                    </Button>
                    <Button
                      type="button"
                      variant="brand"
                      disabled={approveMutation.isPending || !place || !region || !name.trim() || !address.trim()}
                      onClick={() => approveMutation.mutate()}
                    >
                      <Check />
                      {t('libraryRequests.approve')}
                    </Button>
                  </div>
                </div>
              ) : null}
            </>
          ) : null}
        </DialogContent>
      </Dialog>
    </div>
  )
}
