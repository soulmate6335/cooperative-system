import type { ReactNode } from 'react'
import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import Alert from '@mui/material/Alert'
import ArrowBackIcon from '@mui/icons-material/ArrowBack'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Dialog from '@mui/material/Dialog'
import DialogActions from '@mui/material/DialogActions'
import DialogContent from '@mui/material/DialogContent'
import DialogTitle from '@mui/material/DialogTitle'
import Divider from '@mui/material/Divider'
import MenuItem from '@mui/material/MenuItem'
import Stack from '@mui/material/Stack'
import Table from '@mui/material/Table'
import TableBody from '@mui/material/TableBody'
import TableCell from '@mui/material/TableCell'
import TableContainer from '@mui/material/TableContainer'
import TableHead from '@mui/material/TableHead'
import TableRow from '@mui/material/TableRow'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import { useNavigate, useParams } from 'react-router-dom'

import { PageContainer } from '../../components/common/PageContainer'
import { StatusPill } from '../../components/common/StatusPill'
import {
  approveAdminLoan,
  getAdminLoanDecision,
  rejectAdminLoan,
  type ApproveLoanPayload,
} from '../../services/loans'
import { formatBasisPoints, formatDate, formatNaira } from '../../utils/format'
import { getErrorMessage } from '../../utils/errors'

function DetailField({ label, value }: { label: string; value: ReactNode }): ReactNode {
  return (
    <Box>
      <Typography variant="caption" color="text.secondary">
        {label}
      </Typography>
      <Typography variant="body2">{value}</Typography>
    </Box>
  )
}

function DetailSection({
  title,
  children,
}: {
  title: string
  children: ReactNode
}): ReactNode {
  return (
    <Card variant="outlined">
      <CardContent>
        <Typography variant="h6" gutterBottom>
          {title}
        </Typography>
        {children}
      </CardContent>
    </Card>
  )
}

function FieldGrid({ children }: { children: ReactNode }): ReactNode {
  return (
    <Box
      sx={{
        display: 'grid',
        gridTemplateColumns: { xs: '1fr', md: '1fr 1fr' },
        gap: 2,
      }}
    >
      {children}
    </Box>
  )
}

export function AdminLoanDecisionDetailPage(): ReactNode {
  const { applicationId } = useParams<{ applicationId: string }>()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const [approveOpen, setApproveOpen] = useState(false)
  const [apprAmount, setApprAmount] = useState('')
  const [apprRate, setApprRate] = useState('')
  const [apprMethod, setApprMethod] = useState<'flat' | 'reducing_balance'>('flat')
  const [apprMonths, setApprMonths] = useState('')
  const [apprReason, setApprReason] = useState('')
  const [approveErrors, setApproveErrors] = useState<Record<string, string>>({})
  const [approveServerError, setApproveServerError] = useState<string | null>(null)

  const [rejectOpen, setRejectOpen] = useState(false)
  const [rejectReason, setRejectReason] = useState('')
  const [rejectErrors, setRejectErrors] = useState<Record<string, string>>({})
  const [rejectServerError, setRejectServerError] = useState<string | null>(null)

  const { data: detail, isLoading, error, refetch } = useQuery({
    queryKey: ['admin-loan-decision', applicationId],
    queryFn: () => getAdminLoanDecision(applicationId!),
    enabled: !!applicationId,
  })

  const invalidate = (): void => {
    void queryClient.invalidateQueries({ queryKey: ['admin-loan-decision', applicationId] })
    void queryClient.invalidateQueries({ queryKey: ['admin-loan-decisions'] })
  }

  const approveMutation = useMutation({
    mutationFn: (payload: ApproveLoanPayload) => approveAdminLoan(applicationId!, payload),
    onSuccess: () => {
      setApproveOpen(false)
      invalidate()
    },
    onError: (err) => setApproveServerError(getErrorMessage(err, 'Failed to approve the loan application.')),
  })

  const rejectMutation = useMutation({
    mutationFn: (reason: string) => rejectAdminLoan(applicationId!, reason),
    onSuccess: () => {
      setRejectOpen(false)
      invalidate()
    },
    onError: (err) => setRejectServerError(getErrorMessage(err, 'Failed to reject the loan application.')),
  })

  const openApprove = (): void => {
    setApprAmount(application ? String((application.amount_requested_minor ?? 0) / 100) : '')
    setApprRate(
      product?.interest_rate_basis_points !== null && product?.interest_rate_basis_points !== undefined
        ? String(product.interest_rate_basis_points / 100)
        : '0',
    )
    setApprMethod(product?.interest_method === 'reducing_balance' ? 'reducing_balance' : 'flat')
    setApprMonths(product?.repayment_months ? String(product.repayment_months) : '12')
    setApprReason('')
    setApproveErrors({})
    setApproveServerError(null)
    setApproveOpen(true)
  }

  const handleApproveSubmit = (): void => {
    const errors: Record<string, string> = {}
    const amount = Number(apprAmount)
    const rate = Number(apprRate)
    const months = Number(apprMonths)

    if (!apprAmount.trim() || Number.isNaN(amount) || amount <= 0) {
      errors.amount = 'Enter a valid approved amount.'
    } else {
      const minor = Math.round(amount * 100)
      if (product && minor < product.minimum_amount_minor) {
        errors.amount = `Minimum approved amount is ${formatNaira(product.minimum_amount_minor)}.`
      } else if (product && product.maximum_amount_minor !== null && minor > product.maximum_amount_minor) {
        errors.amount = `Maximum approved amount is ${formatNaira(product.maximum_amount_minor)}.`
      }
    }

    if (!apprRate.trim() || Number.isNaN(rate) || rate < 0) {
      errors.rate = 'Enter a valid interest rate.'
    }
    if (!apprMonths.trim() || Number.isNaN(months) || !Number.isInteger(months) || months < 1) {
      errors.months = 'Repayment months must be a whole number of at least 1.'
    }
    if (!apprReason.trim()) {
      errors.reason = 'A decision reason is required.'
    }

    setApproveErrors(errors)
    if (Object.keys(errors).length > 0) {
      return
    }

    approveMutation.mutate({
      approved_amount_minor: Math.round(amount * 100),
      interest_rate_basis_points: Math.round(rate * 100),
      interest_method: apprMethod,
      repayment_months: months,
      decision_reason: apprReason.trim(),
    })
  }

  const handleRejectSubmit = (): void => {
    if (!rejectReason.trim()) {
      setRejectErrors({ reason: 'A rejection reason is required.' })
      return
    }
    rejectMutation.mutate(rejectReason.trim())
  }

  if (isLoading) {
    return (
      <PageContainer title="Final Loan Decision" subtitle="Review the full picture before deciding">
        <div style={{ display: 'flex', justifyContent: 'center', padding: '3rem' }}>
          <Typography>Loading loan decision...</Typography>
        </div>
      </PageContainer>
    )
  }

  if (error || !detail) {
    return (
      <PageContainer title="Final Loan Decision" subtitle="Review the full picture before deciding">
        <div style={{ textAlign: 'center', padding: '3rem' }}>
          <Typography color="error">Failed to load the loan decision. Please try again.</Typography>
          <Button variant="contained" onClick={() => void refetch()} sx={{ mt: 2 }}>
            Retry
          </Button>
        </div>
      </PageContainer>
    )
  }

  const application = detail.application
  const product = application.loan_product
  const investigation = application.investigation
  const decision = application.decision

  return (
    <PageContainer
      title="Final Loan Decision"
      subtitle={`Application ${application.application_number} • ${application.member?.name ?? 'Member'}`}
    >
      <Button
        startIcon={<ArrowBackIcon fontSize="small" />}
        size="small"
        onClick={() => navigate('/admin/loan-decisions')}
        sx={{ mb: 2 }}
      >
        Decision queue
      </Button>

      <Stack spacing={2.5}>
        {/* Applicant */}
        <DetailSection title="Applicant">
          <FieldGrid>
            <DetailField label="Name" value={application.member?.name ?? '—'} />
            <DetailField label="Member number" value={application.member?.member_number ?? '—'} />
            <DetailField label="Membership type" value={application.member?.membership_type ?? '—'} />
            <DetailField
              label="Status"
              value={application.member ? <StatusPill status={application.member.status} /> : '—'}
            />
            <DetailField label="Joined" value={application.member?.joined_at ? formatDate(application.member.joined_at) : '—'} />
          </FieldGrid>
        </DetailSection>

        {/* Loan request */}
        <DetailSection title="Loan Request">
          <FieldGrid>
            <DetailField label="Application number" value={application.application_number} />
            <DetailField label="Status" value={<StatusPill status={application.status} />} />
            <DetailField label="Amount requested" value={formatNaira(application.amount_requested_minor)} />
            <DetailField label="Purpose" value={application.purpose ?? '—'} />
            <DetailField label="Submitted" value={application.submitted_at ? formatDate(application.submitted_at) : '—'} />
            <DetailField label="Application created" value={formatDate(application.created_at)} />
          </FieldGrid>
        </DetailSection>

        {/* Loan product */}
        <DetailSection title="Loan Product">
          <FieldGrid>
            <DetailField label="Product" value={product?.name ?? '—'} />
            <DetailField label="Status" value={product ? <StatusPill status={product.status} /> : '—'} />
            <DetailField label="Minimum amount" value={product ? formatNaira(product.minimum_amount_minor) : '—'} />
            <DetailField label="Maximum amount" value={product ? formatNaira(product.maximum_amount_minor) : '—'} />
            <DetailField label="Interest rate" value={product ? formatBasisPoints(product.interest_rate_basis_points) : '—'} />
            <DetailField label="Interest method" value={product?.interest_method ?? '—'} />
            <DetailField label="Repayment months" value={product?.repayment_months ?? '—'} />
            <DetailField label="Required guarantors" value={product?.required_guarantors ?? '—'} />
          </FieldGrid>
        </DetailSection>

        {/* Committee meeting */}
        <DetailSection title="Committee Meeting">
          <FieldGrid>
            <DetailField
              label="Meeting date"
              value={application.committee_meeting?.meeting_date ? formatDate(application.committee_meeting.meeting_date) : '—'}
            />
            <DetailField label="Meeting type" value={application.committee_meeting?.meeting_type ?? '—'} />
            <DetailField label="Notes" value={application.committee_meeting?.notes ?? '—'} />
          </FieldGrid>
        </DetailSection>

        {/* Guarantors */}
        <DetailSection title="Guarantors">
          {application.guarantors.length === 0 ? (
            <Typography variant="body2" color="text.secondary">
              No guarantors recorded.
            </Typography>
          ) : (
            <TableContainer>
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>Guarantor</TableCell>
                    <TableCell>Status</TableCell>
                    <TableCell>Requested</TableCell>
                    <TableCell>Responded</TableCell>
                    <TableCell>Note</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {application.guarantors.map((guarantor) => (
                    <TableRow key={guarantor.id}>
                      <TableCell>
                        <Typography variant="body2">
                          {guarantor.guarantor_member?.name ?? '—'}
                          <Typography variant="caption" color="text.secondary" component="span" sx={{ ml: 1 }}>
                            {guarantor.guarantor_member?.member_number ?? ''}
                          </Typography>
                        </Typography>
                      </TableCell>
                      <TableCell>
                        <StatusPill status={guarantor.status} />
                      </TableCell>
                      <TableCell>
                        {guarantor.requested_at ? formatDate(guarantor.requested_at) : '—'}
                      </TableCell>
                      <TableCell>
                        {guarantor.responded_at ? formatDate(guarantor.responded_at) : '—'}
                      </TableCell>
                      <TableCell>
                        <Typography variant="body2" color="text.secondary">
                          {guarantor.response_note ?? '—'}
                        </Typography>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          )}
        </DetailSection>

        {/* Eligibility */}
        <DetailSection title="Eligibility">
          <Alert severity={detail.eligibility.decision.status === 'eligible' ? 'success' : detail.eligibility.decision.status === 'ineligible' ? 'error' : 'warning'} sx={{ mb: 2 }}>
            Current eligibility decision: {detail.eligibility.decision.status}.
          </Alert>
          <FieldGrid>
            <DetailField
              label="Decision"
              value={<StatusPill status={detail.eligibility.decision.status} />}
            />
            <DetailField label="Decided by" value={detail.eligibility.decision.decided_by?.name ?? '—'} />
            <DetailField label="Reason" value={detail.eligibility.decision.reason ?? '—'} />
            <DetailField
              label="Decided at"
              value={detail.eligibility.decision.decided_at ? formatDate(detail.eligibility.decision.decided_at) : '—'}
            />
          </FieldGrid>
          <Divider sx={{ my: 2 }} />
          <Typography variant="subtitle2" gutterBottom>
            Eligibility factors
          </Typography>
          <FieldGrid>
            <DetailField
              label="Membership period"
              value={`${detail.eligibility.factors.membership_months} of ${detail.eligibility.factors.minimum_membership_months} months`}
            />
            <DetailField
              label="Member active"
              value={detail.eligibility.factors.member_active ? 'Yes' : 'No'}
            />
            <DetailField
              label="Product active"
              value={detail.eligibility.factors.product_active ? 'Yes' : 'No'}
            />
            <DetailField label="Savings balance" value={formatNaira(detail.eligibility.factors.savings_balance_minor)} />
            <DetailField label="Shares balance" value={formatNaira(detail.eligibility.factors.shares_balance_minor)} />
            <DetailField label="Product amount range" value={`${formatNaira(detail.eligibility.factors.minimum_amount_minor)} – ${formatNaira(detail.eligibility.factors.maximum_amount_minor)}`} />
            <DetailField
              label="Computed maximum"
              value={detail.eligibility.factors.computed_maximum_amount_minor !== null ? formatNaira(detail.eligibility.factors.computed_maximum_amount_minor) : '—'}
            />
          </FieldGrid>
          {detail.eligibility.factors.reasons.length > 0 && (
            <Box sx={{ mt: 2 }}>
              <DetailField label="Reasons" value={detail.eligibility.factors.reasons.join(' • ')} />
            </Box>
          )}
        </DetailSection>

        {/* Committee investigation */}
        <DetailSection title="Committee Investigation">
          <Alert severity="info" sx={{ mb: 2 }}>
            The committee's work below is a recommendation only. It does not approve or reject the loan — the
            final decision is made by an administrator in the Final Decision section.
          </Alert>
          <FieldGrid>
            <DetailField label="Investigator" value={investigation?.assignee?.name ?? investigation?.assigned_to ?? '—'} />
            <DetailField label="Submitted" value={investigation?.submitted_at ? formatDate(investigation.submitted_at) : '—'} />
            <DetailField label="Member findings" value={investigation?.member_findings ?? '—'} />
            <DetailField label="Savings findings" value={investigation?.savings_findings ?? '—'} />
            <DetailField label="Shares findings" value={investigation?.shares_findings ?? '—'} />
            <DetailField label="Existing loan findings" value={investigation?.existing_loan_findings ?? '—'} />
            <DetailField label="Guarantor findings" value={investigation?.guarantor_findings ?? '—'} />
            <DetailField label="Committee comments" value={investigation?.committee_comments ?? '—'} />
            <DetailField label="Committee recommendation" value={investigation?.recommendation ?? '—'} />
          </FieldGrid>
        </DetailSection>

        {/* Final decision */}
        <DetailSection title="Final Decision">
          {application.status === 'pending_admin_decision' ? (
            <>
              <Alert severity="info" sx={{ mb: 2 }}>
                Approving records the loan as approved only. No disbursement, payment, or transaction is
                created here — disbursement is a later, separate step.
              </Alert>
              <Stack direction="row" spacing={2}>
                <Button variant="contained" color="success" onClick={openApprove}>
                  Approve Loan
                </Button>
                <Button variant="outlined" color="error" onClick={() => setRejectOpen(true)}>
                  Reject Application
                </Button>
              </Stack>
            </>
          ) : decision && decision.decision === 'approved' ? (
            <>
              <Alert severity="success" sx={{ mb: 2 }}>
                Approved — awaiting disbursement.
              </Alert>
              <FieldGrid>
                <DetailField label="Approved amount" value={formatNaira(decision.approved_amount_minor)} />
                <DetailField label="Interest rate" value={formatBasisPoints(decision.interest_rate_basis_points)} />
                <DetailField label="Interest method" value={decision.interest_method ?? '—'} />
                <DetailField label="Repayment months" value={decision.repayment_months ?? '—'} />
                <DetailField label="Decided by" value={decision.decided_by_name ?? decision.decided_by ?? '—'} />
                <DetailField label="Decided at" value={decision.decided_at ? formatDate(decision.decided_at) : '—'} />
                <DetailField label="Decision reason" value={decision.decision_reason ?? '—'} />
                <DetailField label="Loan number" value={application.loan?.loan_number ?? '—'} />
              </FieldGrid>
            </>
          ) : decision && decision.decision === 'rejected' ? (
            <>
              <Alert severity="error" sx={{ mb: 2 }}>
                Rejected — the member must submit a new application to apply again.
              </Alert>
              <FieldGrid>
                <DetailField label="Decided by" value={decision.decided_by_name ?? decision.decided_by ?? '—'} />
                <DetailField label="Decided at" value={decision.decided_at ? formatDate(decision.decided_at) : '—'} />
                <DetailField label="Decision reason" value={decision.decision_reason ?? application.rejection_reason ?? '—'} />
              </FieldGrid>
            </>
          ) : (
            <Typography variant="body2" color="text.secondary">
              No final decision has been recorded for this application.
            </Typography>
          )}
        </DetailSection>
      </Stack>

      {/* Approve dialog */}
      <Dialog open={approveOpen} onClose={() => setApproveOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Approve Loan Application</DialogTitle>
        <DialogContent>
          <Alert severity="info" sx={{ mb: 2 }}>
            These final terms are recorded on the loan and its approval decision. They are not editable later,
            so re-verify them before approving.
          </Alert>
          {approveServerError && (
            <Alert severity="error" sx={{ mb: 2 }}>
              {approveServerError}
            </Alert>
          )}
          <Stack spacing={2} sx={{ mt: 1 }}>
            <TextField
              label="Approved amount (₦)"
              value={apprAmount}
              onChange={(event) => setApprAmount(event.target.value)}
              error={Boolean(approveErrors.amount)}
              helperText={approveErrors.amount ?? 'Enter the approved amount in naira.'}
              fullWidth
            />
            <TextField
              label="Interest rate (%)"
              value={apprRate}
              onChange={(event) => setApprRate(event.target.value)}
              error={Boolean(approveErrors.rate)}
              helperText={approveErrors.rate ?? 'Annual rate as a percentage, e.g. 12 for 12%.'}
              fullWidth
            />
            <TextField
              select
              label="Interest method"
              value={apprMethod}
              onChange={(event) => setApprMethod(event.target.value as 'flat' | 'reducing_balance')}
              fullWidth
            >
              <MenuItem value="flat">Flat</MenuItem>
              <MenuItem value="reducing_balance">Reducing balance</MenuItem>
            </TextField>
            <TextField
              label="Repayment months"
              value={apprMonths}
              onChange={(event) => setApprMonths(event.target.value)}
              error={Boolean(approveErrors.months)}
              helperText={approveErrors.months ?? 'Number of monthly installments.'}
              fullWidth
            />
            <TextField
              label="Decision reason"
              value={apprReason}
              onChange={(event) => setApprReason(event.target.value)}
              error={Boolean(approveErrors.reason)}
              helperText={approveErrors.reason ?? 'Why is this loan being approved?'}
              multiline
              minRows={2}
              fullWidth
            />
          </Stack>
        </DialogContent>
        <DialogActions sx={{ px: 3, pb: 2 }}>
          <Button onClick={() => setApproveOpen(false)} disabled={approveMutation.isPending}>
            Cancel
          </Button>
          <Button
            variant="contained"
            color="success"
            onClick={handleApproveSubmit}
            disabled={approveMutation.isPending}
          >
            {approveMutation.isPending ? 'Approving…' : 'Approve Loan'}
          </Button>
        </DialogActions>
      </Dialog>

      {/* Reject dialog */}
      <Dialog open={rejectOpen} onClose={() => setRejectOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Reject Loan Application</DialogTitle>
        <DialogContent>
          <Alert severity="warning" sx={{ mb: 2 }}>
            Rejection is final. The member must submit a new application to apply again.
          </Alert>
          {rejectServerError && (
            <Alert severity="error" sx={{ mb: 2 }}>
              {rejectServerError}
            </Alert>
          )}
          <TextField
            label="Rejection reason"
            value={rejectReason}
            onChange={(event) => {
              setRejectReason(event.target.value)
              setRejectErrors({})
            }}
            error={Boolean(rejectErrors.reason)}
            helperText={rejectErrors.reason ?? 'Required — the reason is recorded with the decision.'}
            multiline
            minRows={2}
            fullWidth
            sx={{ mt: 1 }}
          />
        </DialogContent>
        <DialogActions sx={{ px: 3, pb: 2 }}>
          <Button onClick={() => setRejectOpen(false)} disabled={rejectMutation.isPending}>
            Cancel
          </Button>
          <Button
            variant="contained"
            color="error"
            onClick={handleRejectSubmit}
            disabled={rejectMutation.isPending}
          >
            {rejectMutation.isPending ? 'Rejecting…' : 'Reject Application'}
          </Button>
        </DialogActions>
      </Dialog>
    </PageContainer>
  )
}