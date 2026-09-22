import type { ReactNode } from 'react'
import Alert from '@mui/material/Alert'
import Button from '@mui/material/Button'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'

interface ErrorStateProps {
  message?: string
  onRetry?: () => void
}

export function ErrorState({ message = 'Something went wrong while loading this page.', onRetry }: ErrorStateProps): ReactNode {
  return (
    <Stack spacing={2} sx={{ py: 4, alignItems: 'flex-start' }}>
      <Alert severity="error" sx={{ width: '100%' }}>
        <Typography variant="body2">{message}</Typography>
      </Alert>
      {onRetry ? (
        <Button variant="outlined" onClick={onRetry}>
          Try again
        </Button>
      ) : null}
    </Stack>
  )
}