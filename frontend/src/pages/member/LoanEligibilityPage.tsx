import type { ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import Alert from '@mui/material/Alert'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import CircularProgress from '@mui/material/CircularProgress'
import List from '@mui/material/List'
import ListItem from '@mui/material/ListItem'
import ListItemIcon from '@mui/material/ListItemIcon'
import ListItemText from '@mui/material/ListItemText'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import CheckCircleIcon from '@mui/icons-material/CheckCircle'
import ErrorOutlineOutlinedIcon from '@mui/icons-material/ErrorOutlineOutlined'
import { useSearchParams } from 'react-router-dom'

import { fetchEligibility } from '../../services/loans'
import { formatNaira } from '../../utils/format'
import { SimplePageContainer } from '../../components/common/PageContainer'

export function LoanEligibilityPage(): ReactNode {
  const [params] = useSearchParams()
  const productIds = params.getAll('product_ids') ?? []
  const productId = productIds[0] ?? ''

  const eligibilityQuery = useQuery({
    queryKey: ['eligibility', productId],
    queryFn: () => fetchEligibility(productId),
    enabled: Boolean(productId),
  })

  let content: ReactNode
  if (!productId) {
    content = (
      <Alert severity="info">
        Select a loan product from the eligibility panel to check your eligibility factors.
      </Alert>
    )
  } else if (eligibilityQuery.isPending) {
    content = <CircularProgress size={24} />
  } else if (eligibilityQuery.isError) {
    content = <Alert severity="error">{(eligibilityQuery.error as Error).message}</Alert>
  } else {
    const factors = eligibilityQuery.data
    content = (
      <Stack spacing={4}>
        <Alert severity={factors.eligible ? 'success' : 'warning'}>
          {factors.eligible
            ? 'You are currently eligible to borrow from the selected product.'
            : 'You are not currently eligible to borrow from the selected product.'}
        </Alert>

        <Card variant="outlined">
          <CardContent>
            <Typography variant="h6" sx={{ mb: 2 }}>
              Eligibility factors
            </Typography>
            <List dense disablePadding>
              <EligibilityFactorLine
                label="Membership months required"
                value={`${factors.membership_months} of ${factors.minimum_membership_months} months`}
                ok={factors.membership_months >= factors.minimum_membership_months}
              />
              <EligibilityFactorLine
                label="Member status must be active"
                value={factors.member_active ? 'Active' : 'Inactive'}
                ok={factors.member_active}
              />
              <EligibilityFactorLine
                label="Savings balance"
                value={formatNaira(factors.savings_balance_minor)}
                ok={factors.savings_balance_minor > 0}
              />
              <EligibilityFactorLine
                label="Shares balance"
                value={formatNaira(factors.shares_balance_minor)}
                ok={factors.shares_balance_minor > 0}
              />
            </List>
          </CardContent>
        </Card>

        {factors.reasons.length > 0 ? (
          <Alert severity="info" sx={{ '& .MuiAlert-message': { width: '100%' } }}>
            <Typography variant="body2" sx={{ mb: 1 }}>
              Adjustments needed:
            </Typography>
            <List dense disablePadding>
              {factors.reasons.map((reason) => (
                <ListItem key={reason} disableGutters sx={{ py: 0, display: 'list-item', listStyle: 'inside disc', color: 'text.secondary' }}>
                  <ListItemText primary={reason} slotProps={{ primary: { variant: 'body2' } }} sx={{ display: 'inline' }} />
                </ListItem>
              ))}
            </List>
          </Alert>
        ) : (
          <Alert severity="success" icon={<CheckCircleIcon fontSize="small" />}>
            No adjustments needed.
          </Alert>
        )}
      </Stack>
    )
  }

  return (
    <SimplePageContainer title="Loan eligibility" backTo="/member">
      {content}
    </SimplePageContainer>
  )
}

function EligibilityFactorLine({ label, value, ok }: { label: string; value: ReactNode; ok: boolean }): ReactNode {
  return (
    <ListItem disableGutters sx={{ py: 0.5 }}>
      <ListItemIcon sx={{ minWidth: 40 }}>
        {ok ? <CheckCircleIcon color="success" /> : <ErrorOutlineOutlinedIcon color="error" />}
      </ListItemIcon>
      <ListItemText primary={label} secondary={value} />
    </ListItem>
  )
}