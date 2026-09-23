import type { ReactNode } from 'react'
import { useState } from 'react'
import Alert from '@mui/material/Alert'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import CircularProgress from '@mui/material/CircularProgress'
import Dialog from '@mui/material/Dialog'
import DialogActions from '@mui/material/DialogActions'
import DialogContent from '@mui/material/DialogContent'
import DialogContentText from '@mui/material/DialogContentText'
import DialogTitle from '@mui/material/DialogTitle'
import Divider from '@mui/material/Divider'
import Grid from '@mui/material/Grid'
import List from '@mui/material/List'
import ListItem from '@mui/material/ListItem'
import ListItemIcon from '@mui/material/ListItemIcon'
import ListItemText from '@mui/material/ListItemText'
import Stack from '@mui/material/Stack'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import CheckCircleIcon from '@mui/icons-material/CheckCircle'
import EditIcon from '@mui/icons-material/Edit'
import FaceIcon from '@mui/icons-material/Face'
import PersonAddIcon from '@mui/icons-material/PersonAdd'
import { useNavigate, useParams } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'

import { SimplePageContainer } from '../../components/common/PageContainer'
import { ConfirmDialog } from '../../components/common/ConfirmDialog'
import { EmptyState } from '../../components/common/EmptyState'
import { ErrorState } from '../../components/common/ErrorState'
import { CardSkeleton, TableSkeleton } from '../../components/common/Skeletons'
import { StatusPill, StatusDetail } from '../../components/common/StatusPill'
import {
  cancelGuarantorRequest,
  cancelLoanApplication,
  getMyLoanApplication,
  listGuarantorCandidates,
  requestGuarantor,
  submitLoanApplication,
} from '../../services/loans'
import type { LoanGuarantor } from '../../types'
import { formatDate, formatNaira } from '../../utils/format'
import { getErrorMessage } from '../../utils/errors'

interface GuarantorRowProps {
  guarantor: LoanGuarantor
  canCancel?: boolean
  onCancel?: () => void
}

function GuarantorRow({ guarantor, canCancel, onCancel }: GuarantorRowProps): ReactNode {
  const member = guarantor.guarantor_member
  const secondary = [
    member ? `Member ${member.member_number}` : null,
    guarantor.requested_at ? `Requested ${formatDate(guarantor.requested_at)}` : null,
    guarantor.responded_at ? `Responded ${formatDate(guarantor.responded_at)}` : null,
  ]
    .filter(Boolean)
    .join(' · ')
  return (
    <ListItem divider sx={{ px: 0 }}>
      <ListItemIcon sx={{ minWidth: 36 }}>
        <FaceIcon fontSize="small" color="action" />
      </ListItemIcon>
      <ListItemText
        primary={member?.name ?? (member ? `Member ${member.member_number}` : 'Unknown member')}
        secondary={secondary || 'Request pending'}
      />
      {canCancel && onCancel ? (
        <Button size="small" color="inherit" onClick={onCancel} disabled={!!guarantor.responded_at}>
          Cancel
        </Button>
      ) : null}
      {guarantor.status ? <StatusPill status={guarantor.status} /> : null}
    </ListItem>
  )
}

export function LoanApplicationDetailPage(): ReactNode {
  const { id = '' } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [confirmSubmit, setConfirmSubmit] = useState(false)
  const [confirmCancel, setConfirmCancel] = useState(false)
  const [candidateDialogOpen, setCandidateDialogOpen] = useState(false)
  const [candidateSearch, setCandidateSearch] = useState('')
  const [candidateError, setCandidateError] = useState<string | null>(null)

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

  const requestGuarantorMutation = useMutation({
    mutationFn: (memberId: string) => requestGuarantor(id, memberId),
    onSuccess: () => {
      setCandidateDialogOpen(false)
      setCandidateSearch('')
      setCandidateError(null)
      void queryClient.invalidateQueries({ queryKey: ['member', 'loan-application', id] })
    },
    onError: (err) => setCandidateError(getErrorMessage(err, 'Failed to send the guarantor request.')),
  })

  const cancelGuarantorMutation = useMutation({
    mutationFn: (guarantorId: string) => cancelGuarantorRequest(id, guarantorId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['member', 'loan-application', id] })
    },
    onError: (err) => setCandidateError(getErrorMessage(err, 'Failed to cancel the guarantor request.')),
  })

  const candidatesQuery = useQuery({
    queryKey: ['member', 'guarantor-candidates', id, candidateSearch],
    queryFn: () => listGuarantorCandidates(id, { search: candidateSearch || undefined, per_page: 20 }),
    enabled: candidateDialogOpen,
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
  const requiredGuarantors = application.loan_product?.required_guarantors ?? null
  const acceptedCount = guarantors.filter((guarantor) => guarantor.status === 'accepted').length
  const pendingCount = guarantors.filter((guarantor) => guarantor.status === 'pending').length
  const guarantorStageOpen = ['submitted', 'awaiting_guarantors'].includes(application.status)
  const canAddGuarantors =
    guarantorStageOpen && (requiredGuarantors === null || acceptedCount < requiredGuarantors)

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
              <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center', mb: 0.5 }}>
                <Typography variant="h6">Guarantors</Typography>
                {canAddGuarantors ? (
                  <Button
                    size="small"
                    onClick={() => setCandidateDialogOpen(true)}
                    startIcon={<PersonAddIcon />}
                    sx={{ textTransform: 'none' }}
                  >
                    Add guarantor
                  </Button>
                ) : null}
              </Stack>
              <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                Guarantors confirm your character and repayment capacity. Only members in good standing can guarantee.
              </Typography>
              {requiredGuarantors != null ? (
                <Typography variant="body2" sx={{ fontWeight: 600, mb: 1 }}>
                  Required {requiredGuarantors} · Accepted {acceptedCount} · Pending {pendingCount}
                </Typography>
              ) : null}
              {guarantors.length === 0 ? (
                <Typography variant="body2" color="text.secondary">
                  No guarantors have been added to this application yet.
                </Typography>
              ) : (
                <List disablePadding>
                  {guarantors.map((guarantor) => (
                    <GuarantorRow
                      key={guarantor.id}
                      guarantor={guarantor}
                      canCancel={guarantorStageOpen && guarantor.status === 'pending'}
                      onCancel={() => cancelGuarantorMutation.mutate(guarantor.id)}
                    />
                  ))}
                </List>
              )}
              {application.status === 'guarantors_confirmed' ? (
                <Alert severity="success" sx={{ mt: 1.5 }}>
                  <Typography variant="body2">
                    Guarantor requirement met — this application is ready for committee review.
                  </Typography>
                </Alert>
              ) : pendingCount > 0 ? (
                <Alert severity="info" sx={{ mt: 1.5 }}>
                  <Typography variant="body2">
                    Waiting for {pendingCount} guarantor {pendingCount === 1 ? 'response' : 'responses'} before this
                    application can move forward.
                  </Typography>
                </Alert>
              ) : requiredGuarantors != null && acceptedCount < requiredGuarantors ? (
                <Alert severity="info" sx={{ mt: 1.5 }}>
                  <Typography variant="body2">
                    Add {requiredGuarantors - acceptedCount} more required guarantor
                    {requiredGuarantors - acceptedCount === 1 ? '' : 's'} to move the application forward.
                  </Typography>
                </Alert>
              ) : null}
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      <Dialog open={candidateDialogOpen} onClose={() => setCandidateDialogOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Add a guarantor</DialogTitle>
        <DialogContent>
          <DialogContentText sx={{ mb: 2 }}>
            Select an active member to guarantee this loan. Members with an existing active guarantee obligation or
            already requested on this application are not listed.{' '}
            {requiredGuarantors != null
              ? `You still need ${Math.max(0, requiredGuarantors - acceptedCount)} of ${requiredGuarantors} required guarantors.`
              : null}
          </DialogContentText>
          {candidateError ? (
            <Alert severity="error" sx={{ mb: 2 }}>
              {candidateError}
            </Alert>
          ) : null}
          <TextField
            label="Search by name or member number"
            value={candidateSearch}
            onChange={(e) => setCandidateSearch(e.target.value)}
            fullWidth
            size="small"
            sx={{ mb: 2 }}
          />
          {candidatesQuery.isPending ? (
            <TableSkeleton rows={4} columns={2} />
          ) : candidatesQuery.isError ? (
            <ErrorState
              message={getErrorMessage(candidatesQuery.error, 'Failed to load guarantor candidates.')}
              onRetry={() => void candidatesQuery.refetch()}
            />
          ) : candidatesQuery.data.data.length === 0 ? (
            <Typography variant="body2" color="text.secondary">
              No eligible members found. Adjust your search, or all available members are already requested or carry an
              active guarantee obligation.
            </Typography>
          ) : (
            <List disablePadding>
              {candidatesQuery.data.data.map((member) => (
                <ListItem key={member.id} divider sx={{ px: 0 }}>
                  <ListItemText
                    primary={member.name ?? `Member ${member.member_number}`}
                    secondary={`Member ${member.member_number}`}
                  />
                  <Button
                    size="small"
                    variant="outlined"
                    disabled={
                      requestGuarantorMutation.isPending ||
                      (requiredGuarantors != null && acceptedCount >= requiredGuarantors)
                    }
                    onClick={() => requestGuarantorMutation.mutate(member.id)}
                  >
                    Add
                  </Button>
                </ListItem>
              ))}
            </List>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setCandidateDialogOpen(false)} disabled={requestGuarantorMutation.isPending}>
            Close
          </Button>
        </DialogActions>
      </Dialog>

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