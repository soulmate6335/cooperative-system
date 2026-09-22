import type { ReactNode } from 'react'
import Box from '@mui/material/Box'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Divider from '@mui/material/Divider'
import Grid from '@mui/material/Grid'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import AccountCircleIcon from '@mui/icons-material/AccountCircle'
import BadgeIcon from '@mui/icons-material/Badge'

import { SimplePageContainer } from '../../components/common/PageContainer'
import { StatusPill } from '../../components/common/StatusPill'
import { useAuth } from '../../features/auth/AuthContext'
import { formatDate } from '../../utils/format'

export function MemberProfilePage(): ReactNode {
  const { user, isMember, isCommittee, isFinance, isAdmin } = useAuth()
  const member = user?.member

  const roleLabel = (): string => {
    if (isAdmin) return 'Administrator'
    if (isFinance) return 'Finance Officer'
    if (isCommittee) return 'Committee Member'
    if (isMember) return 'Member'
    return user?.roles?.join(', ') ?? '—'
  }

  return (
    <SimplePageContainer title="My profile" subtitle="Your membership and account details">
      <Grid container spacing={3}>
        <Grid size={{ xs: 12, md: 5 }}>
          <Card variant="outlined">
            <CardContent>
              <Stack spacing={1.5} sx={{ alignItems: 'center', textAlign: 'center', py: 2 }}>
                <AccountCircleIcon sx={{ fontSize: 72, color: 'primary.main' }} />
                <Box>
                  <Typography variant="h6">{user?.name ?? '—'}</Typography>
                  <Typography variant="body2" color="text.secondary">
                    {user?.email}
                  </Typography>
                </Box>
                <StatusPill status={user?.status ?? '—'} />
                <Typography variant="caption" color="text.secondary">
                  {roleLabel()}
                </Typography>
              </Stack>
            </CardContent>
          </Card>
        </Grid>

        <Grid size={{ xs: 12, md: 7 }}>
          <Card variant="outlined">
            <CardContent>
              <Typography variant="h6" sx={{ mb: 1 }}>
                Membership details
              </Typography>
              <Divider sx={{ mb: 2 }} />
              <Stack spacing={2}>
                <Stack direction="row" spacing={2} sx={{ alignItems: 'center' }}>
                  <BadgeIcon color="primary" />
                  <Box sx={{ flexGrow: 1 }}>
                    <Typography variant="body2" color="text.secondary">
                      Member number
                    </Typography>
                    <Typography variant="body1" sx={{ fontWeight: 600 }}>
                      {member?.member_number ?? '—'}
                    </Typography>
                  </Box>
                </Stack>
                <Stack direction="row" spacing={2} sx={{ alignItems: 'center' }}>
                  <Box sx={{ flexGrow: 1 }}>
                    <Typography variant="body2" color="text.secondary">
                      Membership type
                    </Typography>
                    <Typography variant="body1" sx={{ fontWeight: 600 }}>
                      {member?.membership_type ?? '—'}
                    </Typography>
                  </Box>
                </Stack>
                <Stack direction="row" spacing={2} sx={{ alignItems: 'center' }}>
                  <Box sx={{ flexGrow: 1 }}>
                    <Typography variant="body2" color="text.secondary">
                      Member status
                    </Typography>
                    <Typography variant="body1" sx={{ fontWeight: 600 }}>
                      {member?.status ?? '—'}
                    </Typography>
                  </Box>
                </Stack>
                <Stack direction="row" spacing={2} sx={{ alignItems: 'center' }}>
                  <Box sx={{ flexGrow: 1 }}>
                    <Typography variant="body2" color="text.secondary">
                      Joined
                    </Typography>
                    <Typography variant="body1" sx={{ fontWeight: 600 }}>
                      {member?.joined_at ? formatDate(member.joined_at) : '—'}
                    </Typography>
                  </Box>
                </Stack>
              </Stack>

              <Divider sx={{ my: 2 }} />

              <Typography variant="body2" color="text.secondary">
                Profile details (name, email, and contact information) are managed by the cooperative administration.
              </Typography>
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </SimplePageContainer>
  )
}