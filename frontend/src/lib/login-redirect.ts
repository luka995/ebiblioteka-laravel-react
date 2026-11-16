/**
 * Validira `redirect` parametar posle prijave.
 *
 * Dozvoljene su samo relativne putanje unutar dashboarda (npr.
 * `/catalog/books/12`), cime se sprecava open-redirect na eksterni sajt.
 */
export function safeInternalRedirect(raw: string | null | undefined): string | null {
  if (!raw) return null

  const value = raw.trim()

  if (!value.startsWith('/') || value.startsWith('//')) {
    return null
  }

  // Ne vracamo korisnika na same auth stranice.
  if (value === '/login' || value.startsWith('/login?') || value.startsWith('/forgot-password') || value.startsWith('/reset-password')) {
    return null
  }

  return value
}

export function loginRedirectQuery(pathname: string, search: string): string {
  const target = `${pathname}${search}`

  return `/login?redirect=${encodeURIComponent(target)}`
}
