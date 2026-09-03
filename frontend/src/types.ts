export type RoleValue = 'superadmin' | 'library_admin' | 'librarian' | 'user'

export interface RoleOption {
  value: RoleValue
  label: string
}

export interface User {
  id: number
  name: string
  first_name: string | null
  last_name: string | null
  username: string | null
  email: string
  role: RoleValue
  role_label: string
  bar_code: string | null
  email_verified_at: string | null
  created_at: string
  updated_at: string
  libraries?: Array<{ id: number; name: string }>
}

export interface Region {
  id: number
  name: string
  places_count?: number
}

export interface Place {
  id: number
  name: string
  region_id: number
  region_name?: string
}

export interface Library {
  id: number
  name: string
  address: string
  work_time: string | null
  deleted: boolean
  place_id: number
  place?: {
    id: number
    name: string
    region_id: number
    region: { id: number; name: string } | null
  }
}

export interface Paginated<T> {
  data: T[]
  links?: unknown
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface PaginatedMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface PaginatedResponse<T> {
  data: T[]
  links?: {
    first?: string | null
    last?: string | null
    prev?: string | null
    next?: string | null
  }
  meta: PaginatedMeta
}
