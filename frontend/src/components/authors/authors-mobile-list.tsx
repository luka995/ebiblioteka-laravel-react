import { useTranslation } from 'react-i18next'
import { PenLine, Pencil, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  List,
  ListItem,
  ListItemField,
  ListItemHeader,
  ListItemMeta,
  ListItemTitle,
} from '@/components/ui/list'
import type { Author } from '@/types'

interface AuthorsMobileListProps {
  authors: Author[]
  onEdit?: (author: Author) => void
  onDelete?: (author: Author) => void
}

export function AuthorsMobileList({ authors, onEdit, onDelete }: AuthorsMobileListProps) {
  const { t } = useTranslation()

  if (authors.length === 0) {
    return <p className="px-4 py-8 text-center text-sm text-muted-foreground">{t('authors.empty')}</p>
  }

  return (
    <List>
      {authors.map((author) => (
        <ListItem key={author.id}>
          <ListItemHeader>
            <PenLine className="size-4 shrink-0 text-muted-foreground" />
            <ListItemTitle>{author.display_name}</ListItemTitle>
            <div className="flex shrink-0 items-center gap-1">
              {author.can?.update && onEdit ? (
                <Button
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('common.edit')}
                  onClick={() => onEdit(author)}
                >
                  <Pencil />
                </Button>
              ) : null}
              {author.can?.delete && onDelete ? (
                <Button
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('common.delete')}
                  onClick={() => onDelete(author)}
                >
                  <Trash2 />
                </Button>
              ) : null}
            </div>
          </ListItemHeader>
          <ListItemMeta>
            <ListItemField label={t('authors.columns.library')} value={author.library_name} />
          </ListItemMeta>
        </ListItem>
      ))}
    </List>
  )
}
