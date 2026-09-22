import type { ReactNode } from 'react'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Box from '@mui/material/Box'
import Container from '@mui/material/Container'
import Grid from '@mui/material/Grid'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import AccountBalanceIcon from '@mui/icons-material/AccountBalance'
import GroupsIcon from '@mui/icons-material/Groups'
import PaymentsIcon from '@mui/icons-material/Payments'
import SavingsIcon from '@mui/icons-material/Savings'
import VerifiedUserIcon from '@mui/icons-material/VerifiedUser'
import { Link as RouterLink } from 'react-router-dom'

const FEATURES: Array<{ icon: typeof SavingsIcon; title: string; description: string }> = [
  {
    icon: SavingsIcon,
    title: 'Savings & Contributions',
    description: 'Build your savings and contributions from as little as ₦100, with balances derived from a transparent transaction history.',
  },
  {
    icon: AccountBalanceIcon,
    title: 'Member Loans',
    description: 'Access affordable loans after six months of active membership, backed by real eligibility checks and guaranteed by fellow members.',
  },
  {
    icon: GroupsIcon,
    title: 'Committee Governance',
    description: 'Every application is investigated by the committee and decided by the administration — no shortcuts, no bypass.',
  },
  {
    icon: PaymentsIcon,
    title: 'Simple Payments',
    description: 'Record contributions, savings and share purchases against payment methods, with verification and receipts.',
  },
  {
    icon: VerifiedUserIcon,
    title: 'Member-Owned',
    description: 'Decisions belong to the membership through the general meeting and elected executives.',
  },
]

export function HomePage(): ReactNode {
  return (
    <Box>
      {/* ------------------------------------------------------------------ hero */}
      <Box
        sx={{
          background: 'linear-gradient(135deg, #0c3d24 0%, #166534 45%, #1f7a44 100%)',
          color: '#fff',
          py: { xs: 6, md: 10 },
        }}
      >
        <Container maxWidth="lg">
          <Stack spacing={3} sx={{ alignItems: { xs: 'center', md: 'flex-start' }, textAlign: { xs: 'center', md: 'left' }, maxWidth: 720 }}>
            <Typography
              variant="h3"
              component="h1"
              sx={{ fontWeight: 800, lineHeight: 1.15, fontSize: { xs: '2rem', md: '2.75rem' } }}
            >
              A cooperative built on <Box component="span" sx={{ color: 'secondary.light' }}>mutual trust</Box>
            </Typography>
            <Typography variant="h6" component="p" sx={{ fontWeight: 400, opacity: 0.95, lineHeight: 1.6 }}>
              We pool our contributions, grow our savings, and lend to one another on fair terms — transparently,
              accountably, and always by the membership.
            </Typography>
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
              <Button component={RouterLink} to="/register" variant="contained" color="secondary" size="large">
                Join the cooperative
              </Button>
              <Button component={RouterLink} to="/login" variant="outlined" size="large" sx={{ color: '#fff', borderColor: 'rgba(255,255,255,0.6)', '&:hover': { borderColor: '#fff', bgcolor: 'rgba(255,255,255,0.08)' } }}>
                Member login
              </Button>
            </Stack>
          </Stack>
        </Container>
      </Box>

      {/* ------------------------------------------------------------- intro */}
      <Container maxWidth="lg" sx={{ py: { xs: 6, md: 8 } }}>
        <Grid container spacing={4} sx={{ alignItems: 'center' }}>
          <Grid size={{ xs: 12, md: 6 }}>
            <Typography variant="h4" component="h2" sx={{ fontWeight: 700, mb: 2 }}>
              Who we are
            </Typography>
            <Typography variant="body1" color="text.secondary" sx={{ mb: 1.5 }}>
              We are a member-owned cooperative society. Members save together, contribute to a common fund,
              and draw on that fund for affordable loans — with every step governed by our own committee and
              decided by our administration.
            </Typography>
            <Typography variant="body1" color="text.secondary">
              Whether you are building a steady savings culture, financing a project, or joining as a guarantor
              for a fellow member, the cooperative is here to help the whole membership move forward together.
            </Typography>
            <Stack direction="row" spacing={2} sx={{ mt: 3, flexWrap: 'wrap' }}>
              <Button component={RouterLink} to="/register" variant="contained">
                Register as a member
              </Button>
              <Button component={RouterLink} to="/about" variant="outlined">
                Learn about us
              </Button>
            </Stack>
          </Grid>
          <Grid size={{ xs: 12, md: 6 }}>
            <Box
              sx={{
                display: 'grid',
                gridTemplateColumns: { xs: '1fr', sm: '1fr 1fr' },
                gap: 2,
              }}
            >
              {[
                { value: '6 months', label: 'minimum membership before loans' },
                { value: '₦100', label: 'minimum contribution or saving' },
                { value: '11 months', label: 'standard repayment period' },
                { value: '2', label: 'guarantors required by default' },
              ].map((stat) => (
                <Card key={stat.label} sx={{ borderRadius: 3 }}>
                  <CardContent>
                    <Typography variant="h5" sx={{ fontWeight: 800, color: 'primary.main' }}>
                      {stat.value}
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                      {stat.label}
                    </Typography>
                  </CardContent>
                </Card>
              ))}
            </Box>
          </Grid>
        </Grid>
      </Container>

      {/* ----------------------------------------------------------- benefits */}
      <Box sx={{ bgcolor: 'background.paper', borderTop: '1px solid', borderColor: 'divider', py: { xs: 6, md: 8 } }}>
        <Container maxWidth="lg">
          <Typography variant="h4" component="h2" sx={{ fontWeight: 700, textAlign: 'center', mb: 1 }}>
            What the cooperative offers
          </Typography>
          <Typography variant="body1" color="text.secondary" sx={{ textAlign: 'center', mb: 5, maxWidth: 640, mx: 'auto' }}>
            Everything here is backed by the real cooperative workflow — savings, contributions, shares,
            and member-driven loan applications.
          </Typography>
          <Grid container spacing={3}>
            {FEATURES.map((feature) => (
              <Grid key={feature.title} size={{ xs: 12, sm: 6, md: 4 }}>
                <Card sx={{ height: '100%', borderRadius: 3, transition: 'box-shadow 0.2s', '&:hover': { boxShadow: 6 } }}>
                  <CardContent>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, mb: 1.5 }}>
                      <Box sx={{ width: 44, height: 44, borderRadius: 2, bgcolor: 'primary.main', color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <feature.icon />
                      </Box>
                      <Typography variant="h6" sx={{ fontWeight: 700 }}>
                        {feature.title}
                      </Typography>
                    </Box>
                    <Typography variant="body2" color="text.secondary">
                      {feature.description}
                    </Typography>
                  </CardContent>
                </Card>
              </Grid>
            ))}
          </Grid>
        </Container>
      </Box>

      {/* -------------------------------------------------------------- CTA */}
      <Container maxWidth="md" sx={{ py: { xs: 6, md: 8 }, textAlign: 'center' }}>
        <Typography variant="h4" component="h2" sx={{ fontWeight: 700, mb: 1.5 }}>
          Ready to save together?
        </Typography>
        <Typography variant="body1" color="text.secondary" sx={{ mb: 3 }}>
          Create your membership application — once it is reviewed and approved by the administration,
          you can start saving, contributing owned shares, and applying for loans.
        </Typography>
        <Button component={RouterLink} to="/register" variant="contained" color="secondary" size="large">
          Get started
        </Button>
      </Container>
    </Box>
  )
}