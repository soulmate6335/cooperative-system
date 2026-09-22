import type { ReactNode } from 'react'
import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Chip from '@mui/material/Chip'
import Container from '@mui/material/Container'
import Grid from '@mui/material/Grid'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import SavingsIcon from '@mui/icons-material/Savings'
import GroupIcon from '@mui/icons-material/Group'
import VerifiedUserIcon from '@mui/icons-material/VerifiedUser'
import { Link as RouterLink } from 'react-router-dom'

import { formatNaira } from '../../utils/format'

interface StatCardProps {
  icon: typeof SavingsIcon
  label: string
  value: string
  hint?: string
}

function StatCard({ icon: Icon, label, value, hint }: StatCardProps): ReactNode {
  return (
    <Card variant="outlined" className="cs-lift" sx={{ height: '100%' }}>
      <CardContent>
        <Stack direction="row" spacing={1.5} sx={{ mb: 1, alignItems: 'center' }}>
          <Box
            sx={{
              width: 40,
              height: 40,
              borderRadius: '50%',
              bgcolor: 'primary.light',
              color: 'white',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
            }}
          >
            <Icon fontSize="small" />
          </Box>
          <Typography variant="body2" color="text.secondary">
            {label}
          </Typography>
        </Stack>
        <Typography variant="h5" sx={{ fontWeight: 700, mb: 0.5 }}>
          {value}
        </Typography>
        {hint ? (
          <Typography variant="caption" color="text.secondary">
            {hint}
          </Typography>
        ) : null}
      </CardContent>
    </Card>
  )
}

export function HomePage(): ReactNode {
  return (
    <Box>
      {/* ------------------------------------------------------------------ */}
      {/* hero */}
      <Box className="cs-hero-in" sx={{ position: 'relative', overflow: 'hidden', bgcolor: 'primary.main', color: 'white', py: { xs: 6, md: 9 } }}>
        <Box
          className="cs-glow"
          sx={{ width: 640, height: 640, right: { xs: -220, md: -140 }, top: -220 }}
        />
        <Box className="cs-orb" sx={{ width: 260, height: 260, left: '6%', bottom: { xs: -140, md: -110 } }} />
        <Container maxWidth="lg" sx={{ position: 'relative' }}>
          <Grid container spacing={4} sx={{ alignItems: 'center' }}>
            <Grid size={{ xs: 12, md: 7 }}>
              <Chip
                label="Member-owned cooperative"
                size="small"
                className="cs-fade-up"
                sx={{ bgcolor: 'rgba(255,255,255,0.15)', color: 'white', mb: 2, fontWeight: 600 }}
              />
              <Typography variant="h3" component="h1" className="cs-fade-up cs-delay-1" sx={{ fontWeight: 800, mb: 2 }}>
                Your cooperative partner for savings, shares &amp; affordable loans
              </Typography>
              <Typography variant="h6" component="p" className="cs-fade-up cs-delay-2" sx={{ opacity: 0.92, mb: 3, fontWeight: 400, maxWidth: 640 }}>
                Join a community of members who save together, invest in shares, and access low-interest credit backed by
                a transparent committee and finance team.
              </Typography>
              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} className="cs-fade-up cs-delay-3">
                <Button component={RouterLink} to="/register" variant="contained" color="secondary" size="large" sx={{ textTransform: 'none' }}>
                  Join the cooperative
                </Button>
                <Button
                  component={RouterLink}
                  to="/login"
                  variant="outlined"
                  size="large"
                  sx={{ textTransform: 'none', color: 'white', borderColor: 'rgba(255,255,255,0.6)', '&:hover': { borderColor: 'white', bgcolor: 'rgba(255,255,255,0.08)' } }}
                >
                  Member sign in
                </Button>
              </Stack>
            </Grid>
          </Grid>
        </Container>
      </Box>

      {/* stats */}
      <Container maxWidth="lg" sx={{ py: 5 }}>
        <Grid container spacing={3} className="cs-stagger">
          <Grid size={{ xs: 12, sm: 6, md: 4 }}>
            <StatCard icon={GroupIcon} label="Members" value="Grow your network" hint="Active member community" />
          </Grid>
          <Grid size={{ xs: 12, sm: 6, md: 4 }}>
            <StatCard icon={SavingsIcon} label="Savings & shares" value={formatNaira(0)} hint="In minor units (kobo)" />
          </Grid>
          <Grid size={{ xs: 12, sm: 6, md: 4 }}>
            <StatCard icon={VerifiedUserIcon} label="Transparent governance" value="Committee reviewed" hint="Every loan is investigated" />
          </Grid>
        </Grid>
      </Container>

      {/* features */}
      <Box sx={{ bgcolor: 'background.default', py: 6 }}>
        <Container maxWidth="lg">
          <Typography variant="h4" align="center" sx={{ fontWeight: 700, mb: 1 }}>
            What we offer
          </Typography>
          <Typography variant="body1" color="text.secondary" align="center" sx={{ mb: 4, maxWidth: 640, mx: 'auto' }}>
            Simple, transparent financial services built around the needs of our members.
          </Typography>
          <Grid container spacing={3} className="cs-stagger">
            {[
              { title: 'Savings & contributions', body: 'Automated contribution accounts with a verified finance team recording every payment transparently.' },
              { title: 'Shares investment', body: 'Build ownership through share accounts and grow your stake in the society.' },
              { title: 'Affordable loans', body: 'Apply for loans backed by guarantors uniquely, with eligibility and investigation before approval.' },
              { title: 'Committee governance', body: 'Every application is reviewed by the committee and investigated before a final decision.' },
            ].map((feature) => (
              <Grid key={feature.title} size={{ xs: 12, md: 6 }}>
                <Card variant="outlined" className="cs-lift" sx={{ height: '100%' }}>
                  <CardContent>
                    <Typography variant="h6" sx={{ mb: 1 }}>
                      {feature.title}
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                      {feature.body}
                    </Typography>
                  </CardContent>
                </Card>
              </Grid>
            ))}
          </Grid>
        </Container>
      </Box>

      {/* CTA */}
      <Container maxWidth="lg" sx={{ py: 6 }}>
        <Alert severity="info" className="cs-fade-up" sx={{ maxWidth: 640, mx: 'auto' }}>
          <Typography variant="body1">
            Have questions? Read more{' '}
            <Box component={RouterLink} to="/about" sx={{ fontWeight: 700 }}>
              about us
            </Box>{' '}
            or{' '}
            <Box component={RouterLink} to="/contact" sx={{ fontWeight: 700 }}>
              contact the team
            </Box>
            .
          </Typography>
        </Alert>
      </Container>
    </Box>
  )
}
