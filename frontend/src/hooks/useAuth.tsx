import { createContext, useCallback, useContext, useEffect, type ReactNode } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, apiPaths, setUnauthorizedHandler } from '@/lib/api'
import type { ActiveLibrary, GlobalPermissions, LibraryOption, User } from '@/types'

interface LoginPayload {
  email: string
  password: string
  remember: boolean
}

interface MeEnvelope {
  user: User
  permissions: GlobalPermissions
  libraries: LibraryOption[]
  activeLibrary: ActiveLibrary
}

interface AuthContextValue {
  user: User | null
  permissions: GlobalPermissions
  libraries: LibraryOption[]
  activeLibrary: ActiveLibrary
  isLoading: boolean
  isAuthenticated: boolean
  can: (permission: string) => boolean
  login: (payload: LoginPayload) => Promise<void>
  logout: () => Promise<void>
  setActiveLibrary: (libraryId: number | null) => Promise<void>
}

const ME_KEY = ['auth', 'me'] as const

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()

  const meQuery = useQuery({
    queryKey: ME_KEY,
    queryFn: async (): Promise<MeEnvelope | null> => {
      try {
        const response = await api.get<{
          data: User
          permissions: GlobalPermissions
          selectable_libraries: LibraryOption[]
          active_library: LibraryOption | null
        }>(apiPaths.me)
        return {
          user: response.data,
          permissions: response.permissions ?? {},
          libraries: response.selectable_libraries ?? [],
          activeLibrary: response.active_library ?? null,
        }
      } catch (error) {
        if (error instanceof Error && 'status' in error && (error as { status: number }).status === 401) {
          return null
        }
        throw error
      }
    },
    retry: false,
    staleTime: 30_000,
  })

  const loginMutation = useMutation({
    mutationFn: (payload: LoginPayload) => api.post(apiPaths.login, payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ME_KEY })
    },
  })

  const logoutMutation = useMutation({
    mutationFn: () => api.post(apiPaths.logout),
    onSuccess: async () => {
      queryClient.setQueryData(ME_KEY, null)
      await queryClient.invalidateQueries({ queryKey: ME_KEY })
    },
  })

  const setActiveLibraryMutation = useMutation({
    mutationFn: (libraryId: number | null) => api.put(apiPaths.activeLibrary, { library_id: libraryId }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ME_KEY })
    },
  })

  const user = meQuery.data?.user ?? null
  const permissions = meQuery.data?.permissions ?? {}
  const libraries = meQuery.data?.libraries ?? []
  const activeLibrary = meQuery.data?.activeLibrary ?? null

  useEffect(() => {
    setUnauthorizedHandler(() => {
      queryClient.setQueryData(ME_KEY, null)
    })
    return () => setUnauthorizedHandler(null)
  }, [queryClient])

  const can = useCallback(
    (permission: string) => {
      const [section, action] = permission.split('.')
      return permissions[section]?.[action] === true
    },
    [permissions],
  )

  const value: AuthContextValue = {
    user,
    permissions,
    libraries,
    activeLibrary,
    isLoading: meQuery.isLoading,
    isAuthenticated: user !== null,
    can,
    login: async (payload) => {
      await loginMutation.mutateAsync(payload)
    },
    logout: async () => {
      await logoutMutation.mutateAsync()
    },
    setActiveLibrary: async (libraryId) => {
      await setActiveLibraryMutation.mutateAsync(libraryId)
    },
  }

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext)
  if (!ctx) {
    throw new Error('useAuth must be used within an AuthProvider')
  }
  return ctx
}
