import { type ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { PageLoader } from '@/components/ui/loader'
import { loginRedirectQuery, safeInternalRedirect } from '@/lib/login-redirect'

export function RequireAuth({ children }: { children: ReactNode }) {
  const { isLoading, isAuthenticated } = useAuth()
  const location = useLocation()

  if (isLoading) {
    return (
      <div className="flex min-h-dvh items-center justify-center">
        <PageLoader />
      </div>
    )
  }

  if (!isAuthenticated) {
    return <Navigate to={loginRedirectQuery(location.pathname, location.search)} replace />
  }

  return <>{children}</>
}

export function GuestOnly({ children }: { children: ReactNode }) {
  const { isLoading, isAuthenticated } = useAuth()
  const location = useLocation()

  if (isLoading) {
    return (
      <div className="flex min-h-dvh items-center justify-center">
        <PageLoader />
      </div>
    )
  }

  if (isAuthenticated) {
    const redirect = safeInternalRedirect(new URLSearchParams(location.search).get('redirect'))

    return <Navigate to={redirect ?? '/'} replace />
  }

  return <>{children}</>
}
