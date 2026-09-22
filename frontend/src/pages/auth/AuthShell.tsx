import type { ReactNode } from 'react'
import Box from '@mui/material/Box'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Container from '@mui/material/Container'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import { Link as RouterLink } from 'react-router-dom'

import { ThemeToggle } from '../../components/common/ThemeToggle'

interface AuthShellProps {
  title: string
  subtitle?: string
  children: ReactNode
  footer?: ReactNode
}

export function AuthShell({ title, subtitle, children, footer }: AuthShellProps): ReactNode {
  return (
    <Box
      sx={{
        position: 'relative',
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        py: 4,
        background: 'linear-gradient(135deg, #0c3d24 0%, #166534 55%, #1f7a44 100%)',
        px: 2,
      }}
    >
      <Box sx={{ position: 'absolute', top: 16, right: 16 }}>
        <ThemeToggle color="inherit" />
      </Box>
      <Container maxWidth="sm" disableGutters>
        <Stack spacing={3} sx={{ alignItems: 'center' }}>
          <Stack direction="row" spacing={1.5} component={RouterLink} to="/" sx={{ textDecoration: 'none', color: 'white', alignItems: 'center' }}>
            <Box
              sx={{
                width: 44,
                height: 44,
                borderRadius: '50%',
                bgcolor: 'secondary.main',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontWeight: 800,
                color: 'white',
                fontSize: 20,
              }}
            >
              CS
            </Box>
            <Box>
              <Typography variant="h6" sx={{ lineHeight: 1.1 }}>
                Cooperative Society
              </Typography>
              <Typography variant="caption" sx={{ opacity: 0.85, display: 'block' }}>
                Member-owned finance
              </Typography>
            </Box>
          </Stack>

          <Card sx={{ width: '100%', borderRadius: 3 }} variant="outlined">
            <CardContent sx={{ p: { xs: 3, sm: 4 } }}>
              <Typography variant="h5" component="h1" gutterBottom>
                {title}
              </Typography>
              {subtitle ? (
                <Typography variant="body2" color="text.secondary" sx={{ mb: 3 }}>
                  {subtitle}
                </Typography>
              ) : null}
              {children}
            </CardContent>
          </Card>

          {footer ? <Typography variant="body2" sx={{ color: 'rgba(255,255,255,0.9)' }}>{footer}</Typography> : null}
        </Stack>
      </Container>
    </Box>
  )
}