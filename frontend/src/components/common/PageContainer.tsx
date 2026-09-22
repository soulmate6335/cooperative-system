import type { ReactNode } from 'react'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Container from '@mui/material/Container'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import ArrowBackIcon from '@mui/icons-material/ArrowBack'
import { Link as RouterLink } from 'react-router-dom'

export interface SimplePageContainerProps {
  title: string
  subtitle?: string
  backTo?: string
  actions?: ReactNode
  children: ReactNode
  maxWidth?: 'sm' | 'md' | 'lg' | 'xl'
}

/**
 * Shared single-page container used by every authenticated/portal page.
 * Renders a back-link + title/subtitle header (with optional action buttons)
 * and wraps `children` in a responsive MUI Container.
 */
export function SimplePageContainer({
  title,
  subtitle,
  backTo,
  actions,
  children,
  maxWidth = 'lg',
}: SimplePageContainerProps): ReactNode {
  return (
    <Container maxWidth={maxWidth} sx={{ py: 3 }}>
      <Stack spacing={2.5}>
        <Box>
          <Stack
            direction={{ xs: 'column', sm: 'row' }}
            spacing={2}
            sx={{
              flexWrap: 'wrap',
              alignItems: { xs: 'flex-start', sm: 'center' },
              justifyContent: 'space-between',
            }}
          >
            <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
              {backTo ? (
                <Button
                  component={RouterLink}
                  to={backTo}
                  startIcon={<ArrowBackIcon />}
                  variant="text"
                  color="inherit"
                  size="small"
                  sx={{ textTransform: 'none', px: 0.5 }}
                >
                  Back
                </Button>
              ) : null}
              <Box>
                <Typography variant="h5" component="h1" sx={{ fontWeight: 700 }}>
                  {title}
                </Typography>
                {subtitle ? (
                  <Typography variant="body2" color="text.secondary">
                    {subtitle}
                  </Typography>
                ) : null}
              </Box>
            </Stack>
            {actions ? <Box sx={{ display: 'flex', gap: 1, flexWrap: 'wrap' }}>{actions}</Box> : null}
          </Stack>
        </Box>
        {children}
      </Stack>
    </Container>
  )
}

/** Alias kept for existing pages that import `PageContainer` directly. */
export const PageContainer = SimplePageContainer
