import type { ReactNode } from 'react'
import { useState } from 'react'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import CircularProgress from '@mui/material/CircularProgress'
import Divider from '@mui/material/Divider'
import Grid from '@mui/material/Grid'
import List from '@mui/material/List'
import ListItem from '@mui/material/ListItem'
import ListItemIcon from '@mui/material/ListItemIcon'
import ListItemText from '@mui/material/ListItemText'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import CheckCircleIcon from '@mui/icons-material/CheckCircle'
import EditIcon from '@mui/icons-material/Edit'
import FaceIcon from '@mui/icons-material/Face'
import { useNavigate, useParams } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'

import { SimplePageContainer } from '../../components/common/PageContainer'
import { ConfirmDialog } from '../../components/common/ConfirmDialog'
import { EmptyState } from '../../components/common/EmptyState'
import { ErrorState } from '../../components/common/ErrorState'
import { CardSkeleton } from '../../components/common/Skeletons'
import { StatusPill, StatusDetail } from '../../components/common/StatusPill'
import { getMyLoanApplication, submitLoanApplication, cancelLoanApplication } from '../../services/loans'
import type { LoanGuarantor } from '../../types'
import { formatDate, formatNaira } from '../../utils/format'
import { getErrorMessage } from '../../utils/errors'

interface GuarantorRowProps {
  guarantor: LoanGuarantor | null
}

function GuarantorRow({ guarantor }: GuarantorRowProps): ReactNode {
  const memberNumber = guarantor?.guarantor_member?.member_number ?? null
  return (
    <ListItem divider sx={{ px: 0 }}>
      <ListItemIcon sx={{ minWidth: 36 }}>
        <FaceIcon fontSize="small" color="action" />
      </ListItemIcon>
      <ListItemText
        primary={memberNumber ? `Member ${memberNumber}` : 'Unknown member'}
        secondary={guarantor?.requested_at ? `Requested ${formatDate(guarantor.requested_at)}` : 'Request pending'}
      />
      {guarantor?.status ? <StatusPill status={guarantor.status} /> : null}
    </ListItem>
  )
}

export function LoanApplicationDetailPage(): ReactNode {
  const { id = '' } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [confirmSubmit, setConfirmSubmit] = useState(false)
  const [confirmCancel, setConfirmCancel] = useState(false)

  const applicationQuery = useQuery({
    queryKey: ['member', 'loan-application', id],
    queryFn: () => getMyLoanApplication(id),
  })

  const invalidate = (): void => {
    void queryClient.invalidateQueries({ queryKey: ['member', 'loan-application', id] })
  }

  const submitMutation = useMutation({
    mutationFn: () => submitLoanApplication(id),
    onSuccess: invalidate,
  })

  const cancelMutation = useMutation({
    mutationFn: () => cancelLoanApplication(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['member', 'loan-applications'] })
      navigate('/member/loans/applications', { replace: true })
    },
  })

  if (applicationQuery.isPending) {
    return (
      <SimplePageContainer title="Loan application" subtitle="Loading application details…">
        <CardSkeleton />
      </SimplePageContainer>
    )
  }

  if (applicationQuery.isError) {
    return (
      <SimplePageContainer title="Loan application">
        <ErrorState message={getErrorMessage(applicationQuery.error, 'Failed to load application details.')} onRetry={() => void applicationQuery.refetch()} />
      </SimplePageContainer>
    )
  }

  if (!applicationQuery.data) {
    return (
      <SimplePageContainer title="Loan application" subtitle="Application not found">
        <EmptyState title="Application not found" description="This loan application does not exist or you do not have access to it." />
      </SimplePageContainer>
    )
  }

  const application = applicationQuery.data
  const isDraft = application.status === 'draft'
  const isCancellable = !['cancelled', 'rejected', 'approved'].includes(application.status)
  const guarantors = application.guarantors ?? []

  return (
    <SimplePageContainer
      title={`Application · ${application.application_number}`}
      subtitle="Loan application details"
      backTo="/member/loans/applications"
      actions={
        isDraft || isCancellable ? (
          <>
            {isDraft ? (
              <>
                <Button
                  variant="outlined"
                  onClick={() => navigate(`/member/loans/applications/new?edit=${id}`)}
                  startIcon={<EditIcon />}
                  sx={{ textTransform: 'none' }}
                >
                  Edit draft
                </Button>
                <Button
                  variant="contained"
                  onClick={() => setConfirmSubmit(true)}
                  disabled={submitMutation.isPending}
                  startIcon={submitMutation.isPending ? <CircularProgress size={18} /> : <CheckCircleIcon />}
                  sx={{ textTransform: 'none' }}
                >
                  {isDraft ? 'Submit application' : 'Re-submit'}
                </Button>
              </>
            ) : null}
            {isCancellable ? (
              <Button
                variant="outlined"
                color="error"
                onClick={() => setConfirmCancel(true)}
                disabled={cancelMutation.isPending}
                sx={{ textTransform: 'none' }}
              >
                Cancel application
              </Button>
            ) : null}
          </>
        ) : null
      }
    >
      <Grid container spacing={2}>
        <Grid size={{ xs: 12, md: 7 }}>
          <Card variant="outlined">
            <CardContent>
              <Stack spacing={0.5}>
                <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center' }}>
                  <Typography variant="h6">Loan details</Typography>
                  <StatusPill status={application.status} />
                </Stack>
                <Divider />
                <List disablePadding>
                  <ListItem sx={{ px: 0 }}>
                    <ListItemText primary="Loan product" secondary={application.loan_product?.name ?? '—'} />
                  </ListItem>
                  <ListItem sx={{ px: 0 }}>
                    <ListItemText
                      primary="Amount requested"
                      secondary={formatNaira(application.amount_requested_minor)}
                    />
                  </ListItem>
                  <ListItem sx={{ px: 0 }}>
                    <ListItemText primary="Submitted" secondary={formatDate(application.submitted_at)} />
                  </ListItem>
                  {application.committee_meeting ? (
                    <ListItem sx={{ px: 0 }}>
                      <ListItemText
                        primary="Committee meeting"
                        secondary={
                          application.committee_meeting.meeting_date
                            ? `${formatDate(application.committee_meeting.meeting_date)} · ${
                                application.committee_meeting.meeting_type
                              }`
                            : '—'
                        }
                      />
                    </ListItem>
                  ) : null}
                  <ListItem sx={{ px: 0 }}>
                    <ListItemText primary="Purpose" secondary={application.purpose ?? '—'} />
                  </ListItem>
                  <ListItem sx={{ px: 0 }}>
                    <ListItemText primary="Application number" secondary={application.application_number} />
                  </ListItem>
                  {application.rejection_reason ? (
                    <ListItem sx={{ px: 0 }}>
                      <ListItemText
                        primary="Rejection reason"
                        secondary={<Typography color="error.main">{application.rejection_reason}</Typography>}
                      />
                    </ListItem>
                  ) : null}
                </List>
              </Stack>
            </CardContent>
          </Card>

          <Card variant="outlined" sx={{ mt: 2 }}>
            <CardContent>
              <Typography variant="h6" sx={{ mb: 1 }}>
                Status history
              </Typography>
              <Stack spacing={1}>
                <StatusDetail label="Current status" value={application.status} />
              </Stack>
            </CardContent>
          </Card>
        </Grid>

        <Grid size={{ xs: 12, md: 5 }}>
          <Card variant="outlined">
            <CardContent>
              <Typography variant="h6" sx={{ mb: 0.5 }}>
                Guarantors
              </Typography>
              <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                Guarantors confirm your character and repayment capacity. Only members in good standing can guarantee.
              </Typography>
              {application.loan_product?.required_guarantors != null ? (
                <Typography variant="body2" sx={{ fontWeight: 600, mb: 1 }}>
                  {guarantors.filter((guarantor) => guarantor.status === 'accepted').length} of{' '}
                  {application.loan_product.required_guarantors} required guarantors accepted
                </Typography>
              ) : null}
              {guarantors.length === 0 ? (
                <Typography variant="body2" color="text.secondary">
                  No guarantors have been added to this application yet.
                </Typography>
              ) : (
                <List disablePadding>
                  {guarantors.map((guarantor) => (
                    <GuarantorRow key={guarantor.id} guarantor={guarantor} />
                  ))}
                </List>
              )}
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      <ConfirmDialog
        open={confirmSubmit}
        title="Submit loan application?"
        message="Once submitted, your application will enter the review pipeline and can no longer be edited. Continue?"
        confirmLabel="Submit application"
        loading={submitMutation.isPending}
        onConfirm={() => {
          void submitMutation.mutateAsync()
          setConfirmSubmit(false)
        }}
        onClose={() => setConfirmSubmit(false)}
      />
      <ConfirmDialog
        open={confirmCancel}
        title="Cancel this application?"
        message="Cancelling removes this application from the review pipeline. This cannot be undone."
        confirmLabel="Cancel application"
        danger
        loading={cancelMutation.isPending}
        onConfirm={() => {
          void cancelMutation.mutateAsync()
          setConfirmCancel(false)
        }}
        onClose={() => setConfirmCancel(false)}
      />
    </SimplePageContainer>
  )
}