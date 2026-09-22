import type { ReactNode } from 'react'
import Box from '@mui/material/Box'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Grid from '@mui/material/Grid'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import GroupsIcon from '@mui/icons-material/Groups'
import ShieldIcon from '@mui/icons-material/Shield'
import HandshakeIcon from '@mui/icons-material/Handshake'

import { PageContainer } from '../../components/common/PageContainer'

export function AboutPage(): ReactNode {
  return (
    <PageContainer title="About the Cooperative" subtitle="Who we are and how we operate" maxWidth="lg">
      <Stack spacing={3}>
        <Card variant="outlined">
          <CardContent>
            <Typography variant="h6" sx={{ mb: 1 }}>
              Our mission
            </Typography>
            <Typography variant="body1" color="text.secondary" sx={{ maxWidth: 720 }}>
              We are a member-owned cooperative that pools contributions and savings to provide affordable credit and
              investment opportunities to our community. Every decision is made by the members, reviewed by a
              committee, and backed by transparent financial records.
            </Typography>
          </CardContent>
        </Card>

        <Grid container spacing={3}>
          {[
            { icon: GroupsIcon, title: 'Community first', body: 'Member savings and contributions fund the loans that lift every member together.' },
            { icon: ShieldIcon, title: 'Investigated decisions', body: 'Eligibility, guarantors)Skip, and investigations protect the fund for everyone.' },
            { icon: HandshakeIcon, title: 'Transparent finance', body: 'A dedicated finance team verifies payments and issues receipts for every naira.' },
          ].map((feature) => (
            <Grid key={feature.title} size={{ xs: 12, md: 4 }}>
              <Card variant="outlined" sx={{ height: '100%' }}>
                <CardContent>
                  <Box sx={{ color: 'primary.main', mb: 1 }}>
                    <feature.icon />
                  </Box>
                  <Typography variant="h6" sx={{ mb: 0.5 }}>
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
      </Stack>
    </PageContainer>
  )
}
