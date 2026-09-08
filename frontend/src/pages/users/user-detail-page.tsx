import { useState, type ReactNode } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { ArrowLeft, CalendarClock, KeyRound, LibraryBig, Pencil, Tag, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths } from '@/lib/api'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { ChangePasswordDialog } from '@/components/profile/change-password-dialog'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { PageLoader } from '@/components/ui/loader'
import { UserFormModal } from '@/components/users/user-form-modal'
import { UserMembershipModal } from '@/components/users/user-membership-modal'
import { UserTagModal } from '@/components/users/user-tag-modal'
import { useAuth } from '@/hooks/useAuth'
import type { User } from '@/types'

function initialsOf(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean)
  const first = parts[0]?.[0] ?? '?'
  const last = parts.length > 1 ? parts[parts.length - 1][0] : ''
  return (first + last).toUpperCase()
}

function InfoRow({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="grid grid-cols-[160px_1fr] gap-4 py-2">
      <dt className="text-sm font-medium text-muted-foreground">{label}</dt>
      <dd className="text-sm">{children || '—'}</dd>
    </div>
  )
}

function ComingSoon({ icon, title, text }: { icon: ReactNode; title: string; text: string }) {
  return (
    <div className="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed py-14 text-center">
      <span className="text-muted-foreground">{icon}</span>
      <p className="text-sm font-medium text-foreground">{title}</p>
      <p className="max-w-sm text-sm text-muted-foreground">{text}</p>
    </div>
  )
}

export function UserDetailPage() {
  const { t } = useTranslation()
  const { can } = useAuth()
  const navigate = useNavigate()
  const { id } = useParams<{ id: string }>()
  const userId = Number(id)
  const queryClient = useQueryClient()
  const [editOpen, setEditOpen] = useState(false)
  const [membershipOpen, setMembershipOpen] = useState(false)
  const [passwordOpen, setPasswordOpen] = useState(false)
  const [tagOpen, setTagOpen] = useState(false)

  const userQuery = useQuery({
    queryKey: ['users', userId],
    enabled: Number.isFinite(userId) && userId > 0,
    queryFn: async () => {
      const response = await api.get<{ data: User }>(apiPaths.user(userId))
      return response.data
    },
  })

  if (!can('users.viewAny')) {
    return <p className="text-sm text-muted-foreground">{t('errors.forbidden')}</p>
  }

  if (userQuery.isLoading) return <PageLoader />

  if (userQuery.isError || !userQuery.data) {
    return (
      <div className="space-y-4">
        <Link
          to="/users"
          className="inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
        >
          <ArrowLeft className="size-4" />
          {t('common.backToList')}
        </Link>
        <p className="text-sm text-muted-foreground">{t('users.notFound')}</p>
      </div>
    )
  }

  const target = userQuery.data

  const handleMembershipSuccess = async (message: string) => {
    toast.success(message, { description: target.email })
    const result = await userQuery.refetch()
    if (result.error) {
      navigate('/users')
    }
  }

  const memberLibraries = (target.libraries ?? []).map((library) => library.name)
  const tagsByLibrary = (target.libraries ?? []).map((library) => ({
    library,
    tags: (target.tags ?? []).filter((tag) => tag.library_id === library.id),
  }))

  return (
    <div className="space-y-6">
      <Link
        to="/users"
        className="inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
      >
        <ArrowLeft className="size-4" />
        {t('common.backToList')}
      </Link>

      <Card>
        <CardContent className="flex flex-col gap-4 p-6 sm:flex-row sm:items-center">
          <Avatar className="size-20 text-xl">
            <AvatarFallback className="bg-brand-soft font-semibold text-brand">
              {initialsOf(target.name)}
            </AvatarFallback>
          </Avatar>

          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <h2 className="font-brand-heading text-xl font-bold tracking-tight">{target.name}</h2>
              <Badge className="border-transparent bg-brand-soft text-brand">{target.role_label}</Badge>
            </div>
            {target.username ? <p className="text-sm text-muted-foreground">@{target.username}</p> : null}
            <p className="text-sm text-muted-foreground">{target.email}</p>

            <div className="mt-2 flex flex-wrap items-center gap-x-5 gap-y-1 text-sm text-muted-foreground">
              <span className="inline-flex items-center gap-1.5 font-mono text-xs">{target.bar_code ?? '—'}</span>
              {memberLibraries.length > 0 ? (
                <span className="inline-flex items-center gap-1.5">
                  <LibraryBig className="size-4" />
                  {memberLibraries.join(', ')}
                </span>
              ) : null}
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-2 sm:shrink-0">
            {target.can?.update ? (
              <Button variant="outline" className="flex-1 sm:flex-none" onClick={() => setEditOpen(true)}>
                <Pencil />
                {t('common.edit')}
              </Button>
            ) : null}
            {target.can?.update ? (
              <Button variant="outline" className="flex-1 sm:flex-none" onClick={() => setTagOpen(true)}>
                <Tag />
                {t('tags.assignButton')}
              </Button>
            ) : null}
            {target.can?.update ? (
              <Button variant="outline" className="flex-1 sm:flex-none" onClick={() => setPasswordOpen(true)}>
                <KeyRound />
                {t('profile.changePassword')}
              </Button>
            ) : null}
            {target.can?.forceDelete || target.can?.delete || target.can?.removeMemberships ? (
              <Button
                variant="outline"
                className="flex-1 sm:flex-none"
                onClick={() => setMembershipOpen(true)}
              >
                <Trash2 />
                {t('common.delete')}
              </Button>
            ) : null}
          </div>
        </CardContent>
      </Card>

      <Tabs defaultValue="info">
        <TabsList>
          <TabsTrigger value="info">{t('users.tabs.info')}</TabsTrigger>
          <TabsTrigger value="reservations">{t('users.tabs.reservations')}</TabsTrigger>
          <TabsTrigger value="borrows">{t('users.tabs.borrows')}</TabsTrigger>
        </TabsList>

        <TabsContent value="info">
          <Card>
            <CardHeader>
              <CardTitle className="font-brand-heading text-base">{t('users.tabs.info')}</CardTitle>
            </CardHeader>
            <CardContent className="divide-y">
              <dl>
                <InfoRow label={t('fields.firstName')}>{target.first_name ?? target.name}</InfoRow>
                <InfoRow label={t('fields.lastName')}>{target.last_name}</InfoRow>
                <InfoRow label={t('fields.email')}>{target.email}</InfoRow>
                <InfoRow label={t('fields.username')}>{target.username ? `@${target.username}` : null}</InfoRow>
                <InfoRow label={t('fields.role')}>{target.role_label}</InfoRow>
                <InfoRow label={t('fields.barcode')}>
                  <span className="font-mono">{target.bar_code ?? '—'}</span>
                </InfoRow>
                <InfoRow label={t('fields.jmbg')}>{target.jmbg}</InfoRow>
                <InfoRow label={t('fields.address')}>{target.address}</InfoRow>
                <InfoRow label={t('fields.city')}>{target.city}</InfoRow>
                <InfoRow label={t('fields.postCode')}>{target.post_code}</InfoRow>
                <InfoRow label={t('fields.libraries')}>
                  {memberLibraries.length > 0 ? memberLibraries.join(', ') : null}
                </InfoRow>
                <InfoRow label={t('fields.tags')}>
                  {tagsByLibrary.some(({ tags }) => tags.length > 0) ? (
                    <div className="space-y-1.5">
                      {tagsByLibrary.map(({ library, tags }) => (
                        <div key={library.id} className="flex flex-wrap items-center gap-1.5">
                          <span className="text-xs font-medium text-muted-foreground">{library.name}:</span>
                          {tags.length > 0 ? (
                            tags.map((tag) => (
                              <Badge key={tag.id} variant="secondary" className="border-transparent">
                                {tag.name}
                              </Badge>
                            ))
                          ) : (
                            <span className="text-xs text-muted-foreground">—</span>
                          )}
                        </div>
                      ))}
                    </div>
                  ) : null}
                </InfoRow>
              </dl>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="reservations">
          <ComingSoon
            icon={<CalendarClock className="size-10" />}
            title={t('users.tabs.reservations')}
            text={t('users.soonText')}
          />
        </TabsContent>

        <TabsContent value="borrows">
          <ComingSoon
            icon={<LibraryBig className="size-10" />}
            title={t('users.tabs.borrows')}
            text={t('users.soonText')}
          />
        </TabsContent>
      </Tabs>

      {editOpen ? (
        <UserFormModal
          open={editOpen}
          user={target}
          onClose={() => setEditOpen(false)}
          onSuccess={(email) => {
            toast.success(t('users.updated'), { description: email })
            void queryClient.invalidateQueries({ queryKey: ['users'] })
          }}
        />
      ) : null}

      {membershipOpen ? (
        <UserMembershipModal
          open={membershipOpen}
          user={target}
          mode="delete"
          onClose={() => setMembershipOpen(false)}
          onSuccess={(message) => void handleMembershipSuccess(message)}
        />
      ) : null}

      {tagOpen ? (
        <UserTagModal
          open={tagOpen}
          user={target}
          onClose={() => setTagOpen(false)}
          onSuccess={(message) => {
            toast.success(message)
            void queryClient.invalidateQueries({ queryKey: ['users'] })
          }}
        />
      ) : null}

      <ChangePasswordDialog open={passwordOpen} onClose={() => setPasswordOpen(false)} target={target} />
    </div>
  )
}
