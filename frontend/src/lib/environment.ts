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
