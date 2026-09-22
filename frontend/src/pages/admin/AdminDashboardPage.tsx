import type { ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Grid from '@mui/material/Grid'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import PeopleIcon from '@mui/icons-material/People'
import RequestQuoteIcon from '@mui/icons-material/RequestQuote'
import Inventory2Icon from '@mui/icons-material/Inventory2'
import HowToRegIcon from '@mui/icons-material/HowToReg'
import ArrowForwardIcon from '@mui/icons-material/ArrowForward'

import { PageContainer } from '../../components/common/PageContainer'
import { listAdminLoanProducts } from '../../services/loans'
import { listMemberApplications } from '../../services/membership'
import { listAdminApplications } from '../../services/loans'

export function AdminDashboardPage(): ReactNode {
  const { data: productResult } = useQuery({
    queryKey: ['admin-loan-products', 'dashboard'],
    queryFn: () => listAdminLoanProducts({ per_page: 5 }),
  })
  const { data: memberAppResult } = useQuery({
    queryKey: ['admin-membership-apps', 'dashboard'],
    queryFn: () => listMemberApplications({ per_page: 5 }),
  })
  const { data: loanAppResult } = useQuery({
    queryKey: ['admin-loan-apps', 'dashboard'],
    queryFn: () => listAdminApplications({ per_page: 5 }),
  })

  const stats = [
    {
      title: 'Total Members',
      value: memberAppResult?.meta.total ?? 0,
      icon: <PeopleIcon color="primary" fontSize="large" />,
      link: '/admin/members',
    },
    {
      title: 'Pending Membership Apps',
      value: memberAppResult?.data.filter(a => a.status === 'pending').length ?? 0,
      icon: <HowToRegIcon color="warning" fontSize="large" />,
      link: '/admin/membership-applications',
    },
    {
      title: 'Active Loan Products',
      value: productResult?.data.filter(p => p.status === 'active').length ?? 0,
      icon: <Inventory2Icon color="success" fontSize="large" />,
      link: '/admin/loan-products',
    },
    {
      title: 'Loan Applications',
      value: loanAppResult?.meta.total ?? 0,
      icon: <RequestQuoteIcon color="info" fontSize="large" />,
      link: '/admin/loans/applications',
    },
  ]

  return (
    <PageContainer title="Admin Dashboard" subtitle="System overview and quick actions">
      <Grid container spacing={3} className="cs-stagger" sx={{ mb: 4 }}>
        {stats.map((stat) => (
          <Grid size={{ xs: 12, sm: 6, md: 3 }} key={stat.title}>
            <Card variant="outlined" className="cs-lift" sx={{ height: '100%' }}>
              <CardContent>
                <Stack direction="row" spacing={2} sx={{ alignItems: 'center' }}>
                  <Box
                    sx={{
                      width: 56,
                      height: 56,
                      borderRadius: 2,
                      bgcolor: 'primary.light',
                      color: 'white',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                    }}
                  >
                    {stat.icon}
                  </Box>
                  <Box sx={{ flexGrow: 1 }}>
                    <Typography variant="body2" color="text.secondary" gutterBottom>
                      {stat.title}
                    </Typography>
                    <Typography variant="h4" component="h2" sx={{ fontWeight: 700 }}>
                      {stat.value}
                    </Typography>
                  </Box>
                </Stack>
              </CardContent>
            </Card>
          </Grid>
        ))}
      </Grid>

      <Grid container spacing={3}>
        <Grid size={{ xs: 12, md: 6 }}>
          <Card variant="outlined">
            <CardContent>
              <Stack direction="row" spacing={2} sx={{ mb: 2, alignItems: 'center', justifyContent: 'space-between' }}>
                <Typography variant="h6">Recent Membership Applications</Typography>
                <Button size="small" component="a" href="/admin/membership-applications" startIcon={<ArrowForwardIcon fontSize="small" />}>
                  View All
                </Button>
              </Stack>
              {memberAppResult?.data.length === 0 ? (
                <Typography variant="body2" color="text.secondary">No membership applications yet.</Typography>
              ) : (
                <Stack spacing={1}>
                  {memberAppResult?.data.slice(0, 5).map((app) => (
                    <Stack key={app.id} direction="row" spacing={2} sx={{ alignItems: 'center', p: 1, borderBottom: 1, borderColor: 'divider' }}>
                      <Box sx={{ flexGrow: 1 }}>
                        <Typography variant="body2" sx={{ fontWeight: 500 }}>{app.full_name}</Typography>
                        <Typography variant="caption" color="text.secondary">{app.email}</Typography>
                      </Box>
                      <Typography variant="body2" color="text.secondary">{app.status}</Typography>
                    </Stack>
                  ))}
                </Stack>
              )}
            </CardContent>
          </Card>
        </Grid>
        <Grid size={{ xs: 12, md: 6 }}>
          <Card variant="outlined">
            <CardContent>
              <Stack direction="row" spacing={2} sx={{ mb: 2, alignItems: 'center', justifyContent: 'space-between' }}>
                <Typography variant="h6">Recent Loan Applications</Typography>
                <Button size="small" component="a" href="/admin/loans/applications" startIcon={<ArrowForwardIcon fontSize="small" />}>
                  View All
                </Button>
              </Stack>
              {loanAppResult?.data.length === 0 ? (
                <Typography variant="body2" color="text.secondary">No loan applications yet.</Typography>
              ) : (
                <Stack spacing={1}>
                  {loanAppResult?.data.slice(0, 5).map((app) => (
                    <Stack key={app.id} direction="row" spacing={2} sx={{ alignItems: 'center', p: 1, borderBottom: 1, borderColor: 'divider' }}>
                      <Box sx={{ flexGrow: 1 }}>
                        <Typography variant="body2" sx={{ fontWeight: 500 }}>{app.application_number}</Typography>
                        <Typography variant="caption" color="text.secondary">{app.member?.member_number ?? '—'}</Typography>
                      </Box>
                      <Typography variant="body2" color="text.secondary">{app.status}</Typography>
                    </Stack>
                  ))}
                </Stack>
              )}
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </PageContainer>
  )
}
