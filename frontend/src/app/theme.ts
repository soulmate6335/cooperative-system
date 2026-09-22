import { createTheme, type Theme } from '@mui/material/styles'

export type ThemeMode = 'light' | 'dark'

const GREEN = {
  lightMode: {
    main: '#166534',
    light: '#4c8c4a',
    dark: '#0c3d24',
  },
  darkMode: {
    main: '#4c9a63',
    light: '#6fba85',
    dark: '#2f7a4d',
  },
}

const AMBER = {
  lightMode: {
    main: '#b45309',
    light: '#e9b44c',
    dark: '#7c3d05',
  },
  darkMode: {
    main: '#d99234',
    light: '#f0b95c',
    dark: '#9a5b12',
  },
}

const SURFACES = {
  lightMode: {
    default: '#f5f7f5',
    paper: '#ffffff',
    divider: '#e5e9e5',
    textPrimary: '#1f2937',
    textSecondary: '#5b6572',
    tableHeadBg: '#fafbfa',
    tableHeadColor: '#4b5563',
  },
  darkMode: {
    default: '#0c1511',
    paper: '#15211b',
    divider: '#26342c',
    textPrimary: '#e6ede9',
    textSecondary: '#9db0a4',
    tableHeadBg: '#1a2720',
    tableHeadColor: '#b3c4b8',
  },
}

/**
 * Centralized application theme. Builds a full MUI theme for the requested
 * mode, preserving the cooperative's green brand identity in both themes.
 */
export function createAppTheme(mode: ThemeMode): Theme {
  const green = mode === 'dark' ? GREEN.darkMode : GREEN.lightMode
  const amber = mode === 'dark' ? AMBER.darkMode : AMBER.lightMode
  const surfaces = mode === 'dark' ? SURFACES.darkMode : SURFACES.lightMode

  return createTheme({
    palette: {
      mode,
      primary: {
        main: green.main,
        light: green.light,
        dark: green.dark,
        contrastText: '#ffffff',
      },
      secondary: {
        main: amber.main,
        light: amber.light,
        dark: amber.dark,
        contrastText: '#ffffff',
      },
      background: {
        default: surfaces.default,
        paper: surfaces.paper,
      },
      success: { main: mode === 'dark' ? '#3fae63' : '#15803d' },
      warning: { main: mode === 'dark' ? '#d9992f' : '#b45309' },
      error: { main: mode === 'dark' ? '#e06060' : '#b91c1c' },
      info: { main: mode === 'dark' ? '#4799c9' : '#0369a1' },
      divider: surfaces.divider,
      text: {
        primary: surfaces.textPrimary,
        secondary: surfaces.textSecondary,
      },
    },
    shape: {
      borderRadius: 10,
    },
    typography: {
      fontFamily: "'Inter', 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif",
      h4: {
        fontWeight: 700,
      },
      h5: {
        fontWeight: 700,
      },
      h6: {
        fontWeight: 600,
      },
      button: {
        textTransform: 'none',
        fontWeight: 600,
      },
    },
    components: {
      MuiButton: {
        defaultProps: {
          disableElevation: true,
        },
        styleOverrides: {
          root: {
            borderRadius: 8,
            paddingInline: 16,
            transition:
              'background-color 180ms ease, border-color 180ms ease, color 180ms ease, box-shadow 180ms ease, transform 180ms ease',
          },
        },
      },
      MuiCard: {
        styleOverrides: {
          root: {
            borderRadius: 14,
            border: '1px solid',
            borderColor: 'divider',
          },
        },
      },
      MuiPaper: {
        defaultProps: {
          elevation: 0,
        },
      },
      MuiTextField: {
        defaultProps: {
          size: 'small',
          fullWidth: true,
        },
      },
      MuiChip: {
        styleOverrides: {
          root: {
            fontWeight: 600,
            transition: 'background-color 180ms ease, color 180ms ease, border-color 180ms ease',
          },
        },
      },
      MuiTableCell: {
        styleOverrides: {
          head: {
            fontWeight: 700,
            color: surfaces.tableHeadColor,
            backgroundColor: surfaces.tableHeadBg,
          },
        },
      },
      MuiDialogTitle: {
        styleOverrides: {
          root: {
            fontWeight: 700,
          },
        },
      },
      MuiListItemButton: {
        styleOverrides: {
          root: {
            transition: 'background-color 180ms ease, color 180ms ease',
          },
        },
      },
      MuiCssBaseline: {
        styleOverrides: {
          body: {
            transition: 'background-color 220ms ease, color 220ms ease',
          },
        },
      },
    },
  })
}

/** Light theme kept as the default export for compatibility. */
export const theme = createAppTheme('light')