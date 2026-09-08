import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { Building2, Eye, Pencil, RotateCcw, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { List, ListItem, ListItemField, ListItemHeader, ListItemMeta } from '@/components/ui/list'
import type { Library } from '@/types'

interface LibrariesMobileListProps {
  libraries: Library[]
  onEdit: (library: Library) => void
  onDelete: (library: Library) => void
  onRestore: (library: Library) => void
}

export function LibrariesMobileList({
  libraries,
  onEdit,
  onDelete,
  onRestore,
}: LibrariesMobileListProps) {
  const { t } = useTranslation()

  if (libraries.length === 0) {
    return <p className="px-4 py-8 text-center text-sm text-muted-foreground">{t('libs.empty')}</p>
  }

  return (
    <List>
      {libraries.map((library) => (
        <ListItem key={library.id}>
          <ListItemHeader>
            <Building2 className="size-4 shrink-0 text-muted-foreground" />
            <div className="flex min-w-0 flex-1 flex-col gap-1">
              <Link to={`/libraries/${library.id}`} className="truncate font-medium hover:underline">
                {library.name}
              </Link>
              {library.deleted ? (
                <Badge variant="destructive" className="w-fit">
                  {t('libs.deletedBadge')}
                </Badge>
              ) : null}
            </div>
            <div className="flex shrink-0 items-center gap-1">
              {library.deleted ? (
                library.can?.restore ? (
                  <Button
                    variant="ghost"
                    size="icon-sm"
                    aria-label={t('libs.restore')}
                    onClick={() => onRestore(library)}
                  >
                    <RotateCcw />
                  </Button>
                ) : null
              ) : (
                <>
                  <Button variant="ghost" size="icon-sm" asChild aria-label={t('common.view')}>
                    <Link to={`/libraries/${library.id}`}>
                      <Eye />
                    </Link>
                  </Button>
                  {library.can?.update ? (
                    <Button
                      variant="ghost"
                      size="icon-sm"
                      aria-label={t('common.edit')}
                      onClick={() => onEdit(library)}
                    >
                      <Pencil />
                    </Button>
                  ) : null}
                  {library.can?.delete ? (
                    <Button
                      variant="ghost"
                      size="icon-sm"
                      aria-label={t('common.delete')}
                      onClick={() => onDelete(library)}
                    >
                      <Trash2 />
                    </Button>
                  ) : null}
                </>
              )}
            </div>
          </ListItemHeader>
          <ListItemMeta>
            <ListItemField label={t('libs.columns.place')} value={library.place?.name} />
            <ListItemField label={t('libs.columns.region')} value={library.place?.region?.name} />
            <ListItemField label={t('libs.columns.address')} value={library.address} />
            <ListItemField label={t('libs.columns.workTime')} value={library.work_time} />
          </ListItemMeta>
        </ListItem>
      ))}
    </List>
  )
}
