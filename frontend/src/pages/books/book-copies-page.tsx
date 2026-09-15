import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Eye, Search } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths } from '@/lib/api'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { PageLoader } from '@/components/ui/loader'
import { PaginationBar } from '@/components/pagination-bar'
import { useAuth } from '@/hooks/useAuth'
import type { BookCopy, BookCopyStatus, PaginatedResponse } from '@/types'

const PAGE_SIZE = 25

const STATUS_VARIANTS: Record<BookCopyStatus, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  available: 'default',
  borrowed: 'secondary',
  record_error: 'outline',
  written_off: 'destructive',
  archived: 'secondary',
}

export function BookCopiesPage() {
  const { t } = useTranslation()
  const { can, activeLibrary, isLoading } = useAuth()
  const [searchParams, setSearchParams] = useSearchParams()

  const page = Math.max(1, Number(searchParams.get('page')) || 1)
  const barcode = searchParams.get('barcode') ?? ''
  const orderNumber = searchParams.get('order_number') ?? ''
  const search = searchParams.get('search') ?? ''
  const recError = searchParams.get('rec_error') ?? ''
  const libraryId = activeLibrary?.id ?? null

  const [barcodeInput, setBarcodeInput] = useState(barcode)
  const [orderNumberInput, setOrderNumberInput] = useState(orderNumber)
  const [searchInput, setSearchInput] = useState(search)

  const copiesQuery = useQuery({
    queryKey: ['book-copies', { page, barcode, orderNumber, search, recError, libraryId }],
    queryFn: async () => {
      const query = new URLSearchParams({ per_page: String(PAGE_SIZE), page: String(page) })
      if (barcode) query.set('barcode', barcode)
      if (orderNumber) query.set('order_number', orderNumber)
      if (search) query.set('search', search)
      if (recError !== '') query.set('rec_error', recError)
      if (libraryId !== null) query.set('library_id', String(libraryId))
      return api.get<PaginatedResponse<BookCopy>>(`${apiPaths.bookCopiesList}?${query.toString()}`)
    },
    enabled: libraryId !== null,
  })

  if (isLoading) {
    return <PageLoader />
  }

  if (!can('book_copies.viewAny')) {
    return <p className="text-sm text-muted-foreground">{t('errors.forbidden')}</p>
  }

  if (libraryId === null) {
    return <p className="text-sm text-muted-foreground">{t('books.activeLibraryRequired')}</p>
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

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 className="font-brand-heading text-2xl font-bold tracking-tight">{t('books.copies.listTitle')}</h2>
        <div className="flex items-center gap-2">
          <select
            className="flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
            value={recError}
            onChange={(event) => setParams({ rec_error: event.target.value, page: null })}
          >
            <option value="">{t('books.copies.recErrorAll')}</option>
            <option value="1">{t('books.copies.recErrorOnly')}</option>
            <option value="0">{t('books.copies.recErrorNone')}</option>
          </select>
        </div>
      </div>

      <div className="flex flex-col gap-2 sm:flex-row sm:items-start">
        <form
          className="flex flex-col gap-2 sm:flex-row"
          onSubmit={(event) => {
            event.preventDefault()
            setParams({
              barcode: barcodeInput.trim() || null,
              order_number: orderNumberInput.trim() || null,
              search: searchInput.trim() || null,
              page: null,
            })
          }}
        >
          <Input
            value={orderNumberInput}
            onChange={(event) => setOrderNumberInput(event.target.value)}
            placeholder={t('books.copies.searchOrderNumber')}
            className="sm:max-w-xs"
          />
          <Input
            value={searchInput}
            onChange={(event) => setSearchInput(event.target.value)}
            placeholder={t('books.copies.searchTitle')}
            className="sm:max-w-xs"
          />
          <Input
            value={barcodeInput}
            onChange={(event) => setBarcodeInput(event.target.value)}
            placeholder={t('books.copies.searchBarcode')}
            className="sm:max-w-xs"
          />
          <Button variant="outline" type="submit" aria-label={t('common.search')}>
            <Search />
          </Button>
        </form>
      </div>

      {copiesQuery.isLoading ? (
        <PageLoader />
      ) : (
        <div className="rounded-lg border bg-card">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="px-4">{t('books.copies.invNumber')}</TableHead>
                <TableHead className="px-4">{t('books.copies.barcode')}</TableHead>
                <TableHead className="px-4">{t('books.columns.name')}</TableHead>
                <TableHead className="hidden px-4 lg:table-cell">{t('books.copies.isbn')}</TableHead>
                <TableHead className="px-4">{t('books.copies.status')}</TableHead>
                <TableHead className="px-4 text-right">{t('common.actions')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {copiesQuery.data?.data.map((copy) => (
                <TableRow key={copy.id}>
                  <TableCell className="px-4 font-medium">
                    <Link to={`/books/copies/${copy.id}`} className="hover:text-brand-accent">
                      {copy.order_number ?? '—'}
                    </Link>
                  </TableCell>
                  <TableCell className="px-4 font-mono text-xs text-muted-foreground">{copy.barcode ?? '—'}</TableCell>
                  <TableCell className="px-4">
                    <Link to={`/books/titles/${copy.book_id}`} className="hover:text-brand-accent">
                      {copy.book_name ?? '—'}
                    </Link>
                  </TableCell>
                  <TableCell className="hidden px-4 text-muted-foreground lg:table-cell">{copy.isbn ?? '—'}</TableCell>
                  <TableCell className="px-4">
                    <Badge variant={STATUS_VARIANTS[copy.status]}>{t(`books.status.${copy.status}`)}</Badge>
                  </TableCell>
                  <TableCell className="px-4">
                    <div className="flex justify-end">
                      <Button asChild variant="ghost" size="icon-sm" aria-label={t('books.copies.viewDetail')}>
                        <Link to={`/books/copies/${copy.id}`}>
                          <Eye />
                        </Link>
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
              {!copiesQuery.data?.data.length ? (
                <TableRow>
                  <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                    {t('books.copies.empty')}
                  </TableCell>
                </TableRow>
              ) : null}
            </TableBody>
          </Table>

          {copiesQuery.data && copiesQuery.data.meta.total > 0 ? (
            <PaginationBar
              currentPage={copiesQuery.data.meta.current_page}
              lastPage={copiesQuery.data.meta.last_page}
              total={copiesQuery.data.meta.total}
              perPage={copiesQuery.data.meta.per_page}
              onPageChange={(nextPage) => setParams({ page: nextPage })}
            />
          ) : null}
        </div>
      )}
    </div>
  )
}
