import { useMemo, useState, type ReactNode } from 'react'
import CssBaseline from '@mui/material/CssBaseline'
import { ThemeProvider } from '@mui/material/styles'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'

import { AuthProvider } from '../features/auth/AuthContext'
import { ThemeModeProvider, useThemeMode } from './ThemeModeContext'
import { createAppTheme } from './theme'

function ThemedApp({ children }: { children: ReactNode }): ReactNode {
  const { mode } = useThemeMode()
  // Memoized so switching themes rebuilds only the theme, never auth or query state.
  const appTheme = useMemo(() => createAppTheme(mode), [mode])

  return (
    <ThemeProvider theme={appTheme}>
      <CssBaseline />
      <AuthProvider>{children}</AuthProvider>
    </ThemeProvider>
  )
}

export function AppProviders({ children }: { children: ReactNode }): ReactNode {
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            staleTime: 30 * 1000,
            retry: 1,
            refetchOnWindowFocus: false,
          },
        },
      }),
  )

  return (
    <QueryClientProvider client={queryClient}>
      <ThemeModeProvider>
        <ThemedApp>{children}</ThemedApp>
      </ThemeModeProvider>
    </QueryClientProvider>
  )
}