import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Search, ShieldCheck, X } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { useAuth } from '@/hooks/useAuth'
import type { AppSettings, SuperAdminUser } from '@/types'

export function SuperAdminSettingsCard() {
  const { t } = useTranslation()
  const { user } = useAuth()
  const queryClient = useQueryClient()
  const isSuperAdmin = user?.is_super_admin === true

  const [selected, setSelected] = useState<SuperAdminUser[]>([])
  const [search, setSearch] = useState('')

  const settingsQuery = useQuery({
    queryKey: ['settings'],
    enabled: isSuperAdmin,
    queryFn: () => api.get<{ data: AppSettings }>(apiPaths.settings),
  })

  useEffect(() => {
    if (settingsQuery.data) {
      setSelected(settingsQuery.data.data.super_admins)
    }
  }, [settingsQuery.data])

  const candidatesQuery = useQuery({
    queryKey: ['settings', 'users', search],
    enabled: isSuperAdmin,
    queryFn: () => api.get<{ data: SuperAdminUser[] }>(apiPaths.settingsUsers(search)),
  })

  const selectedIds = useMemo(() => new Set(selected.map((item) => item.id)), [selected])

  const saveMutation = useMutation({
    mutationFn: () =>
      api.put<{ data: AppSettings }>(apiPaths.settings, {
        super_admin_user_ids: selected.map((item) => item.id),
      }),
    onSuccess: async (response) => {
      setSelected(response.data.super_admins)
      await queryClient.invalidateQueries({ queryKey: ['settings'] })
      toast.success(t('settings.superAdmins.updated'))
    },
    onError: (error) => {
      if (error instanceof ApiError) {
        toast.error(error.messageText ?? t('errors.unexpected'))
      }
    },
  })

  if (!isSuperAdmin) return null

  const add = (candidate: SuperAdminUser) => {
    setSelected((current) =>
      current.some((item) => item.id === candidate.id) ? current : [...current, candidate],
    )
  }

  const remove = (id: number) => {
    setSelected((current) => current.filter((item) => item.id !== id))
  }

  const candidates = (candidatesQuery.data?.data ?? []).filter((candidate) => !selectedIds.has(candidate.id))
  const logs = settingsQuery.data?.data.logs ?? []

  return (
    <Card>
      <CardHeader>
        <CardTitle className="font-brand-heading flex items-center gap-2 text-base">
          <ShieldCheck className="size-4 text-brand" />
          {t('settings.superAdmins.title')}
        </CardTitle>
        <CardDescription>{t('settings.superAdmins.description')}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="space-y-2">
          <p className="text-sm font-medium">{t('settings.superAdmins.selected')}</p>
          {selected.length === 0 ? (
            <p className="text-sm text-muted-foreground">{t('settings.superAdmins.empty')}</p>
          ) : (
            <div className="space-y-2">
              {selected.map((admin) => (
                <div key={admin.id} className="flex items-center gap-3 rounded-md border px-3 py-2">
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium">{admin.name}</p>
                    <p className="truncate text-xs text-muted-foreground">{admin.email}</p>
                  </div>
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label={t('settings.superAdmins.remove')}
                    onClick={() => remove(admin.id)}
                  >
                    <X />
                  </Button>
                </div>
              ))}
            </div>
          )}
        </div>

        <div className="space-y-2">
          <p className="text-sm font-medium">{t('settings.superAdmins.add')}</p>
          <div className="relative">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder={t('settings.superAdmins.searchPlaceholder')}
              className="pl-9"
            />
          </div>

          <div className="max-h-56 space-y-1 overflow-y-auto">
            {candidatesQuery.isLoading ? (
              <p className="px-1 py-2 text-sm text-muted-foreground">{t('common.loading')}</p>
            ) : candidates.length === 0 ? (
              <p className="px-1 py-2 text-sm text-muted-foreground">{t('settings.superAdmins.noResults')}</p>
            ) : (
              candidates.map((candidate) => (
                <button
                  key={candidate.id}
                  type="button"
                  onClick={() => add(candidate)}
                  className="flex w-full items-center justify-between gap-3 rounded-md px-2 py-1.5 text-left transition-colors hover:bg-muted"
                >
                  <span className="min-w-0">
                    <span className="block truncate text-sm">{candidate.name}</span>
                    <span className="block truncate text-xs text-muted-foreground">{candidate.email}</span>
                  </span>
                  <span className="text-xs font-medium text-brand">{t('common.add')}</span>
                </button>
              ))
            )}
          </div>
        </div>

        <div className="flex justify-end">
          <Button
            type="button"
            variant="brand"
            disabled={selected.length === 0 || saveMutation.isPending}
            onClick={() => saveMutation.mutate()}
          >
            {t('common.save')}
          </Button>
        </div>

        <div className="space-y-2 border-t pt-4">
          <p className="text-sm font-medium">{t('settings.superAdmins.history')}</p>
          {logs.length === 0 ? (
            <p className="text-sm text-muted-foreground">{t('settings.superAdmins.noLogs')}</p>
          ) : (
            <ul className="space-y-1.5 text-sm">
              {logs.map((log) => (
                <li key={log.id} className="flex flex-wrap items-center justify-between gap-2 rounded-md bg-muted/40 px-3 py-2">
                  <span className="text-muted-foreground">
                    {log.changed_by ?? t('settings.superAdmins.system')} · {log.key}
                  </span>
                  <span className="text-xs text-muted-foreground">{log.created_at_formatted ?? '—'}</span>
                </li>
              ))}
            </ul>
          )}
        </div>
      </CardContent>
    </Card>
  )
}
