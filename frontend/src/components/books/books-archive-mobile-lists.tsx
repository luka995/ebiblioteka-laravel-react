import { useTranslation } from 'react-i18next'
import { ArchiveRestore, BookOpen, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  List,
  ListItem,
  ListItemField,
  ListItemHeader,
  ListItemMeta,
  ListItemTitle,
} from '@/components/ui/list'
import type { Book, BookCopy } from '@/types'

interface ArchivedBooksMobileListProps {
  books: Book[]
  onRestore: (book: Book) => void
  onForceDelete: (book: Book) => void
}

export function ArchivedBooksMobileList({
  books,
  onRestore,
  onForceDelete,
}: ArchivedBooksMobileListProps) {
  const { t } = useTranslation()

  if (books.length === 0) {
    return <p className="px-4 py-8 text-center text-sm text-muted-foreground">{t('books.archive.empty')}</p>
  }

  return (
    <List>
      {books.map((book) => (
        <ListItem key={book.id}>
          <ListItemHeader>
            <BookOpen className="size-4 shrink-0 text-muted-foreground" />
            <ListItemTitle>{book.name}</ListItemTitle>
            <div className="flex shrink-0 items-center gap-1">
              {book.can?.restore ? (
                <Button
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('books.archive.restore')}
                  onClick={() => onRestore(book)}
                >
                  <ArchiveRestore />
                </Button>
              ) : null}
              {book.can?.forceDelete ? (
                <Button
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('books.archive.forceDelete')}
                  onClick={() => onForceDelete(book)}
                >
                  <Trash2 />
                </Button>
              ) : null}
            </div>
          </ListItemHeader>
          <ListItemMeta>
            <ListItemField
              label={t('books.columns.authors')}
              value={book.authors?.map((author) => author.display_name).join('; ')}
            />
          </ListItemMeta>
        </ListItem>
      ))}
    </List>
  )
}

interface ArchivedCopiesMobileListProps {
  copies: BookCopy[]
  onRestore: (copy: BookCopy) => void
  onForceDelete: (copy: BookCopy) => void
}

export function ArchivedCopiesMobileList({
  copies,
  onRestore,
  onForceDelete,
}: ArchivedCopiesMobileListProps) {
  const { t } = useTranslation()

  if (copies.length === 0) {
    return <p className="px-4 py-8 text-center text-sm text-muted-foreground">{t('books.archive.empty')}</p>
  }

  return (
    <List>
      {copies.map((copy) => (
        <ListItem key={copy.id}>
          <ListItemHeader>
            <ListItemTitle>{copy.order_number ?? '—'}</ListItemTitle>
            <div className="flex shrink-0 items-center gap-1">
              {copy.can?.restore ? (
                <Button
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('books.archive.restore')}
                  onClick={() => onRestore(copy)}
                >
                  <ArchiveRestore />
                </Button>
              ) : null}
              {copy.can?.forceDelete ? (
                <Button
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('books.archive.forceDelete')}
                  onClick={() => onForceDelete(copy)}
                >
                  <Trash2 />
                </Button>
              ) : null}
            </div>
          </ListItemHeader>
          <ListItemMeta>
            <ListItemField label={t('books.columns.name')} value={copy.book_name} />
            <ListItemField label={t('books.copies.barcode')} value={copy.barcode} />
          </ListItemMeta>
        </ListItem>
      ))}
    </List>
  )
}
