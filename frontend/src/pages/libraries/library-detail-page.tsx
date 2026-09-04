import { useState, type ReactNode } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { ArrowLeft, Building2, Pencil, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Card, CardContent } from '@/components/ui/card'
import { PageLoader } from '@/components/ui/loader'
import { LibraryFormModal } from '@/components/libraries/library-form-modal'
import { useAuth } from '@/hooks/useAuth'
import type { Library } from '@/types'

function InfoRow({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="grid grid-cols-[160px_1fr] gap-4 py-2">
      <dt className="text-sm font-medium text-muted-foreground">{label}</dt>
      <dd className="text-sm">{children || '—'}</dd>
    </div>
  )
}

export function LibraryDetailPage() {
  const { t } = useTranslation()
  const { can } = useAuth()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { id } = useParams<{ id: string }>()
  const libraryId = Number(id)
  const [editOpen, setEditOpen] = useState(false)
  const [deleteOpen, setDeleteOpen] = useState(false)
  const [isDeleting, setIsDeleting] = useState(false)

  const libraryQuery = useQuery({
    queryKey: ['libraries', libraryId],
    enabled: Number.isFinite(libraryId) && libraryId > 0,
    queryFn: async () => {
      const response = await api.get<{ data: Library }>(apiPaths.library(libraryId))
      return response.data
    },
  })

  if (!can('libraries.viewAny')) {
    return <p className="text-sm text-muted-foreground">{t('errors.forbidden')}</p>
  }

  if (libraryQuery.isLoading) return <PageLoader />

  if (libraryQuery.isError || !libraryQuery.data) {
    return (
      <div className="space-y-4">
        <BackToList />
        <p className="text-sm text-muted-foreground">{t('libs.notFound')}</p>
      </div>
    )
  }

  const target = libraryQuery.data

  const confirmDelete = async () => {
    setIsDeleting(true)
    try {
      await api.delete(apiPaths.library(target.id))
      toast.success(t('libs.deleted'), { description: target.name })
      navigate('/libraries')
    } catch (error) {
      if (error instanceof ApiError) {
        toast.error(error.messageText ?? t('errors.unexpected'))
      }
    } finally {
      setIsDeleting(false)
    }
  }

  return (
    <div className="space-y-6">
      <BackToList />

      <Card>
        <CardContent className="flex flex-col gap-4 p-6 sm:flex-row sm:items-start">
          <div className="flex size-12 shrink-0 items-center justify-center rounded-lg bg-brand-soft text-brand">
            <Building2 className="size-6" />
          </div>

          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <h2 className="font-brand-heading text-xl font-bold tracking-tight">{target.name}</h2>
              {target.deleted ? (
                <Badge variant="destructive">{t('libs.deletedBadge')}</Badge>
              ) : null}
            </div>
            <p className="text-sm text-muted-foreground">
              {target.place?.name}, {target.place?.region?.name}
            </p>

            <dl className="mt-4 max-w-2xl divide-y">
              <InfoRow label={t('fields.address')}>{target.address}</InfoRow>
              <InfoRow label={t('fields.place')}>{target.place?.name}</InfoRow>
              <InfoRow label={t('fields.region')}>{target.place?.region?.name}</InfoRow>
              <InfoRow label={t('fields.workTime')}>{target.work_time}</InfoRow>
            </dl>
          </div>

          <div className="flex shrink-0 gap-2">
            {target.can?.update ? (
              <Button variant="outline" onClick={() => setEditOpen(true)}>
                <Pencil />
                {t('common.edit')}
              </Button>
            ) : null}
            {target.can?.delete ? (
              <Button variant="outline" onClick={() => setDeleteOpen(true)}>
                <Trash2 />
                {t('common.delete')}
              </Button>
            ) : null}
          </div>
        </CardContent>
      </Card>

      <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('libs.deleteConfirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('libs.deleteConfirmText', { name: target.name })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={isDeleting}>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction variant="destructive" disabled={isDeleting} onClick={() => void confirmDelete()}>
              {t('common.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {editOpen ? (
        <LibraryFormModal
          open={editOpen}
          library={target}
          onClose={() => setEditOpen(false)}
          onSuccess={(name) => {
            toast.success(t('libs.updated'), { description: name })
            void queryClient.invalidateQueries({ queryKey: ['libraries'] })
          }}
        />
      ) : null}
    </div>
  )
}

function BackToList() {
  const { t } = useTranslation()
  return (
    <Link
      to="/libraries"
      className="inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
    >
      <ArrowLeft className="size-4" />
      {t('common.backToList')}
    </Link>
  )
}
