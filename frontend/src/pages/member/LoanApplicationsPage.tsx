import type { ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Chip from '@mui/material/Chip'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import AddIcon from '@mui/icons-material/Add'
import AssignmentIcon from '@mui/icons-material/Assignment'
import { Link as RouterLink, useNavigate, useSearchParams } from 'react-router-dom'

import { EmptyState } from '../../components/common/EmptyState'
import { ErrorState } from '../../components/common/ErrorState'
import { StatusPill } from '../../components/common/StatusPill'
import { TableSkeleton } from '../../components/common/Skeletons'
import { SimplePageContainer } from '../../components/common/PageContainer'
import { listMyLoanApplications } from '../../services/loans'
import { formatDate, formatNaira } from '../../utils/format'
import { getErrorMessage } from '../../utils/errors'

export function LoanApplicationsPage(): ReactNode {
  const [params, setParams] = useSearchParams()
  const navigate = useNavigate()
  const status = params.get('status') ?? ''

  const applicationsQuery = useQuery({
    queryKey: ['member', 'loan-applications', status],
    queryFn: () => listMyLoanApplications({ status: status || undefined }),
  })

  const statusFilters = [
    { label: 'All', value: '' },
    { label: 'Draft', value: 'draft' },
    { label: 'Submitted', value: 'submitted' },
    { label: 'Approved', value: 'approved' },
    { label: 'Rejected', value: 'rejected' },
  ]

  let content: ReactNode
  if (applicationsQuery.isPending) {
    content = <TableSkeleton rows={6} columns={5} />
  } else if (applicationsQuery.isError) {
    content = <ErrorState message={getErrorMessage(applicationsQuery.error, 'Failed to load loan applications.')} onRetry={() => void applicationsQuery.refetch()} />
  } else if (applicationsQuery.data.data.length === 0) {
    content = (
      <EmptyState
        icon={AssignmentIcon}
        title="No loan applications"
        description="You have not submitted any loan applications yet. Start a new application to get started."
        actionLabel="New loan application"
        onAction={() => navigate('/member/loans/applications/new')}
      />
    )
  } else {
    content = (
      <Stack spacing={2}>
        {applicationsQuery.data.data.map((application) => (
          <Card key={application.id} variant="outlined">
            <CardContent>
              <Stack
                direction={{ xs: 'column', sm: 'row' }}
                spacing={2}
                sx={{ alignItems: { xs: 'flex-start', sm: 'center' }, justifyContent: 'space-between' }}
              >
                <Box>
                  <Typography variant="h6" sx={{ fontWeight: 700 }}>
                    {application.loan_product?.name ?? 'Loan application'}
                  </Typography>
                  <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5 }}>
                    {formatNaira(application.amount_requested_minor)}
                  </Typography>
                  <Typography variant="caption" color="text.secondary">
                    Submitted {formatDate(application.submitted_at)}
                  </Typography>
                </Box>
                <Stack direction="row" spacing={1}>
                  <StatusPill status={application.status} />
                  <Button component={RouterLink} to={`/member/loans/applications/${application.id}`} size="small" sx={{ textTransform: 'none' }}>
                    View
                  </Button>
                </Stack>
              </Stack>
            </CardContent>
          </Card>
        ))}
      </Stack>
    )
  }

  return (
    <SimplePageContainer
      title="Loan applications"
      subtitle="Track your loan applications"
      actions={
        <Button component={RouterLink} to="/member/loans/applications/new" variant="contained" startIcon={<AddIcon />} sx={{ textTransform: 'none' }}>
          New application
        </Button>
      }
    >
      <Stack direction="row" spacing={1} sx={{ mb: 2, flexWrap: 'wrap' }}>
        {statusFilters.map((filter) => (
          <Chip
            key={filter.value}
            label={filter.label}
            clickable
            color={status === filter.value ? 'primary' : 'default'}
            variant={status === filter.value ? 'filled' : 'outlined'}
            onClick={() => setParams(filter.value ? { status: filter.value } : {}, { replace: true })}
          />
        ))}
      </Stack>
      {content}
    </SimplePageContainer>
  )
}

export default LoanApplicationsPage
