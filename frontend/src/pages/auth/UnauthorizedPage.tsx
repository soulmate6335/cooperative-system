import type { ReactNode } from 'react'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import SecurityIcon from '@mui/icons-material/Security'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import { Link as RouterLink, useNavigate } from 'react-router-dom'
import { useAuth } from '../../features/auth/AuthContext'

export function UnauthorizedPage(): ReactNode {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  const handleLogout = (): void => {
    void logout().finally(() => {
      navigate('/login', { replace: true })
    })
  }

  return (
    <Box
      sx={{
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        p: 3,
        bgcolor: 'background.default',
      }}
    >
      <Stack spacing={2} sx={{ alignItems: 'center', textAlign: 'center', maxWidth: 440 }}>
        <Box
          sx={{
            width: 72,
            height: 72,
            borderRadius: '50%',
            bgcolor: 'warning.light',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
          }}
        >
          <SecurityIcon sx={{ fontSize: 40, color: 'white' }} />
        </Box>
        <Typography variant="h5" sx={{ fontWeight: 800 }}>
          You don&apos;t have access to this area
        </Typography>
        <Typography variant="body1" color="text.secondary">
          {user ? `You are signed in as ${user.name}, but your current role is not allowed to open this page.` : 'Please sign in to continue.'}
        </Typography>
        <Stack direction="row" spacing={1.5}>
          <Button component={RouterLink} to="/" variant="outlined">
            Go to site
          </Button>
          {user ? (
            <Button variant="contained" onClick={handleLogout}>
              Switch account
            </Button>
          ) : (
            <Button component={RouterLink} to="/login" variant="contained">
              Sign in
            </Button>
          )}
        </Stack>
      </Stack>
    </Box>
  )
}