export type RoleValue = 'user' | 'librarian' | 'library_admin'

export interface ResourceCan {
  view: boolean
  update: boolean
  updateSettings?: boolean
  delete: boolean
  restore?: boolean
  forceDelete?: boolean
  writeOff?: boolean
  manageMemberships?: boolean
  removeMemberships?: boolean
  returnBook?: boolean
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
  role?: RoleValue | null
  role_label?: string | null
  lend_days?: number
  overdue_price_per_day?: number | string
}

export type ActiveLibrary = LibraryOption | null

export interface User {
  id: number
  name: string
  first_name: string | null
  last_name: string | null
  username: string | null
  email: string
  locale: string | null
  is_super_admin: boolean
  role: RoleValue | null
  role_label: string | null
  jmbg: string | null
  address: string | null
  city: string | null
  post_code: string | null
  bar_code: string | null
  bar_code_svg?: string | null
  email_verified_at: string | null
  created_at: string
  updated_at: string
  libraries?: Array<{ id: number; name: string; role: RoleValue; role_label: string }>
  deactivated_libraries?: Array<{ id: number; name: string; role: RoleValue; role_label: string }>
  tags?: Array<{ id: number; name: string; library_id: number }>
  reservations?: Reservation[]
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
  settings?: LibrarySettings
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

export interface LibrarySettings {
  reservation_hours: number
  reservation_reminder_hours: number
  lend_days: number
  loan_reminder_days: number
  overdue_reminder_days: number
  overdue_price_per_day: number | string
  notify_staff_on_reservation: boolean
  notify_staff_on_issue: boolean
  notify_staff_on_return: boolean
  notify_staff_on_cancellation: boolean
  notify_staff_daily_digest: boolean
  notify_user_reservation_expiry: boolean
  notify_user_loan_due: boolean
}

export type LibraryRequestStatus = 'pending' | 'approved' | 'rejected'

export interface LibraryRequest {
  id: number
  school_name: string
  school_type: string
  school_type_label: string
  city: string
  address: string
  contact_name: string
  contact_email: string
  contact_phone: string
  student_count: number | null
  employee_count: number | null
  message: string | null
  status: LibraryRequestStatus
  status_label: string
  handled_by?: string | null
  handled_at: string | null
  handled_at_formatted: string | null
  created_at: string
  created_at_formatted: string | null
  updated_at: string
  can?: {
    view: boolean
    approve: boolean
    reject: boolean
  }
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

export type BookCopyStatus = 'available' | 'borrowed' | 'reserved' | 'record_error' | 'written_off' | 'archived'

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
  active_reservation?: BorrowReservationSummary | null
  active_borrow?: Borrow | null
  deleted_at: string | null
  created_at: string
  updated_at: string
  can?: ResourceCan
}

export type InventoryBookStatus = 'pending' | 'processing' | 'completed' | 'failed'

export interface InventoryBook {
  id: number
  library_id: number
  library_name?: string | null
  requested_by?: string | null
  status: InventoryBookStatus
  file_size: number | null
  rows_count: number | null
  locale: string
  started_at: string | null
  finished_at: string | null
  failure_reason: string | null
  created_at: string
  can?: {
    view: boolean
    download: boolean
  }
}

export type BarcodePrintJobStatus = 'pending' | 'processing' | 'completed' | 'failed'

export type BarcodePrintJobScope = 'library' | 'list'

export interface BarcodePrintJob {
  id: number
  library_id: number
  library_name?: string | null
  requested_by?: string | null
  scope: BarcodePrintJobScope
  format: 'label' | 'a4'
  status: BarcodePrintJobStatus
  file_size: number | null
  items_count: number | null
  invalid_count: number
  started_at: string | null
  finished_at: string | null
  failure_reason: string | null
  created_at: string
  can?: {
    view: boolean
    download: boolean
    delete: boolean
  }
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

export type ReservationStatus = 'active' | 'cancelled' | 'expired' | 'fulfilled'

export interface Reservation {
  id: number
  library_id: number
  library_name?: string | null
  user_id: number
  user_name?: string | null
  book_id: number
  book_name?: string | null
  book_authors?: string | null
  book_copy_id: number
  barcode?: string | null
  order_number?: string | null
  from_date: string | null
  from_date_formatted: string | null
  valid_till: string | null
  valid_till_formatted: string | null
  status: ReservationStatus
  status_label: string
  ended_at: string | null
  can: {
    cancel: boolean
    issue: boolean
  }
}

export interface CatalogBook {
  id: number
  name: string
  slug: string
  library_id: number
  library_name: string | null
  library_address: string | null
  category_primary_id: number | null
  category_primary_name: string | null
  description: string | null
  image: string | null
  image_url: string | null
  cover_url: string | null
  authors: BookAuthor[]
}

export interface CatalogBookResponse {
  data: CatalogBook
  availability: {
    total: number
    available: number
    archived: number
    written_off: number
  }
  publish_year: string | null
  reservation_hours: number
  membership: boolean
  active_reservation: Reservation | null
  can_reserve: boolean
}

export type BorrowStatus = 'active' | 'overdue' | 'returned'

export interface Borrow {
  id: number
  library_id: number
  library_name?: string | null
  user_id: number
  user_name?: string | null
  user_barcode?: string | null
  book_id: number
  book_name?: string | null
  book_authors?: string | null
  book_copy_id: number
  barcode?: string | null
  order_number?: string | null
  reservation_id: number | null
  from_date: string | null
  from_date_formatted: string | null
  valid_till: string | null
  valid_till_formatted: string | null
  return_date: string | null
  return_date_formatted: string | null
  returned: boolean
  status: BorrowStatus
  is_overdue: boolean
  overdue_days: number
  overdue_amount: number
  created_by?: string | null
  created_at: string | null
  updated_at: string | null
  can?: ResourceCan
}

export interface BorrowUserSummary {
  id: number
  name: string
  first_name: string | null
  last_name: string | null
  username: string | null
  email: string
  bar_code: string | null
  is_member: boolean
  active_borrows: number
  active_reservations: number
}

export interface BorrowCopyLookup {
  id: number
  book_id: number
  book_name: string | null
  book_authors: string | null
  barcode: string | null
  order_number: string | null
  status: 'available' | 'reserved' | 'borrowed' | 'written_off' | 'record_error'
  issuable: boolean
  block_reason: string | null
  active_reservation: BorrowReservationSummary | null
  active_borrow: {
    id: number
    user_id: number
    user_name: string | null
    valid_till_formatted: string | null
  } | null
}

export interface BorrowReservationSummary {
  id: number
  user_id: number
  user_name: string | null
  valid_till: string | null
  valid_till_formatted: string | null
}

export interface BorrowLookupResponse {
  data: {
    user: BorrowUserSummary | null
    users: BorrowUserSummary[]
    copy: BorrowCopyLookup | null
    defaults: {
      lend_days: number
      overdue_price_per_day: number | string
      valid_till: string
    }
  }
}

export interface ReservationConflict {
  reservation_id: number
  user_id: number
  user_name: string | null
  book_id: number
  book_name: string | null
  reserved_book_copy_id: number
  reserved_barcode: string | null
  reserved_order_number: string | null
  reserved_valid_till: string | null
  reserved_valid_till_formatted: string | null
  scanned_book_copy_id: number
  scanned_barcode: string | null
  scanned_order_number: string | null
}

export interface SuperAdminUser {
  id: number
  name: string
  email: string
}

export interface AppSettings {
  super_admin_user_ids: number[]
  super_admins: SuperAdminUser[]
  logs: SettingLogEntry[]
}

export interface SettingLogEntry {
  id: number
  key: string
  old_value: unknown
  new_value: unknown
  changed_by: string | null
  created_at: string | null
  created_at_formatted: string | null
}

export interface BookHistoryEntry {
  id: number
  library_id: number | null
  library_name: string | null
  book_id: number | null
  book_name: string | null
  book_copy_id: number | null
  barcode: string | null
  order_number: string | null
  user_id: number | null
  user_name: string | null
  actor_id: number | null
  actor_name: string | null
  action: string
  action_date: string | null
  action_date_formatted: string | null
  meta: Record<string, unknown> | null
}
