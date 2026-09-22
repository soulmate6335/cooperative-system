import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'

import { getToken, setToken } from '../../services/api'
import { fetchMe, login as apiLogin, logout as apiLogout } from '../../services/auth'
import type { User } from '../../types'

const AUTH_QUERY_KEY = ['auth', 'me'] as const

interface AuthContextValue {
  user: User | null
  isLoading: boolean
  isAuthenticated: boolean
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
  hasRole: (...roles: string[]) => boolean
  hasPermission: (permission: string) => boolean
  isMember: boolean
  isCommittee: boolean
  isFinance: boolean
  isAdmin: boolean
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }): ReactNode {
  const queryClient = useQueryClient()
  const token = getToken()

  const meQuery = useQuery({
    queryKey: AUTH_QUERY_KEY,
    queryFn: fetchMe,
    enabled: Boolean(token),
    staleTime: 5 * 60 * 1000,
    retry: false,
  })

  // React state mirror of the authenticated user. Set directly from the login
  // response so the UI can navigate immediately after signing in — this does
  // not depend on React Query observer notification, which is what keeps
  // `user` correct even if the `/auth/me` query has not been (re)subscribed.
  const [sessionUser, setSessionUser] = useState<User | null>(null)

  // Prefer the fetched `/auth/me` result when available; the session mirror
  // covers the instant-after-login window (and any query-observer quirk).
  const user = meQuery.data ?? sessionUser
  const isLoading = Boolean(token) && meQuery.isPending && sessionUser === null

  const login = useCallback(
    async (email: string, password: string) => {
      const { token: newToken, user: loggedInUser } = await apiLogin({ email, password })
      setToken(newToken)
      // Upsert the cache entry directly (fresh, so no duplicate `/auth/me`
      // fetch) without removing the query — removing it detaches the live
      // `meQuery` observer and the cache update never reaches the UI.
      queryClient.setQueryData([...AUTH_QUERY_KEY], loggedInUser)
      setSessionUser(loggedInUser)
    },
    [queryClient],
  )

  const logout = useCallback(async () => {
    try {
      await apiLogout()
    } catch {
      // The session may already be invalid; local state must be cleared regardless.
    }
    setToken(null)
    queryClient.clear()
    setSessionUser(null)
  }, [queryClient])

  const value = useMemo<AuthContextValue>(() => {
    const roles = user?.roles ?? []
    const permissions = user?.permissions ?? []

    return {
      user,
      isLoading,
      isAuthenticated: Boolean(user),
      login,
      logout,
      hasRole: (...roleNames: string[]) => roleNames.some((role) => roles.includes(role)),
      hasPermission: (permission: string) => permissions.includes(permission),
      isMember: roles.includes('member'),
      isCommittee: roles.includes('committee_officer'),
      isFinance: roles.includes('finance_officer'),
      isAdmin: roles.includes('admin') || roles.includes('super_admin'),
    }
  }, [user, isLoading, login, logout])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

// eslint-disable-next-line react-refresh/only-export-components -- useAuth hook co-located with AuthProvider
export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)
  if (!context) {
    throw new Error('useAuth must be used inside <AuthProvider>')
  }
  return context
}