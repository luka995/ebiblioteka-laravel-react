import { useTranslation } from 'react-i18next'
import { Pencil, Tag as TagIcon, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  List,
  ListItem,
  ListItemField,
  ListItemHeader,
  ListItemMeta,
  ListItemTitle,
} from '@/components/ui/list'
import type { Tag } from '@/types'

interface TagsMobileListProps {
  tags: Tag[]
  onEdit?: (tag: Tag) => void
  onDelete?: (tag: Tag) => void
}

export function TagsMobileList({ tags, onEdit, onDelete }: TagsMobileListProps) {
  const { t } = useTranslation()

  if (tags.length === 0) {
    return <p className="px-4 py-8 text-center text-sm text-muted-foreground">{t('tags.empty')}</p>
  }

  return (
    <List>
      {tags.map((tag) => (
        <ListItem key={tag.id}>
          <ListItemHeader>
            <TagIcon className="size-4 shrink-0 text-muted-foreground" />
            <ListItemTitle>{tag.name}</ListItemTitle>
            <div className="flex shrink-0 items-center gap-1">
              {tag.can?.update && onEdit ? (
                <Button
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('common.edit')}
                  onClick={() => onEdit(tag)}
                >
                  <Pencil />
                </Button>
              ) : null}
              {tag.can?.delete && onDelete ? (
                <Button
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('common.delete')}
                  onClick={() => onDelete(tag)}
                >
                  <Trash2 />
                </Button>
              ) : null}
            </div>
          </ListItemHeader>
          <ListItemMeta>
            <ListItemField label={t('tags.columns.library')} value={tag.library_name} />
            <ListItemField label={t('tags.columns.usersCount')} value={tag.users_count ?? 0} />
          </ListItemMeta>
        </ListItem>
      ))}
    </List>
  )
}
