import { useState, type ReactNode } from 'react'
import AppBar from '@mui/material/AppBar'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Container from '@mui/material/Container'
import Divider from '@mui/material/Divider'
import Drawer from '@mui/material/Drawer'
import IconButton from '@mui/material/IconButton'
import List from '@mui/material/List'
import ListItem from '@mui/material/ListItem'
import ListItemButton from '@mui/material/ListItemButton'
import ListItemText from '@mui/material/ListItemText'
import MenuIcon from '@mui/icons-material/Menu'
import Stack from '@mui/material/Stack'
import Toolbar from '@mui/material/Toolbar'
import Typography from '@mui/material/Typography'
import { Link as RouterLink, Outlet } from 'react-router-dom'

import { PageTransition } from '../common/PageTransition'
import { ThemeToggle } from '../common/ThemeToggle'

interface NavLink {
  label: string
  to: string
}

const PUBLIC_LINKS: NavLink[] = [
  { label: 'Home', to: '/' },
  { label: 'About', to: '/about' },
  { label: 'Contact', to: '/contact' },
]

export function PublicLayout(): ReactNode {
  const [drawerOpen, setDrawerOpen] = useState(false)

  return (
    <Box sx={{ display: 'flex', flexDirection: 'column', minHeight: '100vh' }}>
      <AppBar position="static" color="primary" elevation={0}>
        <Container maxWidth="lg">
          <Toolbar disableGutters sx={{ gap: 1 }}>
            <Box component={RouterLink} to="/" sx={{ display: 'flex', alignItems: 'center', gap: 1, textDecoration: 'none', color: 'inherit', mr: { xs: 0, md: 3 } }}>
              <Box
                sx={{
                  width: 38,
                  height: 38,
                  borderRadius: '50%',
                  bgcolor: 'secondary.main',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  fontWeight: 700,
                  color: 'white',
                  fontSize: 18,
                }}
              >
                CS
              </Box>
              <Box sx={{ display: { xs: 'none', sm: 'block' } }}>
                <Typography variant="h6" sx={{ lineHeight: 1.1 }}>
                  Cooperative Society
                </Typography>
                <Typography variant="caption" sx={{ opacity: 0.85, display: 'block', lineHeight: 1.2 }}>
                  Mutual Savings • Loans • Progress
                </Typography>
              </Box>
            </Box>

            <Box sx={{ flexGrow: 1 }} />

            <Box sx={{ display: { xs: 'none', md: 'flex' }, alignItems: 'center', gap: 1 }}>
              {PUBLIC_LINKS.map((link) => (
                <Button key={link.to} component={RouterLink} to={link.to} color="inherit" sx={{ textTransform: 'none' }}>
                  {link.label}
                </Button>
              ))}
              <ThemeToggle color="inherit" />
              <Divider orientation="vertical" flexItem sx={{ bgcolor: 'rgba(255,255,255,0.3)', mx: 1 }} />
              <Button component={RouterLink} to="/login" color="inherit" sx={{ textTransform: 'none' }}>
                Login
              </Button>
              <Button
                component={RouterLink}
                to="/register"
                variant="contained"
                color="secondary"
                sx={{ textTransform: 'none' }}
              >
                Register
              </Button>
            </Box>

            <IconButton
              color="inherit"
              edge="end"
              onClick={() => setDrawerOpen(true)}
              sx={{ display: { md: 'none' } }}
              aria-label="Open navigation menu"
            >
              <MenuIcon />
            </IconButton>
          </Toolbar>
        </Container>
      </AppBar>

      <Drawer anchor="right" open={drawerOpen} onClose={() => setDrawerOpen(false)}>
        <Box sx={{ width: 280, p: 2 }}>
          <Stack direction="row" spacing={1} sx={{ mb: 2, alignItems: 'center' }}>
            <Box
              sx={{
                width: 34,
                height: 34,
                borderRadius: '50%',
                bgcolor: 'secondary.main',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontWeight: 700,
                color: 'white',
              }}
            >
              CS
            </Box>
            <Typography variant="h6">Cooperative Society</Typography>
            <Box sx={{ flexGrow: 1 }} />
            <ThemeToggle />
          </Stack>
          <List>
            {PUBLIC_LINKS.map((link) => (
              <ListItem key={link.to} disablePadding>
                <ListItemButton component={RouterLink} to={link.to} onClick={() => setDrawerOpen(false)}>
                  <ListItemText primary={link.label} />
                </ListItemButton>
              </ListItem>
            ))}
            <Divider sx={{ my: 1 }} />
            <ListItem disablePadding>
              <ListItemButton component={RouterLink} to="/login" onClick={() => setDrawerOpen(false)}>
                <ListItemText primary="Login" />
              </ListItemButton>
            </ListItem>
            <ListItem disablePadding>
              <ListItemButton component={RouterLink} to="/register" onClick={() => setDrawerOpen(false)}>
                <ListItemText primary="Register" />
              </ListItemButton>
            </ListItem>
          </List>
        </Box>
      </Drawer>

      <Box component="main" sx={{ flexGrow: 1, display: 'flex', flexDirection: 'column' }}>
        <PageTransition>
          <Outlet />
        </PageTransition>
      </Box>

      <Box component="footer" sx={{ bgcolor: 'grey.900', color: 'grey.300', py: 4 }}>
        <Container maxWidth="lg">
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={3} sx={{ justifyContent: 'space-between' }}>
            <Box>
              <Typography variant="h6" color="white">
                Cooperative Society
              </Typography>
              <Typography variant="body2" sx={{ mt: 0.5, maxWidth: 360 }}>
                A member-owned cooperative supporting savings, contributions and affordable loans for our community.
              </Typography>
            </Box>
            <Stack direction="row" spacing={3}>
              <Box>
                <Typography variant="subtitle2" color="white" sx={{ mb: 1 }}>
                  Explore
                </Typography>
                <Stack spacing={0.5}>
                  {PUBLIC_LINKS.map((link) => (
                    <Typography key={link.to} component={RouterLink} to={link.to} variant="body2" sx={{ color: 'inherit', textDecoration: 'none', '&:hover': { color: 'white' } }}>
                      {link.label}
                    </Typography>
                  ))}
                </Stack>
              </Box>
              <Box>
                <Typography variant="subtitle2" color="white" sx={{ mb: 1 }}>
                  Access
                </Typography>
                <Stack spacing={0.5}>
                  <Typography component={RouterLink} to="/login" variant="body2" sx={{ color: 'inherit', textDecoration: 'none', '&:hover': { color: 'white' } }}>
                    Member login
                  </Typography>
                  <Typography component={RouterLink} to="/register" variant="body2" sx={{ color: 'inherit', textDecoration: 'none', '&:hover': { color: 'white' } }}>
                    Register
                  </Typography>
                </Stack>
              </Box>
            </Stack>
          </Stack>
          <Typography variant="caption" sx={{ display: 'block', mt: 3, opacity: 0.7 }}>
            © {new Date().getFullYear()} Cooperative Society. All rights reserved.
          </Typography>
        </Container>
      </Box>
    </Box>
  )
}