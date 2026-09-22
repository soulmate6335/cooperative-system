import type { ReactNode } from 'react'
import Box from '@mui/material/Box'
import Typography from '@mui/material/Typography'
import { statusPillSx, statusStyle } from '../../utils/status'

interface StatusPillProps {
  status: string | null | undefined
}

export function StatusPill({ status }: StatusPillProps): ReactNode {
  const raw = status ?? ''
  const { label } = statusStyle(raw)
  return (
    <Box component="span" sx={statusPillSx(raw)}>
      {label}
    </Box>
  )
}

export function StatusDetail({ label, value }: { label: string; value: string | null | undefined }): ReactNode {
  return (
    <Box>
      <Typography variant="caption" color="text.secondary" sx={{ mb: 0.5, display: 'block' }}>
        {label}
      </Typography>
      <StatusPill status={value} />
    </Box>
  )
}