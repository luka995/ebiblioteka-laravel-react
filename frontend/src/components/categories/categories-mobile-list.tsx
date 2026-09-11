import { useTranslation } from 'react-i18next'
import { FolderTree, Pencil, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  List,
  ListItem,
  ListItemField,
  ListItemHeader,
  ListItemMeta,
  ListItemTitle,
} from '@/components/ui/list'
import type { Category } from '@/types'

interface CategoriesMobileListProps {
  categories: Category[]
  onEdit?: (category: Category) => void
  onDelete?: (category: Category) => void
}

export function CategoriesMobileList({ categories, onEdit, onDelete }: CategoriesMobileListProps) {
  const { t } = useTranslation()

  if (categories.length === 0) {
    return <p className="px-4 py-8 text-center text-sm text-muted-foreground">{t('categories.empty')}</p>
  }

  return (
    <List>
      {categories.map((category) => (
        <ListItem key={category.id}>
          <ListItemHeader>
            <FolderTree className="size-4 shrink-0 text-muted-foreground" />
            <ListItemTitle>{category.full_name ?? category.name}</ListItemTitle>
            <div className="flex shrink-0 items-center gap-1">
              {category.can?.update && onEdit ? (
                <Button
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('common.edit')}
                  onClick={() => onEdit(category)}
                >
                  <Pencil />
                </Button>
              ) : null}
              {category.can?.delete && onDelete ? (
                <Button
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('common.delete')}
                  onClick={() => onDelete(category)}
                >
                  <Trash2 />
                </Button>
              ) : null}
            </div>
          </ListItemHeader>
          <ListItemMeta>
            <ListItemField label={t('categories.columns.library')} value={category.library_name} />
            <ListItemField label={t('categories.columns.children')} value={category.children_count ?? 0} />
          </ListItemMeta>
        </ListItem>
      ))}
    </List>
  )
}
