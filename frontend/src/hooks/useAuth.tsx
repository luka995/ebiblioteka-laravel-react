import { createContext, useContext, type ReactNode } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, apiPaths } from '@/lib/api'
import type { User } from '@/types'

interface LoginPayload {
  email: string
  password: string
  remember: boolean
}

interface AuthContextValue {
  user: User | null
  isLoading: boolean
  isAuthenticated: boolean
  login: (payload: LoginPayload) => Promise<void>
  logout: () => Promise<void>
}

const ME_KEY = ['auth', 'me'] as const

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()

  const meQuery = useQuery({
    queryKey: ME_KEY,
    queryFn: async () => {
      try {
        const response = await api.get<{ data: User }>(apiPaths.me)
        return response.data
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

  const user = meQuery.data ?? null

  const value: AuthContextValue = {
    user,
    isLoading: meQuery.isLoading,
    isAuthenticated: user !== null,
    login: async (payload) => {
      await loginMutation.mutateAsync(payload)
    },
    logout: async () => {
      await logoutMutation.mutateAsync()
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
