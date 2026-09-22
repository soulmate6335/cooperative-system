import { useMemo, useState, type ReactNode } from 'react'
import AppBar from '@mui/material/AppBar'
import Avatar from '@mui/material/Avatar'
import Box from '@mui/material/Box'
import Divider from '@mui/material/Divider'
import Drawer from '@mui/material/Drawer'
import IconButton from '@mui/material/IconButton'
import List from '@mui/material/List'
import ListItem from '@mui/material/ListItem'
import ListItemButton from '@mui/material/ListItemButton'
import ListItemIcon from '@mui/material/ListItemIcon'
import ListItemText from '@mui/material/ListItemText'
import ListSubheader from '@mui/material/ListSubheader'
import Menu from '@mui/material/Menu'
import MenuItem from '@mui/material/MenuItem'
import Toolbar from '@mui/material/Toolbar'
import Tooltip from '@mui/material/Tooltip'
import Typography from '@mui/material/Typography'
import { Outlet, useLocation, useNavigate } from 'react-router-dom'
import { Link as RouterLink } from 'react-router-dom'
import DashboardIcon from '@mui/icons-material/Dashboard'
import FactCheckIcon from '@mui/icons-material/FactCheck'
import GroupIcon from '@mui/icons-material/Group'
import ListAltIcon from '@mui/icons-material/ListAlt'
import PaymentsIcon from '@mui/icons-material/Payments'
import PersonIcon from '@mui/icons-material/Person'
import RequestQuoteIcon from '@mui/icons-material/RequestQuote'
import SavingsIcon from '@mui/icons-material/Savings'
import SettingsIcon from '@mui/icons-material/Settings'
import AssignmentIndIcon from '@mui/icons-material/AssignmentInd'
import Inventory2Icon from '@mui/icons-material/Inventory2'
import MenuIcon from '@mui/icons-material/Menu'
import ReceiptLongIcon from '@mui/icons-material/ReceiptLong'
import type { SvgIconComponent } from '@mui/icons-material'

import { useAuth } from '../../features/auth/AuthContext'
import { PageTransition } from '../common/PageTransition'
import { ThemeToggle } from '../common/ThemeToggle'

interface NavItem {
  label: string
  to: string
  icon: SvgIconComponent
}

interface NavSection {
  title: string
  items: NavItem[]
}

const DRAWER_WIDTH = 264

export function AppLayout(): ReactNode {
  const { user, isMember, isCommittee, isAdmin, isFinance, logout } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [mobileOpen, setMobileOpen] = useState(false)
  const [userMenuAnchor, setUserMenuAnchor] = useState<HTMLElement | null>(null)

  const sections = useMemo<NavSection[]>(() => {
    const memberItems: NavItem[] = [
      { label: 'Dashboard', to: '/member', icon: DashboardIcon },
      { label: 'Loan Eligibility', to: '/member/eligibility', icon: FactCheckIcon },
      { label: 'Loan Products', to: '/member/loans/products', icon: ListAltIcon },
      { label: 'My Applications', to: '/member/loans/applications', icon: RequestQuoteIcon },
      { label: 'Savings & Contributions', to: '/member/accounts', icon: SavingsIcon },
      { label: 'Profile', to: '/member/profile', icon: PersonIcon },
    ]
    const committeeItems: NavItem[] = [
      { label: 'Assigned Applications', to: '/committee', icon: FactCheckIcon },
    ]
    const adminItems: NavItem[] = [
      { label: 'Dashboard', to: '/admin', icon: DashboardIcon },
      { label: 'Member Applications', to: '/admin/membership-applications', icon: GroupIcon },
      { label: 'Loan Applications', to: '/admin/loans/applications', icon: RequestQuoteIcon },
      { label: 'Loan Products', to: '/admin/loan-products', icon: Inventory2Icon },
      { label: 'Committee Meetings', to: '/admin/committee-meetings', icon: AssignmentIndIcon },
      { label: 'Payments', to: '/admin/payments', icon: PaymentsIcon },
      { label: 'Receipts & Verification', to: '/admin/receipts', icon: ReceiptLongIcon },
      { label: 'Payment Methods', to: '/admin/payment-methods', icon: SettingsIcon },
      { label: 'Financial Accounts', to: '/admin/financial/accounts', icon: SavingsIcon },
      { label: 'Members', to: '/admin/members', icon: GroupIcon },
    ]
    const financeItems: NavItem[] = [
      { label: 'Dashboard', to: '/finance', icon: DashboardIcon },
      { label: 'Record Payment', to: '/admin/payments', icon: PaymentsIcon },
      { label: 'Verify & Receipts', to: '/admin/receipts', icon: ReceiptLongIcon },
    ]

    const result: NavSection[] = []
    if (isMember) {
      result.push({ title: 'Member Portal', items: memberItems })
    }
    if (isCommittee) {
      result.push({ title: 'Committee', items: committeeItems })
    }
    if (isAdmin) {
      result.push({ title: 'Administration', items: adminItems })
    }
    if (isFinance) {
      result.push({ title: 'Finance', items: financeItems })
    }
    return result
  }, [isMember, isCommittee, isAdmin, isFinance])

  const handleLogout = async (): Promise<void> => {
    setUserMenuAnchor(null)
    await logout()
    navigate('/login')
  }

  const navContent = (
    <Box>
      <Toolbar sx={{ gap: 1 }}>
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
            fontSize: 15,
          }}
        >
          CS
        </Box>
        <Box>
          <Typography variant="subtitle1" sx={{ lineHeight: 1.1, fontWeight: 700 }}>
            Cooperative System
          </Typography>
          <Typography variant="caption" color="text.secondary">
            {isAdmin ? 'Administrator' : isCommittee ? 'Committee' : isFinance ? 'Finance' : 'Member'} Portal
          </Typography>
        </Box>
      </Toolbar>
      <Divider />
      {sections.map((section) => {
        // Highlight only the most specific matching item so section index routes
        // (e.g. /member, /admin) do not stay active on nested pages.
        const activeTo = section.items.reduce<NavItem | null>((best, candidate) => {
          const matches = location.pathname === candidate.to || location.pathname.startsWith(`${candidate.to}/`)
          if (!matches) {
            return best
          }
          return best === null || candidate.to.length > best.to.length ? candidate : best
        }, null)?.to
        return (
          <List
            key={section.title}
            subheader={
              <ListSubheader component="div" sx={{ bgcolor: 'transparent', fontWeight: 700, color: 'text.secondary' }}>
                {section.title}
              </ListSubheader>
            }
          >
            {section.items.map((item) => {
              const selected = activeTo === item.to
              return (
                <ListItem key={item.to} disablePadding sx={{ display: 'block' }}>
                  <ListItemButton
                    component={RouterLink}
                    to={item.to}
                    selected={selected}
                    sx={{
                      mx: 1,
                      borderRadius: 2,
                      '&.Mui-selected': {
                        bgcolor: 'primary.main',
                        color: 'white',
                        '&:hover': { bgcolor: 'primary.dark' },
                        '& .MuiListItemIcon-root': { color: 'inherit' },
                      },
                    }}
                  >
                    <ListItemIcon sx={{ minWidth: 38, color: selected ? 'inherit' : 'text.secondary' }}>
                      <item.icon fontSize="small" />
                    </ListItemIcon>
                    <ListItemText primary={item.label} sx={{ '& .MuiListItemText-primary': { fontSize: '0.9rem', fontWeight: 500 } }} />
                  </ListItemButton>
                </ListItem>
              )
            })}
          </List>
        )
      })}
    </Box>
  )

  return (
    <Box sx={{ display: 'flex', minHeight: '100vh' }}>
      <Drawer
        variant="temporary"
        open={mobileOpen}
        onClose={() => setMobileOpen(false)}
        ModalProps={{ keepMounted: true }}
        sx={{
          display: { xs: 'block', lg: 'none' },
          '& .MuiDrawer-paper': { boxSizing: 'border-box', width: DRAWER_WIDTH },
        }}
      >
        {navContent}
      </Drawer>

      <Drawer
        variant="permanent"
        open
        sx={{
          display: { xs: 'none', lg: 'block' },
          width: DRAWER_WIDTH,
          flexShrink: 0,
          '& .MuiDrawer-paper': { boxSizing: 'border-box', width: DRAWER_WIDTH, borderRight: '1px solid', borderColor: 'divider' },
        }}
      >
        {navContent}
      </Drawer>

      <Box sx={{ flexGrow: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }}>
        <AppBar position="sticky" color="inherit" elevation={0} sx={{ borderBottom: '1px solid', borderColor: 'divider' }}>
          <Toolbar sx={{ gap: 1 }}>
            <IconButton
              edge="start"
              color="inherit"
              onClick={() => setMobileOpen(true)}
              sx={{ display: { lg: 'none' } }}
              aria-label="Open navigation"
            >
              <MenuIcon />
            </IconButton>
            <Typography variant="subtitle1" sx={{ fontWeight: 600 }} color="text.primary">
              {user?.name ?? 'Cooperative System'}
            </Typography>
            <Box sx={{ flexGrow: 1 }} />
            <ThemeToggle />
            <Tooltip title="Account">
              <IconButton onClick={(event) => setUserMenuAnchor(event.currentTarget)} size="small" sx={{ border: '1px solid', borderColor: 'divider' }} aria-label="Account menu">
                <Avatar sx={{ width: 32, height: 32, bgcolor: 'primary.main', fontSize: 14 }}>
                  {user?.name ? user.name.charAt(0).toUpperCase() : '?'}
                </Avatar>
              </IconButton>
            </Tooltip>
            <Menu
              anchorEl={userMenuAnchor}
              open={Boolean(userMenuAnchor)}
              onClose={() => setUserMenuAnchor(null)}
              anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
              transformOrigin={{ vertical: 'top', horizontal: 'right' }}
            >
              <Box sx={{ px: 2, py: 1 }}>
                <Typography variant="subtitle2">{user?.name}</Typography>
                <Typography variant="caption" color="text.secondary">
                  {user?.email}
                </Typography>
              </Box>
              <Divider />
              {isMember ? (
                <MenuItem component={RouterLink} to="/member/profile" onClick={() => setUserMenuAnchor(null)}>
                  Profile
                </MenuItem>
              ) : null}
              <MenuItem onClick={handleLogout}>Logout</MenuItem>
            </Menu>
          </Toolbar>
        </AppBar>

        <Box component="main" sx={{ flexGrow: 1, p: { xs: 2, md: 3 } }}>
          <PageTransition>
            <Outlet />
          </PageTransition>
        </Box>
      </Box>
    </Box>
  )
}