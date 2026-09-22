import type { ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Grid from '@mui/material/Grid'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import AccountBalanceWalletIcon from '@mui/icons-material/AccountBalanceWallet'
import SavingsIcon from '@mui/icons-material/Savings'
import CreditCardIcon from '@mui/icons-material/CreditCard'

import { PageContainer } from '../../components/common/PageContainer'
import { formatNaira } from '../../utils/format'
import { listMemberAccounts } from '../../services/financial'

export function MemberAccountsPage(): ReactNode {
  const { data: accounts, isLoading, error, refetch } = useQuery({
    queryKey: ['member-accounts'],
    queryFn: listMemberAccounts,
  })

  const getAccountIcon = (type: string) => {
    switch (type) {
      case 'savings':
        return <SavingsIcon color="primary" fontSize="large" />
      case 'shares':
        return <CreditCardIcon color="secondary" fontSize="large" />
      default:
        return <AccountBalanceWalletIcon color="primary" fontSize="large" />
    }
  }

  const getAccountTypeLabel = (type: string) => {
    return type.charAt(0).toUpperCase() + type.slice(1)
  }

  if (isLoading) {
    return (
      <PageContainer title="My Accounts" subtitle="View your savings, shares, and contribution accounts">
        <div style={{ display: 'flex', justifyContent: 'center', padding: '3rem' }}>
          <div className="loading-spinner" />
        </div>
      </PageContainer>
    )
  }

  if (error) {
    return (
      <PageContainer title="My Accounts" subtitle="View your savings, shares, and contribution accounts">
        <div style={{ textAlign: 'center', padding: '3rem' }}>
          <Typography color="error">Failed to load accounts. Please try again.</Typography>
          <Button variant="contained" onClick={() => refetch()} sx={{ mt: 2 }}>
            Retry
          </Button>
        </div>
      </PageContainer>
    )
  }

  return (
    <PageContainer title="My Accounts" subtitle="View your savings, shares, and contribution accounts">
      {accounts && accounts.length === 0 ? (
        <Box sx={{ textAlign: 'center', py: 6 }}>
          <AccountBalanceWalletIcon sx={{ fontSize: 64, color: 'text.secondary', mb: 2 }} />
          <Typography variant="h6" color="text.secondary" gutterBottom>
            No accounts found
          </Typography>
          <Typography variant="body2" color="text.secondary" sx={{ maxWidth: 400, mx: 'auto', mb: 3 }}>
            You don&apos;t have any financial accounts yet. Contact the cooperative administration to open an account.
          </Typography>
        </Box>
      ) : (
        <Grid container spacing={3}>
          {accounts?.map((account) => (
            <Grid size={{ xs: 12, sm: 6, md: 4 }} key={account.id}>
              <Card variant="outlined" sx={{ height: '100%' }}>
                <CardContent>
                  <Stack direction="row" spacing={2} sx={{ mb: 2, alignItems: 'center' }}>
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
                      {getAccountIcon(account.account_type)}
                    </Box>
                    <Box sx={{ flexGrow: 1 }}>
                      <Typography variant="h6" component="h3" gutterBottom>
                        {getAccountTypeLabel(account.account_type)} Account
                      </Typography>
                      <Typography variant="body2" color="text.secondary">
                        Account: {account.account_number}
                      </Typography>
                    </Box>
                  </Stack>
                  <Box sx={{ borderTop: 1, borderColor: 'divider', py: 2 }}>
                    <Typography variant="body2" color="text.secondary" sx={{ display: 'block' }} gutterBottom>
                      Current Balance
                    </Typography>
                    <Typography variant="h4" component="h2" sx={{ fontWeight: 700, color: 'primary.main' }}>
                      {formatNaira(account.balance_minor)}
                    </Typography>
                  </Box>
                </CardContent>
              </Card>
            </Grid>
          ))}
        </Grid>
      )}
    </PageContainer>
  )
}