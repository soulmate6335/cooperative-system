import type { ReactNode } from 'react'
import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import EmailIcon from '@mui/icons-material/Email'
import PhoneIcon from '@mui/icons-material/Phone'
import PlaceIcon from '@mui/icons-material/Place'

import { PageContainer } from '../../components/common/PageContainer'

export function ContactPage(): ReactNode {
  return (
    <PageContainer title="Contact us" subtitle="Get in touch with the cooperative office" maxWidth="md">
      <Stack spacing={2}>
        <Alert severity="info">
          For membership or loan enquiries, use the registration or sign-in portal. For anything else, reach us below.
        </Alert>
        <Card variant="outlined">
          <CardContent>
            <Stack spacing={2}>
              {[
                { icon: EmailIcon, label: 'Email', value: 'info@cooperative.example' },
                { icon: PhoneIcon, label: 'Phone', value: '+234 800 000 0000' },
                { icon: PlaceIcon, label: 'Office', value: 'Cooperative House, Community Plaza' },
              ].map((row) => (
                <Stack key={row.label} direction="row" spacing={1.5} sx={{ alignItems: 'center' }}>
                  <Box sx={{ color: 'primary.main', display: 'flex' }}>
                    <row.icon />
                  </Box>
                  <Box>
                    <Typography variant="body2" color="text.secondary">
                      {row.label}
                    </Typography>
                    <Typography variant="body1">{row.value}</Typography>
                  </Box>
                </Stack>
              ))}
            </Stack>
          </CardContent>
        </Card>
      </Stack>
    </PageContainer>
  )
}
