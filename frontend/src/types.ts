export type RoleValue = 'superadmin' | 'library_admin' | 'librarian' | 'user'

export interface ResourceCan {
  view: boolean
  update: boolean
  delete: boolean
  restore?: boolean
  forceDelete?: boolean
  writeOff?: boolean
  manageMemberships?: boolean
  removeMemberships?: boolean
}

export type CollectionPermissions = Record<string, boolean>

export type GlobalPermissions = Record<string, CollectionPermissions>

export interface RoleOption {
  value: RoleValue
  label: string
}

export interface LibraryOption {
  id: number
  name: string
  inv_number_auto?: boolean
}

export type ActiveLibrary = LibraryOption | null

export interface User {
  id: number
  name: string
  first_name: string | null
  last_name: string | null
  username: string | null
  email: string
  role: RoleValue
  role_label: string
  jmbg: string | null
  address: string | null
  city: string | null
  post_code: string | null
  bar_code: string | null
  bar_code_svg?: string | null
  email_verified_at: string | null
  created_at: string
  updated_at: string
  libraries?: Array<{ id: number; name: string }>
  deactivated_libraries?: Array<{ id: number; name: string }>
  tags?: Array<{ id: number; name: string; library_id: number }>
  can?: ResourceCan
}

export interface Region {
  id: number
  name: string
  places_count?: number
  can?: ResourceCan
}

export interface Place {
  id: number
  name: string
  region_id: number
  region_name?: string
  can?: ResourceCan
}

export interface Tag {
  id: number
  name: string
  library_id: number
  library_name?: string
  users_count?: number
  can?: ResourceCan
}

export interface Category {
  id: number
  name: string
  library_id: number
  library_name?: string
  parent_id: number | null
  parent_name?: string | null
  parent_full_name?: string | null
  full_name?: string
  children_count?: number
  can?: ResourceCan
}

export interface Author {
  id: number
  name: string
  display_name: string
  library_id: number
  library_name?: string
  can?: ResourceCan
}

export interface Library {
  id: number
  name: string
  address: string
  work_time: string | null
  inv_number_auto: boolean
  deleted: boolean
  place_id: number
  place?: {
    id: number
    name: string
    region_id: number
    region: { id: number; name: string } | null
  }
  can?: ResourceCan
}

export interface News {
  id: number
  title: string
  slug: string
  body: string
  date: string
  date_formatted: string | null
  image: string | null
  image_url: string | null
  created_at: string
  updated_at: string
  can?: ResourceCan
}

export interface BookAuthor {
  id: number
  name: string
  display_name: string
}

export interface Book {
  id: number
  name: string
  slug: string
  library_id: number
  library_name?: string
  inv_number_auto?: boolean
  category_primary_id: number | null
  category_primary_name?: string | null
  category_secondary_id: number | null
  category_secondary_name?: string | null
  description: string | null
  image: string | null
  image_url?: string | null
  cover_url: string | null
  authors?: BookAuthor[]
  copies_count?: number
  available_count?: number
  deleted_at: string | null
  created_at: string
  updated_at: string
  can?: ResourceCan
}

export type BookCopyStatus = 'available' | 'borrowed' | 'record_error' | 'written_off' | 'archived'

export interface BookCopyWriteOff {
  id: number
  book_copy_id: number | null
  book_id: number | null
  order_number: string | null
  reason: 'out_of_date' | 'unusable'
  reason_label: string
  occurred_at: string | null
  notice: string | null
  cancelled_at: string | null
  created_by?: string | null
  cancelled_by?: string | null
  created_at: string
  updated_at: string
}

export interface BookCopy {
  id: number
  library_id: number
  library_name?: string | null
  inv_number_auto?: boolean
  book_id: number
  book_name?: string | null
  order_number: string | null
  seq_number: number | null
  barcode: string | null
  isbn: string | null
  publisher: string | null
  publish_place: string | null
  publish_year: string | null
  issue_number: string | null
  num_of_pages: number | null
  dimension: string | null
  part: string | null
  udk: string | null
  binding: string | null
  origin: string | null
  book_number: string | null
  place_on_shelf: string | null
  price: string | number
  date_add: string | null
  date_add_formatted: string | null
  notice: string | null
  borrowed: boolean
  reserved: boolean
  rec_error: boolean
  rec_error_notice: string | null
  status: BookCopyStatus
  active_write_off: BookCopyWriteOff | null
  deleted_at: string | null
  created_at: string
  updated_at: string
  can?: ResourceCan
}

export interface IsbnMetadata {
  source: string
  isbn: string | null
  title: string | null
  authors: string[]
  publisher: string | null
  publish_place: string | null
  publish_year: string | null
  pages: number | null
  dimensions: string | null
  description: string | null
  cover_url: string | null
  category: string | null
  udk: string | null
}

export interface IsbnLookupResult {
  source: string | null
  isbn: string
  metadata: IsbnMetadata | null
  book: Book | null
  existing_copies: BookCopy[]
  matches: Book[]
}

export interface BookDuplicateCheckResult {
  matches: Book[]
}

export interface InventoryDiscrepancy {
  next_auto: number
  max_existing: number
  max_used: number
  archived_copies: BookCopy[]
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
  permissions?: CollectionPermissions
}
