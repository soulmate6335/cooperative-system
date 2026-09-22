import type { ReactNode } from 'react'
import Box from '@mui/material/Box'
import Skeleton from '@mui/material/Skeleton'

interface TableSkeletonProps {
  rows?: number
  columns?: number
}

export function TableSkeleton({ rows = 6, columns = 5 }: TableSkeletonProps): ReactNode {
  return (
    <Box sx={{ p: 2 }}>
      {Array.from({ length: rows }, (_, rowIndex) => (
        <Box key={rowIndex} sx={{ display: 'flex', gap: 2, py: 1 }}>
          {Array.from({ length: columns }, (_, columnIndex) => (
            <Skeleton
              key={columnIndex}
              variant="text"
              width={`${100 / columns}%`}
              height={24}
              sx={{ flexGrow: 1 }}
            />
          ))}
        </Box>
      ))}
    </Box>
  )
}

export function CardSkeleton(): ReactNode {
  return (
    <Box sx={{ display: 'flex', gap: 2, flexWrap: 'wrap' }}>
      {Array.from({ length: 4 }, (_, index) => (
        <Skeleton key={index} variant="rounded" height={120} sx={{ flexGrow: 1, minWidth: 220 }} />
      ))}
    </Box>
  )
}