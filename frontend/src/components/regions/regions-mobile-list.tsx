import { useTranslation } from 'react-i18next'
import { Map, Pencil, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  List,
  ListItem,
  ListItemField,
  ListItemHeader,
  ListItemMeta,
  ListItemTitle,
} from '@/components/ui/list'
import type { Region } from '@/types'

interface RegionsMobileListProps {
  regions: Region[]
  onEdit: (region: Region) => void
  onDelete: (region: Region) => void
}

export function RegionsMobileList({ regions, onEdit, onDelete }: RegionsMobileListProps) {
  const { t } = useTranslation()

  if (regions.length === 0) {
    return <p className="px-4 py-8 text-center text-sm text-muted-foreground">{t('regions.empty')}</p>
  }

  return (
    <List>
      {regions.map((region) => (
        <ListItem key={region.id}>
          <ListItemHeader>
            <Map className="size-4 shrink-0 text-muted-foreground" />
            <ListItemTitle>{region.name}</ListItemTitle>
            <div className="flex shrink-0 items-center gap-1">
              {region.can?.update ? (
                <Button
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('common.edit')}
                  onClick={() => onEdit(region)}
                >
                  <Pencil />
                </Button>
              ) : null}
              {region.can?.delete ? (
                <Button
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('common.delete')}
                  onClick={() => onDelete(region)}
                >
                  <Trash2 />
                </Button>
              ) : null}
            </div>
          </ListItemHeader>
          <ListItemMeta>
            <ListItemField label={t('regions.columns.placesCount')} value={region.places_count ?? 0} />
          </ListItemMeta>
        </ListItem>
      ))}
    </List>
  )
}
