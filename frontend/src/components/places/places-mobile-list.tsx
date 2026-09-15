import { useTranslation } from 'react-i18next'
import { MapPin, Pencil, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  List,
  ListItem,
  ListItemField,
  ListItemHeader,
  ListItemMeta,
  ListItemTitle,
} from '@/components/ui/list'
import type { Place } from '@/types'

interface PlacesMobileListProps {
  places: Place[]
  onEdit: (place: Place) => void
  onDelete: (place: Place) => void
}

export function PlacesMobileList({ places, onEdit, onDelete }: PlacesMobileListProps) {
  const { t } = useTranslation()

  if (places.length === 0) {
    return <p className="px-4 py-8 text-center text-sm text-muted-foreground">{t('places.empty')}</p>
  }

  return (
    <List>
      {places.map((place) => (
        <ListItem key={place.id}>
          <ListItemHeader>
            <MapPin className="size-4 shrink-0 text-muted-foreground" />
            <ListItemTitle>{place.name}</ListItemTitle>
            <div className="flex shrink-0 items-center gap-1">
              {place.can?.update ? (
                <Button
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('common.edit')}
                  onClick={() => onEdit(place)}
                >
                  <Pencil />
                </Button>
              ) : null}
              {place.can?.delete ? (
                <Button
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('common.delete')}
                  onClick={() => onDelete(place)}
                >
                  <Trash2 />
                </Button>
              ) : null}
            </div>
          </ListItemHeader>
          <ListItemMeta>
            <ListItemField label={t('places.columns.region')} value={place.region_name} />
          </ListItemMeta>
        </ListItem>
      ))}
    </List>
  )
}
