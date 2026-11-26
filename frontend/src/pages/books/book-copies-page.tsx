import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { BookCheck, Eye, FileSpreadsheet, Filter, Plus, Printer, Search } from 'lucide-react'
import { api, apiPaths } from '@/lib/api'
import { normalizeBarcode } from '@/lib/barcode'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { PageLoader } from '@/components/ui/loader'
import { SortableTableHead } from '@/components/ui/sortable-table-head'
import { PaginationBar } from '@/components/pagination-bar'
import { BookCopyBarcodePrintModal } from '@/components/books/book-copy-barcode-print-modal'
import { BookCopiesMobileList } from '@/components/books/book-copies-mobile-list'
import { BookCopyQuickAddModal } from '@/components/books/book-copy-quick-add-modal'
import { IssueLoanModal } from '@/components/borrows/issue-loan-modal'
import {
  BookCopyFiltersPanel,
  countActiveFilters,
  EMPTY_BOOK_COPY_FILTERS,
  type BookCopyFilters,
} from '@/components/books/book-copies-filters-panel'
import { useAuth } from '@/hooks/useAuth'
import { useTableSort } from '@/hooks/useTableSort'
import type { BookCopy, BookCopyStatus, PaginatedResponse } from '@/types'

const PAGE_SIZE = 25

const FILTER_KEYS = Object.keys(EMPTY_BOOK_COPY_FILTERS) as Array<keyof BookCopyFilters>

function readFilters(params: URLSearchParams): BookCopyFilters {
  const filters = { ...EMPTY_BOOK_COPY_FILTERS }
  FILTER_KEYS.forEach((key) => {
    const value = params.get(key)
    if (value) filters[key] = value
  })
  return filters
}

const STATUS_VARIANTS: Record<BookCopyStatus, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  available: 'default',
  borrowed: 'secondary',
  reserved: 'outline',
  record_error: 'outline',
  written_off: 'destructive',
  archived: 'secondary',
}

export function BookCopiesPage() {
  const { t } = useTranslation()
  const { can, activeLibrary, isLoading } = useAuth()
  const [searchParams, setSearchParams] = useSearchParams()
  const { sort, direction, onSort } = useTableSort('order_number')

  const page = Math.max(1, Number(searchParams.get('page')) || 1)
  const barcode = searchParams.get('barcode') ?? ''
  const orderNumber = searchParams.get('order_number') ?? ''
  const isbn = searchParams.get('isbn') ?? ''
  const search = searchParams.get('search') ?? ''
  const filters = readFilters(searchParams)
  const activeFilters = countActiveFilters(filters)
  const libraryId = activeLibrary?.id ?? null

  const [barcodeInput, setBarcodeInput] = useState(barcode)
  const [orderNumberInput, setOrderNumberInput] = useState(orderNumber)
  const [isbnInput, setIsbnInput] = useState(isbn)
  const [searchInput, setSearchInput] = useState(search)
  const [filtersOpen, setFiltersOpen] = useState(false)
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())
  const [printOpen, setPrintOpen] = useState(false)
  const [quickAddOpen, setQuickAddOpen] = useState(false)
  const [issueCopy, setIssueCopy] = useState<BookCopy | null>(null)

  useEffect(() => {
    setBarcodeInput(barcode)
    setOrderNumberInput(orderNumber)
    setIsbnInput(isbn)
    setSearchInput(search)
  }, [barcode, orderNumber, isbn, search])

  const copiesQuery = useQuery({
    queryKey: ['book-copies', { page, barcode, orderNumber, isbn, search, filters, libraryId, sort, direction }],
    queryFn: async () => {
      const query = new URLSearchParams({ per_page: String(PAGE_SIZE), page: String(page), sort, direction })
      if (barcode) query.set('barcode', barcode)
      if (orderNumber) query.set('order_number', orderNumber)
      if (isbn) query.set('isbn', isbn)
      if (search) query.set('search', search)
      Object.entries(filters).forEach(([key, value]) => {
        if (value) query.set(key, value)
      })
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

  const rows = copiesQuery.data?.data ?? []
  const pageIds = rows.map((item) => item.id)
  const allPageSelected = pageIds.length > 0 && pageIds.every((id) => selectedIds.has(id))
  const somePageSelected = pageIds.some((id) => selectedIds.has(id))

  const toggleSelectAll = () => {
    setSelectedIds((current) => {
      const next = new Set(current)
      if (allPageSelected) {
        pageIds.forEach((id) => next.delete(id))
      } else {
        pageIds.forEach((id) => next.add(id))
      }
      return next
    })
  }

  const toggleRow = (id: number) => {
    setSelectedIds((current) => {
      const next = new Set(current)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  }

  const clearSelection = () => setSelectedIds(new Set())

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

  const applyFilters = (next: BookCopyFilters) => {
    setParams({ ...next, page: null })
  }

  const clearFilters = () => {
    setParams({ ...EMPTY_BOOK_COPY_FILTERS, page: null })
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 className="font-brand-heading text-2xl font-bold tracking-tight">{t('books.copies.listTitle')}</h2>
        <div className="flex flex-wrap items-center gap-2">
          {can('book_imports.viewAny') ? (
            <Button asChild variant="outline">
              <Link to="/books/imports">
                <FileSpreadsheet />
                {t('bookImports.open')}
              </Link>
            </Button>
          ) : null}
          <Button
            variant="outline"
            onClick={() => setFiltersOpen((value) => !value)}
            aria-expanded={filtersOpen}
          >
            <Filter />
            {t('books.copies.filters.toggle')}
            {activeFilters > 0 ? <Badge variant="secondary">{activeFilters}</Badge> : null}
          </Button>
          {can('book_copies.create') ? (
            <Button variant="brand" onClick={() => setQuickAddOpen(true)}>
              <Plus />
              {t('books.quickAdd.title')}
            </Button>
          ) : null}
        </div>
      </div>

      <div className="flex flex-col gap-2 sm:flex-row sm:items-start">
        <form
          className="flex flex-col gap-2 sm:flex-row"
          onSubmit={(event) => {
            event.preventDefault()
            const normalizedBarcode = normalizeBarcode(barcodeInput)
            setBarcodeInput(normalizedBarcode)
            setParams({
              barcode: normalizedBarcode || null,
              order_number: orderNumberInput.trim() || null,
              isbn: isbnInput.trim() || null,
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
            onBlur={(event) => setBarcodeInput(normalizeBarcode(event.currentTarget.value))}
            placeholder={t('books.copies.searchBarcode')}
            className="sm:max-w-xs"
          />
          <Input
            value={isbnInput}
            onChange={(event) => setIsbnInput(event.target.value)}
            placeholder={t('books.copies.searchIsbn')}
            className="sm:max-w-xs"
          />
          <Button variant="outline" type="submit" aria-label={t('common.search')}>
            <Search />
          </Button>
        </form>
      </div>

      {filtersOpen ? (
        <BookCopyFiltersPanel filters={filters} onApply={applyFilters} onClear={clearFilters} />
      ) : null}

      {selectedIds.size > 0 ? (
        <div className="flex flex-wrap items-center gap-2 rounded-lg border bg-muted/40 px-3 py-2">
          <span className="text-sm text-muted-foreground">
            {t('tags.selectedCount', { count: selectedIds.size })}
          </span>
          <div className="ml-auto flex flex-wrap items-center gap-2">
            <Button variant="outline" size="sm" onClick={() => setPrintOpen(true)}>
              <Printer />
              {t('books.copies.printSelected', { count: selectedIds.size })}
            </Button>
            <Button variant="ghost" size="sm" onClick={clearSelection}>
              {t('tags.clearSelection')}
            </Button>
          </div>
        </div>
      ) : null}

      {copiesQuery.isLoading ? (
        <PageLoader />
      ) : (
        <div className="rounded-lg border bg-card">
          <div className="hidden lg:block">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-10 px-4">
                  <Checkbox
                    checked={allPageSelected ? true : somePageSelected ? 'indeterminate' : false}
                    onCheckedChange={toggleSelectAll}
                    aria-label={t('books.copies.selectAll')}
                  />
                </TableHead>
                <SortableTableHead column="order_number" sort={sort} direction={direction} onSort={onSort} className="px-4">
                  {t('books.copies.invNumber')}
                </SortableTableHead>
                <SortableTableHead column="barcode" sort={sort} direction={direction} onSort={onSort} className="px-4">
                  {t('books.copies.barcode')}
                </SortableTableHead>
                <SortableTableHead column="book" sort={sort} direction={direction} onSort={onSort} className="px-4">
                  {t('books.columns.name')}
                </SortableTableHead>
                <SortableTableHead column="isbn" sort={sort} direction={direction} onSort={onSort} className="hidden px-4 lg:table-cell">
                  {t('books.copies.isbn')}
                </SortableTableHead>
                <SortableTableHead column="status" sort={sort} direction={direction} onSort={onSort} className="px-4">
                  {t('books.copies.status')}
                </SortableTableHead>
                <TableHead className="px-4 text-right">{t('common.actions')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.map((copy) => (
                <TableRow key={copy.id}>
                  <TableCell className="px-4">
                    <Checkbox
                      checked={selectedIds.has(copy.id)}
                      onCheckedChange={() => toggleRow(copy.id)}
                      aria-label={copy.order_number ?? t('books.copies.select')}
                    />
                  </TableCell>
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
                    <div className="flex justify-end gap-1">
                      {can('borrows.create') && (copy.status === 'available' || copy.status === 'reserved') ? (
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => setIssueCopy(copy)}
                        >
                          <BookCheck />
                          {t('borrows.issueAction')}
                        </Button>
                      ) : null}
                      <Button asChild variant="ghost" size="icon-sm" aria-label={t('books.copies.viewDetail')}>
                        <Link to={`/books/copies/${copy.id}`}>
                          <Eye />
                        </Link>
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
              {!rows.length ? (
                <TableRow>
                  <TableCell colSpan={7} className="h-24 text-center text-muted-foreground">
                    {t('books.copies.empty')}
                  </TableCell>
                </TableRow>
              ) : null}
            </TableBody>
          </Table>
          </div>

          <div className="lg:hidden">
            <BookCopiesMobileList
              copies={rows}
              selectedIds={selectedIds}
              onToggleRow={toggleRow}
              onToggleAll={toggleSelectAll}
              allSelected={allPageSelected}
              someSelected={somePageSelected}
              renderActions={(copy) => (
                <div className="flex items-center gap-1">
                  {can('borrows.create') && (copy.status === 'available' || copy.status === 'reserved') ? (
                    <Button variant="ghost" size="icon-sm" onClick={() => setIssueCopy(copy)} aria-label={t('borrows.issueAction')}>
                      <BookCheck />
                    </Button>
                  ) : null}
                  <Button asChild variant="ghost" size="icon-sm" aria-label={t('books.copies.viewDetail')}>
                    <Link to={`/books/copies/${copy.id}`}>
                      <Eye />
                    </Link>
                  </Button>
                </div>
              )}
            />
          </div>

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

      {printOpen ? (
        <BookCopyBarcodePrintModal
          open
          copyIds={Array.from(selectedIds)}
          onClose={() => setPrintOpen(false)}
          onSuccess={clearSelection}
        />
      ) : null}

      {quickAddOpen ? (
        <BookCopyQuickAddModal open onClose={() => setQuickAddOpen(false)} />
      ) : null}

      {issueCopy ? (
        <IssueLoanModal
          open
          copy={issueCopy}
          onClose={() => setIssueCopy(null)}
          onSuccess={() => {
            void copiesQuery.refetch()
          }}
        />
      ) : null}
    </div>
  )
}
