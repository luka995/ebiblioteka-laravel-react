import { useTranslation } from 'react-i18next'
import { Pencil, Trash2 } from 'lucide-react'
import { laravelBaseUrl, storageUrl } from '@/lib/environment'
import { Button } from '@/components/ui/button'
import {
  List,
  ListItem,
  ListItemField,
  ListItemHeader,
  ListItemMeta,
  ListItemTitle,
} from '@/components/ui/list'
import type { News } from '@/types'

function publicNewsUrl(slug: string): string {
  return `${laravelBaseUrl()}/novosti/${slug}`
}

interface NewsMobileListProps {
  news: News[]
  onEdit: (news: News) => void
  onDelete: (news: News) => void
}

export function NewsMobileList({ news, onEdit, onDelete }: NewsMobileListProps) {
  const { t } = useTranslation()

  if (news.length === 0) {
    return <p className="px-4 py-8 text-center text-sm text-muted-foreground">{t('news.empty')}</p>
  }

  return (
    <List>
      {news.map((item) => (
        <ListItem key={item.id}>
          <ListItemHeader className="items-start">
            {item.image_url ? (
              <img
                src={storageUrl(item.image_url) ?? undefined}
                alt=""
                className="h-12 w-20 shrink-0 rounded object-cover"
              />
            ) : (
              <div className="h-12 w-20 shrink-0 rounded bg-muted" />
            )}
            <ListItemTitle className="leading-snug">
              <a
                href={publicNewsUrl(item.slug)}
                target="_blank"
                rel="noreferrer"
                className="block truncate text-brand hover:underline"
                title={t('news.openPublic')}
              >
                {item.title}
              </a>
            </ListItemTitle>
            <div className="flex shrink-0 items-center gap-1">
              {item.can?.update ? (
                <Button
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('common.edit')}
                  onClick={() => onEdit(item)}
                >
                  <Pencil />
                </Button>
              ) : null}
              {item.can?.delete ? (
                <Button
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('common.delete')}
                  onClick={() => onDelete(item)}
                >
                  <Trash2 />
                </Button>
              ) : null}
            </div>
          </ListItemHeader>
          <ListItemMeta>
            <ListItemField label={t('news.columns.date')} value={item.date_formatted} />
          </ListItemMeta>
        </ListItem>
      ))}
    </List>
  )
}
