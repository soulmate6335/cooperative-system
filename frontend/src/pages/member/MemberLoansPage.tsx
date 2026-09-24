import type { ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import AccountBalanceWalletIcon from '@mui/icons-material/AccountBalanceWallet'
import { Link as RouterLink, useNavigate } from 'react-router-dom'

import { EmptyState } from '../../components/common/EmptyState'
import { ErrorState } from '../../components/common/ErrorState'
import { StatusPill } from '../../components/common/StatusPill'
import { TableSkeleton } from '../../components/common/Skeletons'
import { SimplePageContainer } from '../../components/common/PageContainer'
import { listMemberLoans } from '../../services/loans'
import { formatDate, formatNaira } from '../../utils/format'
import { getErrorMessage } from '../../utils/errors'

export function MemberLoansPage(): ReactNode {
  const navigate = useNavigate()

  const loansQuery = useQuery({
    queryKey: ['member', 'loans'],
    queryFn: () => listMemberLoans(),
  })

  let content: ReactNode
  if (loansQuery.isPending) {
    content = <TableSkeleton rows={4} columns={4} />
  } else if (loansQuery.isError) {
    content = (
      <ErrorState message={getErrorMessage(loansQuery.error, 'Failed to load your loans.')} onRetry={() => void loansQuery.refetch()} />
    )
  } else if (loansQuery.data.data.length === 0) {
    content = (
      <EmptyState
        icon={AccountBalanceWalletIcon}
        title="No loans yet"
        description="Approved loans appear here with their repayment schedule once they are disbursed."
        actionLabel="View loan products"
        onAction={() => navigate('/member/loans/products')}
      />
    )
  } else {
    content = (
      <Stack spacing={2}>
        {loansQuery.data.data.map((loan) => {
          const nextDue = loan.obligations?.next_due_installment ?? null
          return (
            <Card key={loan.id} variant="outlined">
              <CardContent>
                <Stack
                  direction={{ xs: 'column', sm: 'row' }}
                  spacing={2}
                  sx={{ alignItems: { xs: 'flex-start', sm: 'center' }, justifyContent: 'space-between' }}
                >
                  <Box>
                    <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                      <Typography variant="h6" sx={{ fontWeight: 700 }}>
                        {loan.product?.name ?? 'Loan'}
                      </Typography>
                      <StatusPill status={loan.status} />
                    </Stack>
                    <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5 }}>
                      {formatNaira(loan.principal_amount_minor)} • {loan.interest_method} •{' '}
                      {loan.repayment_months} months
                    </Typography>
                    {loan.obligations ? (
                      <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>
                        Outstanding {formatNaira(loan.obligations.total_outstanding_minor)}
                        {nextDue
                          ? ` • next due ${formatNaira(nextDue.outstanding_minor)} on ${formatDate(nextDue.due_date)}`
                          : ''}
                      </Typography>
                    ) : (
                      <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>
                        Awaiting disbursement
                      </Typography>
                    )}
                  </Box>
                  <Button
                    component={RouterLink}
                    to={`/member/loans/${loan.id}`}
                    size="small"
                    sx={{ textTransform: 'none' }}
                  >
                    View
                  </Button>
                </Stack>
              </CardContent>
            </Card>
          )
        })}
      </Stack>
    )
  }

  return (
    <SimplePageContainer title="Loans" subtitle="Your loans and repayment schedules">
      {content}
    </SimplePageContainer>
  )
}

export default MemberLoansPage