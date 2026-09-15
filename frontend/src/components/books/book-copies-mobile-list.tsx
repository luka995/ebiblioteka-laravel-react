import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { Eye } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  List,
  ListItem,
  ListItemField,
  ListItemHeader,
  ListItemMeta,
  ListItemTitle,
} from '@/components/ui/list'
import type { BookCopy, BookCopyStatus } from '@/types'

const STATUS_VARIANTS: Record<BookCopyStatus, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  available: 'default',
  borrowed: 'secondary',
  record_error: 'outline',
  written_off: 'destructive',
  archived: 'secondary',
}

interface BookCopiesMobileListProps {
  copies: BookCopy[]
  selectedIds: Set<number>
  onToggleRow: (id: number) => void
  onToggleAll?: () => void
  allSelected?: boolean
  someSelected?: boolean
  renderActions?: (copy: BookCopy) => ReactNode
}

export function BookCopiesMobileList({
  copies,
  selectedIds,
  onToggleRow,
  onToggleAll,
  allSelected = false,
  someSelected = false,
  renderActions,
}: BookCopiesMobileListProps) {
  const { t } = useTranslation()

  if (copies.length === 0) {
    return <p className="px-4 py-8 text-center text-sm text-muted-foreground">{t('books.copies.empty')}</p>
  }

  return (
    <div>
      {onToggleAll ? (
        <div className="flex items-center gap-2 border-b px-4 py-2">
          <Checkbox
            checked={allSelected ? true : someSelected ? 'indeterminate' : false}
            onCheckedChange={() => onToggleAll()}
            aria-label={t('books.copies.selectAll')}
          />
          <span className="text-sm text-muted-foreground">{t('books.copies.selectAll')}</span>
        </div>
      ) : null}
      <List>
        {copies.map((copy) => (
          <ListItem key={copy.id}>
            <ListItemHeader>
              <Checkbox
                checked={selectedIds.has(copy.id)}
                onCheckedChange={() => onToggleRow(copy.id)}
                aria-label={copy.order_number ?? t('books.copies.select')}
              />
              <ListItemTitle>
                <Link to={`/books/copies/${copy.id}`} className="block truncate hover:text-brand-accent">
                  {copy.order_number ?? '—'}
                </Link>
              </ListItemTitle>
              <div className="flex shrink-0 items-center gap-1">
                {renderActions ? (
                  renderActions(copy)
                ) : (
                  <Button asChild variant="ghost" size="icon-sm" aria-label={t('books.copies.viewDetail')}>
                    <Link to={`/books/copies/${copy.id}`}>
                      <Eye />
                    </Link>
                  </Button>
                )}
              </div>
            </ListItemHeader>
            <ListItemMeta>
              <ListItemField
                label={t('books.columns.name')}
                value={
                  <Link to={`/books/titles/${copy.book_id}`} className="hover:text-brand-accent">
                    {copy.book_name ?? '—'}
                  </Link>
                }
              />
              <ListItemField
                label={t('books.copies.status')}
                value={<Badge variant={STATUS_VARIANTS[copy.status]}>{t(`books.status.${copy.status}`)}</Badge>}
              />
              <ListItemField label={t('books.copies.barcode')} value={copy.barcode} />
              <ListItemField label={t('books.copies.isbn')} value={copy.isbn} />
            </ListItemMeta>
          </ListItem>
        ))}
      </List>
    </div>
  )
}
