import type { ReactNode } from 'react'
import IconButton from '@mui/material/IconButton'
import Tooltip from '@mui/material/Tooltip'
import LightModeIcon from '@mui/icons-material/LightMode'
import DarkModeIcon from '@mui/icons-material/DarkMode'

import { useThemeMode } from '../../app/ThemeModeContext'

interface ThemeToggleProps {
  /**
   * `inherit` renders the button in the current color (used on branded green
   * headers); `default` uses the standard MUI icon color.
   */
  color?: 'inherit' | 'default'
}

export function ThemeToggle({ color = 'default' }: ThemeToggleProps): ReactNode {
  const { mode, toggleTheme } = useThemeMode()
  const isDark = mode === 'dark'
  const label = isDark ? 'Switch to light mode' : 'Switch to dark mode'

  return (
    <Tooltip title={label}>
      <IconButton
        onClick={toggleTheme}
        color={color}
        size="small"
        aria-label={label}
        sx={{
          border: '1px solid',
          borderColor: color === 'inherit' ? 'rgba(255,255,255,0.35)' : 'divider',
          transition: 'background-color 200ms ease, border-color 200ms ease, color 200ms ease',
          '&:hover': {
            borderColor: color === 'inherit' ? 'rgba(255,255,255,0.7)' : 'primary.main',
            bgcolor: color === 'inherit' ? 'rgba(255,255,255,0.12)' : 'action.hover',
          },
        }}
      >
        {isDark ? <LightModeIcon fontSize="small" /> : <DarkModeIcon fontSize="small" />}
      </IconButton>
    </Tooltip>
  )
}