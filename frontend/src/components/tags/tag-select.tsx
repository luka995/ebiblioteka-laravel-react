import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { api, apiPaths } from '@/lib/api'
import { Checkbox } from '@/components/ui/checkbox'
import type { PaginatedResponse, Tag } from '@/types'

export interface TagOption {
  id: number
  name: string
  library_id: number
}

interface TagSelectProps {
  libraryId: number
  value: TagOption[]
  onChange: (value: TagOption[]) => void
}

export function TagSelect({ libraryId, value, onChange }: TagSelectProps) {
  const { t } = useTranslation()

  const tagsQuery = useQuery({
    queryKey: ['tags', 'options', libraryId],
    queryFn: async () => {
      const response = await api.get<PaginatedResponse<Tag>>(`${apiPaths.tags}?library_id=${libraryId}&all=1`)
      return response.data
    },
    enabled: Boolean(libraryId),
  })

  const selectedIds = new Set(value.map((tag) => tag.id))

  const toggleTag = (tag: Tag) => {
    if (selectedIds.has(tag.id)) {
      onChange(value.filter((item) => item.id !== tag.id))
    } else {
      onChange([...value, { id: tag.id, name: tag.name, library_id: tag.library_id }])
    }
  }

  if (!libraryId) {
    return <p className="px-2 py-1 text-sm text-muted-foreground">{t('tags.selectLibraryHint')}</p>
  }

  if (tagsQuery.isLoading) {
    return <p className="px-2 py-1 text-sm text-muted-foreground">{t('common.loading')}</p>
  }

  const tags = tagsQuery.data ?? []

  if (tags.length === 0) {
    return <p className="px-2 py-1 text-sm text-muted-foreground">{t('tags.empty')}</p>
  }

  return (
    <div className="max-h-40 space-y-1 overflow-y-auto rounded-md border p-2">
      {tags.map((tag) => (
        <label
          key={tag.id}
          className="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-muted"
        >
          <Checkbox
            id={`tag-${tag.id}`}
            checked={selectedIds.has(tag.id)}
            onCheckedChange={() => toggleTag(tag)}
          />
          {tag.name}
        </label>
      ))}
    </div>
  )
}
