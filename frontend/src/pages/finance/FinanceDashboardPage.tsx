import type { ReactNode } from 'react'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Grid from '@mui/material/Grid'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import AccountBalanceIcon from '@mui/icons-material/AccountBalance'
import PaymentsIcon from '@mui/icons-material/Payments'
import ReceiptLongIcon from '@mui/icons-material/ReceiptLong'
import { Link as RouterLink } from 'react-router-dom'

import { PageContainer } from '../../components/common/PageContainer'

interface FinanceLinkCardProps {
  to: string
  icon: ReactNode
  title: string
  description: string
}

function FinanceLinkCard({ to, icon, title, description }: FinanceLinkCardProps): ReactNode {
  return (
    <Card component={RouterLink} to={to} variant="outlined" sx={{ textDecoration: 'none', height: '100%', display: 'block' }}>
      <CardContent>
        <Stack spacing={1.5}>
          <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}>
            {icon}
            <Typography variant="h6">{title}</Typography>
          </Stack>
          <Typography variant="body2" color="text.secondary">
            {description}
          </Typography>
        </Stack>
      </CardContent>
    </Card>
  )
}

export function FinanceDashboardPage(): ReactNode {
  return (
    <PageContainer title="Finance Dashboard" subtitle="Record, verify, and manage all payments">
      <Grid container spacing={3}>
        <Grid size={{ xs: 12, sm: 6, lg: 4 }}>
          <FinanceLinkCard
            to="/admin/payments"
            icon={<PaymentsIcon color="primary" fontSize="large" />}
            title="Record Payment"
            description="Record a contribution, savings, or shares payment against a member's account."
          />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, lg: 4 }}>
          <FinanceLinkCard
            to="/admin/receipts"
            icon={<ReceiptLongIcon color="primary" fontSize="large" />}
            title="Verify & Receipts"
            description="Verify recorded payments and issue official receipts for them."
          />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, lg: 4 }}>
          <FinanceLinkCard
            to="/admin/financial/accounts"
            icon={<AccountBalanceIcon color="primary" fontSize="large" />}
            title="Financial Accounts"
            description="Open contribution, savings, and shares accounts for members."
          />
        </Grid>
      </Grid>
    </PageContainer>
  )
}