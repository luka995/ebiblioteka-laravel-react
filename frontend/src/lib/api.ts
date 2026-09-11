import { laravelBaseUrl } from './environment'
import i18n from '@/i18n'

export class ApiError extends Error {
  status: number
  data: unknown

  constructor(status: number, data: unknown) {
    super(`API error ${status}`)
    this.name = 'ApiError'
    this.status = status
    this.data = data
  }

  get validationErrors(): Record<string, string[]> | undefined {
    const payload = this.data as { errors?: Record<string, string[]> } | undefined
    return payload?.errors
  }

  get messageText(): string | undefined {
    const payload = this.data as { message?: string } | undefined
    return payload?.message
  }
}

interface RequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
  body?: unknown
  headers?: Record<string, string>
}

const MUTATING = new Set(['POST', 'PUT', 'PATCH', 'DELETE'])

function currentLocaleHeader(): string {
  return i18n.resolvedLanguage ?? 'sr-Cyrl'
}

function readCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`))
  return match ? decodeURIComponent(match[1]) : null
}

let unauthorizedHandler: (() => void) | null = null

export function setUnauthorizedHandler(handler: (() => void) | null): void {
  unauthorizedHandler = handler
}

let csrfPromise: Promise<boolean> | null = null

async function ensureCsrf(): Promise<void> {
  if (!csrfPromise) {
    csrfPromise = fetch(`${laravelBaseUrl()}/sanctum/csrf-cookie`, {
      method: 'GET',
      credentials: 'include',
    })
      .then(() => true)
      .catch(() => false)
  }
  await csrfPromise
}

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const method = options.method ?? 'GET'
  const mutating = MUTATING.has(method)

  if (mutating) {
    await ensureCsrf()
  }

  const headers: Record<string, string> = {
    Accept: 'application/json',
    'X-Locale': currentLocaleHeader(),
    ...options.headers,
  }

  if (mutating) {
    headers['X-XSRF-TOKEN'] = readCookie('XSRF-TOKEN') ?? ''
  }

  const body = options.body !== undefined ? JSON.stringify(options.body) : undefined

  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }

  const response = await fetch(`${laravelBaseUrl()}${path}`, {
    method,
    credentials: 'include',
    headers,
    body,
  })

  if (!response.ok) {
    let data: unknown = null
    try {
      data = await response.json()
    } catch {
      // ignore
    }
    if (response.status === 401 && path !== apiPaths.me) {
      unauthorizedHandler?.()
    }
    throw new ApiError(response.status, data)
  }

  if (response.status === 204) {
    return undefined as T
  }

  const contentType = response.headers.get('content-type') ?? ''
  if (!contentType.includes('application/json')) {
    return undefined as T
  }

  return (await response.json()) as T
}

interface DownloadOptions {
  method?: 'GET' | 'POST'
  body?: unknown
}

async function download(path: string, options: DownloadOptions = {}): Promise<Blob> {
  const method = options.method ?? 'GET'
  const mutating = method !== 'GET'

  if (mutating) {
    await ensureCsrf()
  }

  const headers: Record<string, string> = {
    Accept: 'application/json',
    'X-Locale': currentLocaleHeader(),
  }

  let body: string | undefined

  if (options.body !== undefined) {
    headers['Content-Type'] = 'application/json'
    body = JSON.stringify(options.body)
  }

  if (mutating) {
    headers['X-XSRF-TOKEN'] = readCookie('XSRF-TOKEN') ?? ''
  }

  const response = await fetch(`${laravelBaseUrl()}${path}`, {
    method,
    credentials: 'include',
    headers,
    body,
  })

  if (!response.ok) {
    let data: unknown = null
    try {
      data = await response.json()
    } catch {
      // ignore
    }
    if (response.status === 401) {
      unauthorizedHandler?.()
    }
    throw new ApiError(response.status, data)
  }

  return await response.blob()
}

export function downloadBlob(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = filename
  document.body.append(anchor)
  anchor.click()
  anchor.remove()
  URL.revokeObjectURL(url)
}

async function upload<T>(path: string, file: File, fields: Record<string, string> = {}): Promise<T> {
  await ensureCsrf()

  const formData = new FormData()
  formData.append('image', file)
  Object.entries(fields).forEach(([key, value]) => formData.append(key, value))

  const response = await fetch(`${laravelBaseUrl()}${path}`, {
    method: 'POST',
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      'X-Locale': currentLocaleHeader(),
      'X-XSRF-TOKEN': readCookie('XSRF-TOKEN') ?? '',
    },
    body: formData,
  })

  if (!response.ok) {
    let data: unknown = null
    try {
      data = await response.json()
    } catch {
      // ignore
    }
    if (response.status === 401) {
      unauthorizedHandler?.()
    }
    throw new ApiError(response.status, data)
  }

  return (await response.json()) as T
}

export const api = {
  get: <T>(path: string) => request<T>(path),
  post: <T>(path: string, body?: unknown) => request<T>(path, { method: 'POST', body }),
  put: <T>(path: string, body?: unknown) => request<T>(path, { method: 'PUT', body }),
  patch: <T>(path: string, body?: unknown) => request<T>(path, { method: 'PATCH', body }),
  delete: <T>(path: string, body?: unknown) => request<T>(path, { method: 'DELETE', body }),
  del: <T>(path: string, body?: unknown) => request<T>(path, { method: 'DELETE', body }),
  upload: <T>(path: string, file: File, fields?: Record<string, string>) => upload<T>(path, file, fields),
  download: (path: string, options?: DownloadOptions) => download(path, options),
}

export const apiPaths = {
  me: '/api/v1/auth/me',
  activeLibrary: '/api/v1/auth/active-library',
  profile: '/api/v1/profile',
  profilePassword: '/api/v1/profile/password',
  login: '/api/v1/auth/login',
  logout: '/api/v1/auth/logout',
  forgotPassword: '/api/v1/auth/forgot-password',
  resetPassword: '/api/v1/auth/reset-password',
  users: '/api/v1/users',
  user: (id: number) => `/api/v1/users/${id}`,
  userBarcode: (id: number) => `/api/v1/users/${id}/barcode`,
  userBarcodePrint: (id: number, format: string) => `/api/v1/users/${id}/barcode/print?format=${format}`,
  userPassword: (id: number) => `/api/v1/users/${id}/password`,
  userForce: (id: number) => `/api/v1/users/${id}/force`,
  userLibrariesDeactivate: (id: number) => `/api/v1/users/${id}/libraries/deactivate`,
  userLibrariesActivate: (id: number) => `/api/v1/users/${id}/libraries/activate`,
  userLibraries: (id: number) => `/api/v1/users/${id}/libraries`,
  userTags: (id: number) => `/api/v1/users/${id}/tags`,
  usersTagsAssign: '/api/v1/users/tags/assign',
  usersTagsRemove: '/api/v1/users/tags/remove',
  usersMembershipsDeactivate: '/api/v1/users/memberships/deactivate',
  usersMembershipsActivate: '/api/v1/users/memberships/activate',
  usersMembershipsRemove: '/api/v1/users/memberships',
  usersBulkDeactivate: '/api/v1/users/bulk/deactivate',
  usersBulkForce: '/api/v1/users/bulk/force',
  usersBulkBarcode: '/api/v1/users/bulk/barcode',
  usersBulkBarcodePrint: '/api/v1/users/bulk/barcode/print',
  libraries: '/api/v1/libraries',
  library: (id: number) => `/api/v1/libraries/${id}`,
  libraryRestore: (id: number) => `/api/v1/libraries/${id}/restore`,
  regions: '/api/v1/regions',
  places: '/api/v1/places',
  tags: '/api/v1/tags',
  tag: (id: number) => `/api/v1/tags/${id}`,
  categories: '/api/v1/categories',
  category: (id: number) => `/api/v1/categories/${id}`,
  authors: '/api/v1/authors',
  author: (id: number) => `/api/v1/authors/${id}`,
  news: '/api/v1/news',
  newsItem: (id: number) => `/api/v1/news/${id}`,
  newsUploadImage: '/api/v1/news/upload-image',
  region: (id: number) => `/api/v1/regions/${id}`,
  place: (id: number) => `/api/v1/places/${id}`,
  roles: '/api/v1/roles',
  rolesAssignable: '/api/v1/roles/assignable',
} as const
