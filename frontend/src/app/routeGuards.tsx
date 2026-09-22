import type { ReactNode } from 'react'
import Box from '@mui/material/Box'
import CircularProgress from '@mui/material/CircularProgress'
import { Navigate } from 'react-router-dom'

import { useAuth } from '../features/auth/AuthContext'

export function FullScreenLoader(): ReactNode {
  return (
    <Box sx={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
      <CircularProgress />
    </Box>
  )
}

export function RequireAuth({ children }: { children: ReactNode }): ReactNode {
  const { user, isLoading } = useAuth()
  if (isLoading) {
    return <FullScreenLoader />
  }
  if (!user) {
    return <Navigate to="/login" replace />
  }
  return children
}

export function RequireRoles({ roles, children }: { roles: string[]; children: ReactNode }): ReactNode {
  const { hasRole } = useAuth()
  if (!hasRole(...roles)) {
    return <Navigate to="/unauthorized" replace />
  }
  return children
}