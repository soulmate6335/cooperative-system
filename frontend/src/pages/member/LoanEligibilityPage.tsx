import type { ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import CircularProgress from '@mui/material/CircularProgress'
import Divider from '@mui/material/Divider'
import Grid from '@mui/material/Grid'
import List from '@mui/material/List'
import ListItem from '@mui/material/ListItem'
import ListItemButton from '@mui/material/ListItemButton'
import ListItemIcon from '@mui/material/ListItemIcon'
import ListItemText from '@mui/material/ListItemText'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import CheckCircleIcon from '@mui/icons-material/CheckCircle'
import ErrorOutlineOutlinedIcon from '@mui/icons-material/ErrorOutlineOutlined'
import SavingsIcon from '@mui/icons-material/Savings'
import { Link as RouterLink, useSearchParams } from 'react-router-dom'

import { EmptyState } from '../../components/common/EmptyState'
import { ErrorState } from '../../components/common/ErrorState'
import { CardSkeleton } from '../../components/common/Skeletons'
import { SimplePageContainer } from '../../components/common/PageContainer'
import { fetchEligibility, listLoanProducts } from '../../services/loans'
import type { EligibilityFactors, LoanProduct } from '../../types'
import { formatBasisPoints, formatNaira } from '../../utils/format'
import { getErrorMessage } from '../../utils/errors'

const INTEREST_METHOD_LABELS: Record<LoanProduct['interest_method'], string> = {
  flat: 'Flat rate',
  reducing_balance: 'Reducing balance',
}

export function LoanEligibilityPage(): ReactNode {
  const [params, setParams] = useSearchParams()
  const selectedProductId = params.getAll('product_ids')[0] ?? ''

  const productsQuery = useQuery({
    queryKey: ['member', 'loan-products'],
    queryFn: listLoanProducts,
  })

  const selectedProduct = productsQuery.data?.find((product) => product.id === selectedProductId) ?? null

  const eligibilityQuery = useQuery({
    queryKey: ['eligibility', selectedProductId],
    queryFn: () => fetchEligibility(selectedProductId),
    enabled: Boolean(selectedProductId) && selectedProduct !== null,
  })

  const handleSelect = (productId: string): void => {
    setParams({ product_ids: productId }, { replace: true })
  }

  let content: ReactNode
  if (productsQuery.isPending) {
    content = <CardSkeleton />
  } else if (productsQuery.isError) {
    content = (
      <ErrorState
        message={getErrorMessage(productsQuery.error, 'Failed to load loan products.')}
        onRetry={() => void productsQuery.refetch()}
      />
    )
  } else if (productsQuery.data.length === 0) {
    content = (
      <EmptyState
        icon={SavingsIcon}
        title="No loan products available"
        description="There are no active loan products to review right now. Please check back later."
      />
    )
  } else {
    content = (
      <Grid container spacing={3}>
        <Grid size={{ xs: 12, md: 4 }}>
          <Card variant="outlined">
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Loan products
              </Typography>
              <Typography variant="body2" color="text.secondary" sx={{ mb: 1.5 }}>
                Select a product to view its terms and check your eligibility.
              </Typography>
              <List dense disablePadding>
                {productsQuery.data.map((product) => {
                  const selected = product.id === selectedProductId
                  return (
                    <ListItem key={product.id} disablePadding sx={{ mb: 0.5 }}>
                      <ListItemButton
                        selected={selected}
                        aria-pressed={selected}
                        onClick={() => handleSelect(product.id)}
                        sx={{
                          borderRadius: 2,
                          '&.Mui-selected': {
                            bgcolor: 'primary.main',
                            color: 'white',
                            '&:hover': { bgcolor: 'primary.dark' },
                          },
                        }}
                      >
                        <ListItemText
                          primary={product.name}
                          secondary={product.description}
                          slotProps={{
                            primary: { sx: { fontWeight: 600 } },
                            secondary: { sx: { color: selected ? 'inherit' : 'text.secondary' } },
                          }}
                        />
                      </ListItemButton>
                    </ListItem>
                  )
                })}
              </List>
            </CardContent>
          </Card>
        </Grid>

        <Grid size={{ xs: 12, md: 8 }}>
          {!selectedProductId ? (
            <Alert severity="info" sx={{ '& .MuiAlert-message': { width: '100%' } }}>
              Select a loan product from the list to see its terms and your eligibility factors.
            </Alert>
          ) : !selectedProduct ? (
            <Alert severity="warning">
              The selected loan product is no longer available. Please choose another product from the list.
            </Alert>
          ) : (
            <Stack spacing={3}>
              <Card variant="outlined">
                <CardContent>
                  <Stack spacing={1.5}>
                    <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'flex-start' }}>
                      <Typography variant="h6">{selectedProduct.name}</Typography>
                      <SavingsIcon color="primary" />
                    </Stack>
                    {selectedProduct.description ? (
                      <Typography variant="body2" color="text.secondary">
                        {selectedProduct.description}
                      </Typography>
                    ) : null}
                    <Divider />
                    <Box
                      sx={{
                        display: 'grid',
                        gridTemplateColumns: { xs: '1fr', sm: '1fr 1fr' },
                        gap: 1.5,
                      }}
                    >
                      <TermValue label="Interest rate" value={formatBasisPoints(selectedProduct.interest_rate_basis_points)} />
                      <TermValue label="Interest method" value={INTEREST_METHOD_LABELS[selectedProduct.interest_method]} />
                      <TermValue
                        label="Repayment term"
                        value={selectedProduct.repayment_months === 1 ? '1 month' : `${selectedProduct.repayment_months} months`}
                      />
                      <TermValue label="Required guarantors" value={String(selectedProduct.required_guarantors)} />
                      <TermValue
                        label="Minimum membership"
                        value={
                          selectedProduct.minimum_membership_months === 1
                            ? '1 month'
                            : `${selectedProduct.minimum_membership_months} months`
                        }
                      />
                      <TermValue
                        label="Loan amount"
                        value={
                          selectedProduct.maximum_amount_minor === null
                            ? `From ${formatNaira(selectedProduct.minimum_amount_minor)}`
                            : `${formatNaira(selectedProduct.minimum_amount_minor)} – ${formatNaira(selectedProduct.maximum_amount_minor)}`
                        }
                      />
                    </Box>
                  </Stack>
                </CardContent>
              </Card>

              <Card variant="outlined">
                <CardContent>
                  <Typography variant="h6" gutterBottom>
                    Your eligibility
                  </Typography>
                  <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                    Based on your current membership and this product&apos;s requirements.
                  </Typography>
                  {eligibilityQuery.isPending ? (
                    <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}>
                      <CircularProgress size={28} />
                    </Box>
                  ) : eligibilityQuery.isError ? (
                    <ErrorState
                      message={getErrorMessage(eligibilityQuery.error, 'We could not check your eligibility right now.')}
                      onRetry={() => void eligibilityQuery.refetch()}
                    />
                  ) : (
                    <EligibilityDetails factors={eligibilityQuery.data} />
                  )}
                </CardContent>
              </Card>

              <Button
                variant="contained"
                component={RouterLink}
                to={`/member/loans/applications/new?product_id=${selectedProduct.id}`}
                sx={{ textTransform: 'none', alignSelf: 'flex-start' }}
              >
                Apply for this loan
              </Button>
            </Stack>
          )}
        </Grid>
      </Grid>
    )
  }

  return (
    <SimplePageContainer
      title="Loan eligibility"
      subtitle="Review loan products and check which ones you are eligible for"
      backTo="/member"
    >
      {content}
    </SimplePageContainer>
  )
}

function TermValue({ label, value }: { label: string; value: ReactNode }): ReactNode {
  return (
    <Box>
      <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>
        {label}
      </Typography>
      <Typography variant="body2" sx={{ fontWeight: 600 }}>
        {value}
      </Typography>
    </Box>
  )
}

function EligibilityDetails({ factors }: { factors: EligibilityFactors }): ReactNode {
  return (
    <Stack spacing={2}>
      <Alert severity={factors.eligible ? 'success' : 'warning'}>
        {factors.eligible
          ? 'You are currently eligible to borrow from this product.'
          : 'You are not currently eligible to borrow from this product.'}
      </Alert>

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

      {factors.computed_maximum_amount_minor !== null ? (
        <Alert severity="info" icon={<ErrorOutlineOutlinedIcon fontSize="small" />}>
          <Typography variant="body2">
            Estimated maximum loan amount: {formatNaira(factors.computed_maximum_amount_minor)}
          </Typography>
        </Alert>
      ) : null}

      {factors.reasons.length > 0 ? (
        <Alert severity="info" sx={{ '& .MuiAlert-message': { width: '100%' } }}>
          <Typography variant="body2" sx={{ mb: 1 }}>
            What to fix before applying:
          </Typography>
          <List dense disablePadding>
            {factors.reasons.map((reason) => (
              <ListItem
                key={reason}
                disableGutters
                sx={{ py: 0, display: 'list-item', listStyle: 'inside disc', color: 'text.secondary' }}
              >
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