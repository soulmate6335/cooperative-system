import type { ReactNode } from 'react'
import Box from '@mui/material/Box'
import { useLocation } from 'react-router-dom'

/**
 * Wraps routed content in a subtle fade/slide entrance. Keying on the pathname
 * triggers the animation only when navigating between routes — dialogs, forms
 * and in-page state are untouched.
 */
export function PageTransition({ children }: { children: ReactNode }): ReactNode {
  const { pathname } = useLocation()
  return (
    <Box key={pathname} className="cs-page-enter" sx={{ flexGrow: 1, display: 'flex', flexDirection: 'column' }}>
      {children}
    </Box>
  )
}