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

export const api = {
  get: <T>(path: string) => request<T>(path),
  post: <T>(path: string, body?: unknown) => request<T>(path, { method: 'POST', body }),
  del: <T>(path: string) => request<T>(path, { method: 'DELETE' }),
}

export const apiPaths = {
  me: '/api/v1/auth/me',
  login: '/api/v1/auth/login',
  logout: '/api/v1/auth/logout',
  forgotPassword: '/api/v1/auth/forgot-password',
  resetPassword: '/api/v1/auth/reset-password',
  users: '/api/v1/users',
  barcodeNext: '/api/v1/users/barcode/next',
  libraries: '/api/v1/libraries',
  regions: '/api/v1/regions',
  places: '/api/v1/places',
  roles: '/api/v1/roles',
  rolesAssignable: '/api/v1/roles/assignable',
} as const
