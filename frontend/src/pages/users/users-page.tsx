import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Eye, Pencil, Plus, Search, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
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
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { PageLoader } from '@/components/ui/loader'
import { PaginationBar } from '@/components/pagination-bar'
import { UserFormModal } from '@/components/users/user-form-modal'
import { useAuth } from '@/hooks/useAuth'
import type { PaginatedResponse, User } from '@/types'

const PAGE_SIZE = 10

function useUsersQuery(page: number, search: string) {
  return useQuery({
    queryKey: ['users', { page, search }],
    queryFn: async () => {
      const query = new URLSearchParams({ per_page: String(PAGE_SIZE), page: String(page) })
      if (search) query.set('search', search)
      return api.get<PaginatedResponse<User>>(`${apiPaths.users}?${query.toString()}`)
    },
  })
}

export function UsersPage() {
  const { t } = useTranslation()
  const { user } = useAuth()
  const [searchParams, setSearchParams] = useSearchParams()

  const page = Math.max(1, Number(searchParams.get('page')) || 1)
  const search = searchParams.get('q') ?? ''

  const [searchInput, setSearchInput] = useState(search)
  const [modalOpen, setModalOpen] = useState(false)
  const [editingUser, setEditingUser] = useState<User | null>(null)
  const [deletingUser, setDeletingUser] = useState<User | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)

  const usersQuery = useUsersQuery(page, search)

  if (user?.role !== 'superadmin') {
    return <p className="text-sm text-muted-foreground">{t('errors.forbidden')}</p>
  }

  const setParams = (patch: Record<string, string | number | null>) => {
    setSearchParams(
      (previous) => {
        const next = new URLSearchParams(previous)
        Object.entries(patch).forEach(([key, value]) => {
          if (value === null || value === '') next.delete(key)
          else next.set(key, String(value))
        })
        return next
      },
      { replace: true },
    )
  }

  const applySearch = (value: string) => {
    setSearchInput(value)
    setParams({ q: value.trim() || null, page: null })
  }

  const confirmDelete = async () => {
    if (!deletingUser) return
    setIsDeleting(true)
    try {
      await api.delete(apiPaths.user(deletingUser.id))
      const nextPage =
        usersQuery.data && usersQuery.data.data.length === 1 && page > 1 ? page - 1 : page
      setParams({ page: nextPage === page ? page : nextPage })
      await usersQuery.refetch()
      toast.success(t('users.deleted'), { description: deletingUser.email })
      setDeletingUser(null)
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
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 className="font-brand-heading text-2xl font-bold tracking-tight">{t('users.title')}</h2>
        <Button
          variant="brand"
          onClick={() => {
            setEditingUser(null)
            setModalOpen(true)
          }}
        >
          <Plus />
          {t('users.add')}
        </Button>
      </div>

      <form
        className="flex gap-2"
        onSubmit={(event) => {
          event.preventDefault()
          applySearch(searchInput)
        }}
      >
        <Input
          value={searchInput}
          onChange={(event) => setSearchInput(event.target.value)}
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
        <div className="rounded-lg border bg-card">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="px-4">{t('users.columns.user')}</TableHead>
                <TableHead className="px-4">{t('users.columns.email')}</TableHead>
                <TableHead className="px-4">{t('users.columns.role')}</TableHead>
                <TableHead className="px-4">{t('users.columns.libraries')}</TableHead>
                <TableHead className="px-4">{t('users.columns.barcode')}</TableHead>
                <TableHead className="px-4 text-right">{t('common.actions')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {usersQuery.data?.data.map((item) => (
                <TableRow key={item.id}>
                  <TableCell className="px-4">
                    <Link to={`/users/${item.id}`} className="font-medium hover:underline">
                      {item.name}
                    </Link>
                    {item.username ? <p className="text-xs text-muted-foreground">@{item.username}</p> : null}
                  </TableCell>
                  <TableCell className="px-4 text-muted-foreground">{item.email}</TableCell>
                  <TableCell className="px-4">
                    <Badge className="border-transparent bg-brand-soft text-brand">{item.role_label}</Badge>
                  </TableCell>
                  <TableCell className="px-4 text-muted-foreground">
                    {item.libraries?.map((library) => library.name).join(', ') || '—'}
                  </TableCell>
                  <TableCell className="px-4 font-mono text-xs text-muted-foreground">{item.bar_code ?? '—'}</TableCell>
                  <TableCell className="px-4">
                    <div className="flex items-center justify-end gap-1">
                      <Button variant="ghost" size="icon-sm" asChild aria-label={t('common.view')}>
                        <Link to={`/users/${item.id}`}>
                          <Eye />
                        </Link>
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label={t('common.edit')}
                        onClick={() => {
                          setEditingUser(item)
                          setModalOpen(true)
                        }}
                      >
                        <Pencil />
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label={t('common.delete')}
                        onClick={() => setDeletingUser(item)}
                      >
                        <Trash2 />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
              {!usersQuery.data?.data.length ? (
                <TableRow>
                  <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                    {t('users.empty')}
                  </TableCell>
                </TableRow>
              ) : null}
            </TableBody>
          </Table>

          {usersQuery.data && usersQuery.data.meta.total > 0 ? (
            <PaginationBar
              currentPage={usersQuery.data.meta.current_page}
              lastPage={usersQuery.data.meta.last_page}
              total={usersQuery.data.meta.total}
              perPage={usersQuery.data.meta.per_page}
              onPageChange={(nextPage) => setParams({ page: nextPage })}
            />
          ) : null}
        </div>
      )}

      {modalOpen ? (
        <UserFormModal
          open={modalOpen}
          user={editingUser}
          onClose={() => {
            setModalOpen(false)
            setEditingUser(null)
          }}
          onSuccess={(label) =>
            toast.success(editingUser ? t('users.updated') : t('users.created'), { description: label })
          }
        />
      ) : null}

      <AlertDialog open={Boolean(deletingUser)} onOpenChange={(value) => !value && setDeletingUser(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('users.deleteConfirmTitle')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('users.deleteConfirmText', { name: deletingUser?.name ?? '' })}
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
    </div>
  )
}
