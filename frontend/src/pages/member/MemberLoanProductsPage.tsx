import { useQuery } from '@tanstack/react-query'
import Alert from '@mui/material/Alert'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import CircularProgress from '@mui/material/CircularProgress'
import Divider from '@mui/material/Divider'
import Grid from '@mui/material/Grid'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import SavingsIcon from '@mui/icons-material/Savings'
import { Link as RouterLink } from 'react-router-dom'
import type { ReactNode } from 'react'

import { SimplePageContainer } from '../../components/common/PageContainer'
import { listLoanProducts } from '../../services/loans'
import { formatBasisPoints, formatNaira } from '../../utils/format'
import { getErrorMessage } from '../../utils/errors'

interface ProductCardProps {
  id: string
  name: string
  description?: string | null
  minimumMonths: number
  interestBasisPoints: number
  repaymentMonths: number
  minimumMinor: number
  maximumMinor: number | null
  interestMethod?: string
}

function LoanProductCard({
  id,
  name,
  description,
  minimumMonths,
  interestBasisPoints,
  repaymentMonths,
  minimumMinor,
  maximumMinor,
  interestMethod,
}: ProductCardProps): ReactNode {
  return (
    <Card variant="outlined" sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
      <CardContent>
        <Stack spacing={1.25}>
          <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'flex-start' }}>
            <Typography variant="h6" sx={{ fontWeight: 700 }}>
              {name}
            </Typography>
            <SavingsIcon color="primary" />
          </Stack>
          {description ? <Typography variant="body2" color="text.secondary">{description}</Typography> : null}
          <Divider />
          <Stack spacing={0.75}>
            <Typography variant="body2">
              <strong>Interest:</strong> {formatBasisPoints(interestBasisPoints)}
            </Typography>
            <Typography variant="body2">
              <strong>Repayment:</strong> {repaymentMonths} months
            </Typography>
            <Typography variant="body2">
              <strong>Amount:</strong> {formatNaira(minimumMinor)} – {maximumMinor ? formatNaira(maximumMinor) : 'Unlimited'}
            </Typography>
            <Typography variant="body2">
              <strong>Minimum membership:</strong> {minimumMonths} month{minimumMonths === 1 ? '' : 's'}
            </Typography>
            <Typography variant="body2">
              <strong>Method:</strong> {interestMethod ?? 'flat'}
            </Typography>
          </Stack>
          <Button
            variant="contained"
            component={RouterLink}
            to={`/member/loans/eligibility?product_ids=${id}`}
            sx={{ textTransform: 'none', mt: 'auto' }}
          >
            Check eligibility
          </Button>
        </Stack>
      </CardContent>
    </Card>
  )
}

export function MemberLoanProductsPage(): ReactNode {
  const productsQuery = useQuery({
    queryKey: ['member', 'loan-products'],
    queryFn: listLoanProducts,
  })

  let content: ReactNode
  if (productsQuery.isPending) {
    content = <CircularProgress />
  } else if (productsQuery.isError) {
    content = <Alert severity="error">{getErrorMessage(productsQuery.error, 'Failed to load loan products.')}</Alert>
  } else {
    content = (
      <Grid container spacing={3}>
        {productsQuery.data.map((product) => (
          <Grid key={product.id} size={{ xs: 12, sm: 6, md: 4 }}>
            <LoanProductCard
              id={product.id}
              name={product.name}
              description={product.description}
              minimumMonths={product.minimum_membership_months}
              interestBasisPoints={product.interest_rate_basis_points}
              repaymentMonths={product.repayment_months}
              minimumMinor={product.minimum_amount_minor}
              maximumMinor={product.maximum_amount_minor}
              interestMethod={product.interest_method}
            />
          </Grid>
        ))}
      </Grid>
    )
  }

  return (
    <SimplePageContainer
      title="Loan products"
      subtitle="Available loan products for members"
      actions={
        <Button component={RouterLink} to="/member/loans/eligibility" variant="outlined" sx={{ textTransform: 'none' }}>
          Loan eligibility
        </Button>
      }
    >
      {content}
    </SimplePageContainer>
  )
}
