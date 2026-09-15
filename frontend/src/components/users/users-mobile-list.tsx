import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { Eye, Pencil, Power, UserX } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { List, ListItem, ListItemField, ListItemHeader, ListItemMeta } from '@/components/ui/list'
import type { User } from '@/types'

interface UsersMobileListProps {
  users: User[]
  selectedIds: Set<number>
  onToggleRow: (id: number) => void
  onToggleAll?: () => void
  allSelected?: boolean
  someSelected?: boolean
  onEdit: (user: User) => void
  onMembershipToggle: (user: User) => void
  onMembershipDelete: (user: User) => void
}

export function UsersMobileList({
  users,
  selectedIds,
  onToggleRow,
  onToggleAll,
  allSelected = false,
  someSelected = false,
  onEdit,
  onMembershipToggle,
  onMembershipDelete,
}: UsersMobileListProps) {
  const { t } = useTranslation()

  if (users.length === 0) {
    return <p className="px-4 py-8 text-center text-sm text-muted-foreground">{t('users.empty')}</p>
  }

  return (
    <div>
      {onToggleAll ? (
        <div className="flex items-center gap-2 border-b px-4 py-2">
          <Checkbox
            checked={allSelected ? true : someSelected ? 'indeterminate' : false}
            onCheckedChange={() => onToggleAll()}
            aria-label={t('tags.selectAll')}
          />
          <span className="text-sm text-muted-foreground">{t('tags.selectAll')}</span>
        </div>
      ) : null}
      <List>
        {users.map((item) => (
          <ListItem key={item.id}>
            <ListItemHeader>
              <Checkbox
                checked={selectedIds.has(item.id)}
                onCheckedChange={() => onToggleRow(item.id)}
                aria-label={item.name}
              />
              <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                <Link to={`/users/${item.id}`} className="truncate font-medium hover:underline">
                  {item.name}
                </Link>
                {item.username ? (
                  <p className="truncate text-xs text-muted-foreground">@{item.username}</p>
                ) : null}
              </div>
              <div className="flex shrink-0 items-center gap-1">
                <Button variant="ghost" size="icon-sm" asChild aria-label={t('common.view')}>
                  <Link to={`/users/${item.id}`}>
                    <Eye />
                  </Link>
                </Button>
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
                {item.can?.manageMemberships ? (
                  <Button
                    variant="ghost"
                    size="icon-sm"
                    aria-label={t('membership.toggleTitle')}
                    onClick={() => onMembershipToggle(item)}
                  >
                    <Power />
                  </Button>
                ) : null}
                {item.can?.forceDelete || item.can?.delete || item.can?.removeMemberships ? (
                  <Button
                    variant="ghost"
                    size="icon-sm"
                    aria-label={t('membership.deleteTitle')}
                    onClick={() => onMembershipDelete(item)}
                  >
                    <UserX />
                  </Button>
                ) : null}
              </div>
            </ListItemHeader>
            <ListItemMeta>
              <ListItemField
                label={t('users.columns.role')}
                value={
                  <Badge className="border-transparent bg-brand-soft text-brand">
                    {item.role_label}
                  </Badge>
                }
              />
              <ListItemField label={t('users.columns.email')} value={item.email} />
              <ListItemField
                label={t('users.columns.libraries')}
                value={item.libraries?.map((library) => library.name).join(', ')}
              />
              <ListItemField label={t('users.columns.barcode')} value={item.bar_code} />
            </ListItemMeta>
          </ListItem>
        ))}
      </List>
    </div>
  )
}
