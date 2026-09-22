import type { ReactNode } from 'react'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import SearchOffIcon from '@mui/icons-material/SearchOff'
import { Link as RouterLink } from 'react-router-dom'

export function NotFoundPage(): ReactNode {
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
      <Stack spacing={2} sx={{ alignItems: 'center', textAlign: 'center', maxWidth: 420 }}>
        <Box
          sx={{
            width: 72,
            height: 72,
            borderRadius: '50%',
            bgcolor: 'grey.200',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
          }}
        >
          <SearchOffIcon sx={{ fontSize: 40, color: 'text.secondary' }} />
        </Box>
        <Typography variant="h4" sx={{ fontWeight: 800 }}>
          404
        </Typography>
        <Typography variant="body1" color="text.secondary">
          This page could not be found. It may have been moved or you may have followed an outdated link.
        </Typography>
        <Button component={RouterLink} to="/" variant="contained" sx={{ mt: 1 }}>
          Back to home
        </Button>
      </Stack>
    </Box>
  )
}