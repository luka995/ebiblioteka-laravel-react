import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { BookOpen, Pencil, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  List,
  ListItem,
  ListItemField,
  ListItemHeader,
  ListItemMeta,
  ListItemTitle,
} from '@/components/ui/list'
import type { Book } from '@/types'

interface BooksMobileListProps {
  books: Book[]
  onEdit: (book: Book) => void
  onDelete: (book: Book) => void
}

export function BooksMobileList({ books, onEdit, onDelete }: BooksMobileListProps) {
  const { t } = useTranslation()

  if (books.length === 0) {
    return <p className="px-4 py-8 text-center text-sm text-muted-foreground">{t('books.empty')}</p>
  }

  return (
    <List>
      {books.map((book) => (
        <ListItem key={book.id}>
          <ListItemHeader>
            <BookOpen className="size-4 shrink-0 text-muted-foreground" />
            <ListItemTitle>
              <Link to={`/books/titles/${book.id}`} className="block truncate hover:text-brand-accent">
                {book.name}
              </Link>
            </ListItemTitle>
            <div className="flex shrink-0 items-center gap-1">
              {book.can?.update ? (
                <Button
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('common.edit')}
                  onClick={() => onEdit(book)}
                >
                  <Pencil />
                </Button>
              ) : null}
              {book.can?.delete ? (
                <Button
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('common.delete')}
                  onClick={() => onDelete(book)}
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
            <ListItemField
              label={t('books.columns.categories')}
              value={[book.category_primary_name, book.category_secondary_name].filter(Boolean).join(' / ')}
            />
            <ListItemField
              label={t('books.columns.copies')}
              value={`${book.available_count ?? 0} / ${book.copies_count ?? 0}`}
            />
          </ListItemMeta>
        </ListItem>
      ))}
    </List>
  )
}
