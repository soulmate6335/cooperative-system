import type { ReactNode } from 'react'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Chip from '@mui/material/Chip'
import ListItem from '@mui/material/ListItem'
import ListItemIcon from '@mui/material/ListItemIcon'
import ListItemText from '@mui/material/ListItemText'
import Typography from '@mui/material/Typography'
import CheckCircleIcon from '@mui/icons-material/CheckCircle'
import ErrorRoundedIcon from '@mui/icons-material/ErrorRounded'
import type { LoanProduct } from '../../types'
import { formatBasisPoints, formatNaira } from '../../utils/format'

export function EligibilityFactorRow({
  label,
  value,
  passes,
}: {
  label: string
  value: string
  passes: boolean
}): ReactNode {
  return (
    <ListItem disableGutters>
      <ListItemIcon sx={{ minWidth: 36 }}>
        {passes ? <CheckCircleIcon color="success" fontSize="small" /> : <ErrorRoundedIcon color="error" fontSize="small" />}
      </ListItemIcon>
      <ListItemText primary={label} secondary={value} />
    </ListItem>
  )
}

interface ProductCardProps {
  product: LoanProduct
}

export function ProductCard({ product }: ProductCardProps): ReactNode {
  const minMonths = product.minimum_membership_months
  const isEligible = minMonths <= 6
  return (
    <Card variant="outlined" sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
      <CardContent sx={{ flexGrow: 1 }}>
        <Typography variant="h6" component="h3" gutterBottom>
          {product.name}
        </Typography>
        {product.description ? (
          <Typography variant="body2" color="text.secondary" sx={{ mb: 1.5 }}>
            {product.description}
          </Typography>
        ) : null}
        <Chip
          size="small"
          label={isEligible ? 'Eligible for you' : 'Minimum membership not met'}
          color={isEligible ? 'success' : 'default'}
          sx={{ mb: 1.5 }}
        />
        <Typography variant="h6" sx={{ fontWeight: 700, color: 'primary.main', mb: 0.5 }}>
          {formatNaira(product.minimum_amount_minor)}
        </Typography>
        {product.maximum_amount_minor !== null ? (
          <Typography variant="body2" color="text.secondary">
            up to {formatNaira(product.maximum_amount_minor)}
          </Typography>
        ) : (
          <Typography variant="body2" color="text.secondary">no published maximum</Typography>
        )}
        <Typography variant="body2" color="text.secondary" sx={{ mt: 1 }}>
          {formatBasisPoints(product.interest_rate_basis_points)} {product.interest_method.replace('_', ' ')} interest
        </Typography>
        <Typography variant="body2" color="text.secondary">
          repaid within {product.repayment_months} {product.repayment_months === 1 ? 'month' : 'months'}
        </Typography>
        <Typography variant="body2" color="text.secondary">
          {product.required_guarantors} {product.required_guarantors === 1 ? 'guarantor' : 'guarantors'} required
        </Typography>
      </CardContent>
    </Card>
  )
}