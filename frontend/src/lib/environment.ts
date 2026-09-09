const LOCAL_HOSTS = ['localhost', '127.0.0.1', '::1']

function isLocalHost(): boolean {
  const host = window.location.hostname
  return LOCAL_HOSTS.includes(host) || host.endsWith('.local')
}

export function laravelBaseUrl(): string {
  const override = import.meta.env.VITE_LARAVEL_URL as string | undefined
  if (override) return override.replace(/\/$/, '')

  return isLocalHost() ? 'http://localhost:81' : 'https://demo.ebiblioteka.rs'
}

/**
 * Pretvara root-relative putanju (npr. /storage/news/slika.png) u pun URL
 * prema backendu. Admin (drugi origin) ne može da koristi relativnu putanju.
 */
export function storageUrl(path: string | null | undefined): string | null {
  if (!path) return null
  if (/^https?:\/\//.test(path)) return path
  return `${laravelBaseUrl()}${path.startsWith('/') ? path : `/${path}`}`
}
