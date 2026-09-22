import type { ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import List from '@mui/material/List'
import ListItem from '@mui/material/ListItem'
import ListItemText from '@mui/material/ListItemText'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import AccountBalanceWalletIcon from '@mui/icons-material/AccountBalanceWallet'
import AddIcon from '@mui/icons-material/Add'
import GroupsIcon from '@mui/icons-material/Groups'
import HistoryIcon from '@mui/icons-material/History'
import PaymentsIcon from '@mui/icons-material/Payments'
import ReceiptIcon from '@mui/icons-material/Receipt'
import { Link as RouterLink } from 'react-router-dom'

import { EmptyState } from '../../components/common/EmptyState'
import { ErrorState } from '../../components/common/ErrorState'
import { TableSkeleton } from '../../components/common/Skeletons'
import { StatusPill } from '../../components/common/StatusPill'
import { SimplePageContainer } from '../../components/common/PageContainer'
import { useAuth } from '../../features/auth/AuthContext'
import { listLoanProducts } from '../../services/loans'
import { listMemberAccounts } from '../../services/financial'
import { formatDate, formatNaira, titleCase } from '../../utils/format'
import { getErrorMessage } from '../../utils/errors'

export function MemberDashboardPage(): ReactNode {
  const { user } = useAuth()
  const member = user?.member

  const productsQuery = useQuery({
    queryKey: ['member', 'loan-products'],
    queryFn: listLoanProducts,
  })

  const accountsQuery = useQuery({
    queryKey: ['member', 'accounts'],
    queryFn: listMemberAccounts,
  })

  if (productsQuery.isPending || accountsQuery.isPending) {
    return (
      <SimplePageContainer title="My dashboard" subtitle="Overview of your cooperative membership">
        <TableSkeleton rows={6} columns={4} />
      </SimplePageContainer>
    )
  }

  if (productsQuery.isError || accountsQuery.isError) {
    return (
      <SimplePageContainer title="My dashboard" subtitle="Overview of your cooperative membership">
        <ErrorState message={getErrorMessage(productsQuery.error ?? accountsQuery.error)} onRetry={() => void Promise.all([productsQuery.refetch(), accountsQuery.refetch()])} />
      </SimplePageContainer>
    )
  }

  const totalBalanceMinor = accountsQuery.data.reduce((sum, account) => sum + (account.balance_minor ?? 0), 0)

  return (
    <SimplePageContainer
      title="My dashboard"
      subtitle="Overview of your cooperative membership"
      actions={
        <Button component={RouterLink} to="/member/loans/applications/new" variant="contained" startIcon={<AddIcon />} sx={{ textTransform: 'none' }}>
          New loan application
        </Button>
      }
    >
      <Stack spacing={2.5}>
        <Box className="cs-stagger" sx={{ display: 'flex', gap: 2, flexWrap: 'wrap' }}>
          <Card variant="outlined" className="cs-lift" sx={{ flexGrow: 1, minWidth: 220 }}>
            <CardContent>
              <Stack direction="row" spacing={1.5} sx={{ mb: 1, alignItems: 'center' }}>
                <AccountBalanceWalletIcon color="primary" />
                <Typography variant="body2" color="text.secondary">
                  Total balance
                </Typography>
              </Stack>
              <Typography variant="h6" sx={{ fontWeight: 700 }}>
                {formatNaira(totalBalanceMinor)}
              </Typography>
              <Typography variant="caption" color="text.secondary">
                Across {accountsQuery.data.length} account{accountsQuery.data.length === 1 ? '' : 's'}
              </Typography>
            </CardContent>
          </Card>
          <Card variant="outlined" className="cs-lift" sx={{ flexGrow: 1, minWidth: 220 }}>
            <CardContent>
              <Stack direction="row" spacing={1.5} sx={{ mb: 1, alignItems: 'center' }}>
                <GroupsIcon color="primary" />
                <Typography variant="body2" color="text.secondary">
                  Membership status
                </Typography>
              </Stack>
              <StatusPill status={member?.status ?? 'pending'} />
              {member?.joined_at ? (
                <Typography variant="caption" color="text.secondary" sx={{ display: 'block', mt: 0.5 }}>
                  Joined {formatDate(member.joined_at)}
                </Typography>
              ) : null}
            </CardContent>
          </Card>
          <Card variant="outlined" className="cs-lift" sx={{ flexGrow: 1, minWidth: 220 }}>
            <CardContent>
              <Stack direction="row" spacing={1.5} sx={{ mb: 1, alignItems: 'center' }}>
                <ReceiptIcon color="primary" />
                <Typography variant="body2" color="text.secondary">
                  Loan products
                </Typography>
              </Stack>
              <Typography variant="h6" sx={{ fontWeight: 700 }}>
                {productsQuery.data.length}
              </Typography>
              <Typography variant="caption" color="text.secondary">
                Available to apply for
              </Typography>
            </CardContent>
          </Card>
        </Box>

        <Card variant="outlined">
          <CardContent>
            <Stack direction="row" sx={{ alignItems: 'center', justifyContent: 'space-between', mb: 1.5 }}>
              <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                <HistoryIcon color="primary" />
                <Typography variant="h6">My accounts</Typography>
              </Stack>
              <Button component={RouterLink} to="/member/accounts" size="small" sx={{ textTransform: 'none' }}>
                View all
              </Button>
            </Stack>
            {accountsQuery.data.length === 0 ? (
              <EmptyState title="No accounts yet" description="Accounts are created by the admin once your membership is approved." />
            ) : (
              <List disablePadding>
                {accountsQuery.data.map((account) => (
                  <ListItem key={account.id} divider sx={{ px: 0 }}>
                    <ListItemText
                      primary={titleCase(account.account_type)}
                      secondary={`Account ${account.account_number}`}
                    />
                    <Typography variant="body2" sx={{ fontWeight: 600 }}>
                      {formatNaira(account.balance_minor)}
                    </Typography>
                  </ListItem>
                ))}
              </List>
            )}
          </CardContent>
        </Card>

        <Card variant="outlined">
          <CardContent>
            <Stack direction="row" spacing={1} sx={{ alignItems: 'center', mb: 1.5 }}>
              <PaymentsIcon color="primary" />
              <Typography variant="h6">Quick actions</Typography>
            </Stack>
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} sx={{ flexWrap: 'wrap' }}>
              <Button component={RouterLink} to="/member/loans/applications" variant="outlined" sx={{ textTransform: 'none' }}>
                My loan applications
              </Button>
              <Button component={RouterLink} to="/member/guarantor-requests" variant="outlined" sx={{ textTransform: 'none' }}>
                Guarantor requests
              </Button>
              <Button component={RouterLink} to="/member/eligibility" variant="outlined" sx={{ textTransform: 'none' }}>
                Loan eligibility
              </Button>
              <Button component={RouterLink} to="/member/accounts" variant="outlined" sx={{ textTransform: 'none' }}>
                Accounts &amp; transactions
              </Button>
            </Stack>
          </CardContent>
        </Card>
      </Stack>
    </SimplePageContainer>
  )
}
