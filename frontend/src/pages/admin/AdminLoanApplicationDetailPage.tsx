import type { ReactNode } from 'react'
import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Dialog from '@mui/material/Dialog'
import DialogActions from '@mui/material/DialogActions'
import DialogContent from '@mui/material/DialogContent'
import DialogContentText from '@mui/material/DialogContentText'
import DialogTitle from '@mui/material/DialogTitle'
import Divider from '@mui/material/Divider'
import MenuItem from '@mui/material/MenuItem'
import Select from '@mui/material/Select'
import Stack from '@mui/material/Stack'
import Table from '@mui/material/Table'
import TableBody from '@mui/material/TableBody'
import TableCell from '@mui/material/TableCell'
import TableContainer from '@mui/material/TableContainer'
import TableHead from '@mui/material/TableHead'
import TableRow from '@mui/material/TableRow'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import { useParams } from 'react-router-dom'

import { PageContainer } from '../../components/common/PageContainer'
import { ConfirmDialog } from '../../components/common/ConfirmDialog'
import { ErrorState } from '../../components/common/ErrorState'
import { StatusPill } from '../../components/common/StatusPill'
import {
  getAdminApplication,
  approveLoanApplication,
  rejectLoanApplication,
  cancelApplicationByAdmin,
  assignInvestigation,
} from '../../services/loans'
import { formatBasisPoints, formatDate, formatNaira } from '../../utils/format'
import { getErrorMessage } from '../../utils/errors'

export function AdminLoanApplicationDetailPage(): ReactNode {
  const { id } = useParams<{ id: string }>()
  const queryClient = useQueryClient()

  const [approveOpen, setApproveOpen] = useState(false)
  const [apprAmount, setApprAmount] = useState('')
  const [apprRate, setApprRate] = useState('')
  const [apprMethod, setApprMethod] = useState<'flat' | 'reducing_balance'>('flat')
  const [apprMonths, setApprMonths] = useState('')
  const [apprReason, setApprReason] = useState('')

  const [rejectOpen, setRejectOpen] = useState(false)
  const [rejectReason, setRejectReason] = useState('')

  const [assignOpen, setAssignOpen] = useState(false)
  const [assignTo, setAssignTo] = useState('')

  const [cancelOpen, setCancelOpen] = useState(false)
  const [cancelReason, setCancelReason] = useState('')

  const { data: application, isLoading, error, refetch } = useQuery({
    queryKey: ['admin-loan-application', id],
    queryFn: () => getAdminApplication(id!),
    enabled: !!id,
  })

  const invalidate = (): void => {
    void queryClient.invalidateQueries({ queryKey: ['admin-loan-application', id] })
  }

  const approveMutation = useMutation({
    mutationFn: () =>
      approveLoanApplication(id!, {
        approved_amount_minor: Math.round(Number(apprAmount) * 100),
        interest_rate_basis_points: Math.round(Number(apprRate) * 100),
        interest_method: apprMethod,
        repayment_months: Number(apprMonths),
        decision_reason: apprReason,
      }),
    onSuccess: () => {
      setApproveOpen(false)
      invalidate()
    },
    onError: (err) => alert(getErrorMessage(err, 'Failed to approve the application.').toString()),
  })

  const rejectMutation = useMutation({
    mutationFn: () => rejectLoanApplication(id!, rejectReason),
    onSuccess: () => {
      setRejectOpen(false)
      setRejectReason('')
      invalidate()
    },
    onError: (err) => alert(getErrorMessage(err, 'Failed to reject the application.').toString()),
  })

  const assignMutation = useMutation({
    mutationFn: () => assignInvestigation(id!, assignTo),
    onSuccess: () => {
      setAssignOpen(false)
      setAssignTo('')
      invalidate()
    },
    onError: (err) => alert(getErrorMessage(err, 'Failed to assign the application.').toString()),
  })

  const cancelMutation = useMutation({
    mutationFn: () => cancelApplicationByAdmin(id!, cancelReason),
    onSuccess: () => {
      setCancelOpen(false)
      setCancelReason('')
      invalidate()
    },
    onError: (err) => alert(getErrorMessage(err, 'Failed to cancel the application.').toString()),
  })

  if (isLoading) {
    return (
      <PageContainer title="Application Detail" subtitle="Review loan application details">
        <div style={{ display: 'flex', justifyContent: 'center', padding: '3rem' }}>
          <Typography>Loading application...</Typography>
        </div>
      </PageContainer>
    )
  }

  if (error || !application) {
    return (
      <PageContainer title="Application Detail" subtitle="Review loan application details">
        <ErrorState message="Failed to load application details." onRetry={() => refetch()} />
      </PageContainer>
    )
  }

  const member = application.member
  const product = application.loan_product
  const canDecide = ['pending_admin_decision', 'committee_reviewed'].includes(application.status)
  const canCancel = !['cancelled', 'rejected', 'approved'].includes(application.status)
  const canAssign = !['cancelled', 'rejected'].includes(application.status)

  return (
    <PageContainer
      title={`Application: ${application.application_number}`}
      subtitle={`Status: ${application.status === 'pending_admin_decision' ? 'Pending Admin Decision' : application.status}`}
      backTo="/admin/loans/applications"
      actions={
        <Stack direction="row" spacing={1.5} sx={{ flexWrap: 'wrap' }}>
          {canAssign ? (
            <Button variant="outlined" onClick={() => setAssignOpen(true)}>
              Assign
            </Button>
          ) : null}
          {canCancel ? (
            <Button variant="outlined" color="inherit" onClick={() => setCancelOpen(true)}>
              Cancel
            </Button>
          ) : null}
          {canDecide ? (
            <>
              <Button variant="outlined" color="error" onClick={() => setRejectOpen(true)}>
                Reject
              </Button>
              <Button variant="contained" color="success" onClick={() => setApproveOpen(true)}>
                Approve
              </Button>
            </>
          ) : null}
        </Stack>
      }
    >
      <Stack spacing={3}>
        <Card variant="outlined">
          <CardContent>
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={3} sx={{ flexWrap: 'wrap' }}>
              <Box sx={{ flexGrow: 1, minWidth: 280 }}>
                <Typography variant="h6" gutterBottom>
                  Member Information
                </Typography>
                <Divider sx={{ mb: 2 }} />
                <Stack spacing={1.5}>
                  <Box>
                    <Typography variant="body2" color="text.secondary">Member Number</Typography>
                    <Typography variant="body1" sx={{ fontWeight: 500 }}>{member?.member_number ?? '—'}</Typography>
                  </Box>
                  <Box>
                    <Typography variant="body2" color="text.secondary">Membership Status</Typography>
                    <StatusPill status={member?.status ?? '—'} />
                  </Box>
                  <Box>
                    <Typography variant="body2" color="text.secondary">Membership Type</Typography>
                    <Typography variant="body1" sx={{ fontWeight: 500 }}>{member?.membership_type ?? '—'}</Typography>
                  </Box>
                  <Box>
                    <Typography variant="body2" color="text.secondary">Joined</Typography>
                    <Typography variant="body1" sx={{ fontWeight: 500 }}>{member?.joined_at ? formatDate(member.joined_at) : '—'}</Typography>
                  </Box>
                </Stack>
              </Box>
              <Box sx={{ flexGrow: 1, minWidth: 280 }}>
                <Typography variant="h6" gutterBottom>
                  Loan Details
                </Typography>
                <Divider sx={{ mb: 2 }} />
                <Stack spacing={1.5}>
                  <Box>
                    <Typography variant="body2" color="text.secondary">Product</Typography>
                    <Typography variant="body1" sx={{ fontWeight: 500 }}>{product?.name ?? '—'}</Typography>
                  </Box>
                  <Box>
                    <Typography variant="body2" color="text.secondary">Amount Requested</Typography>
                    <Typography variant="h5" component="h2" sx={{ fontWeight: 700, color: 'primary.main' }}>
                      {formatNaira(application.amount_requested_minor)}
                    </Typography>
                  </Box>
                  <Box>
                    <Typography variant="body2" color="text.secondary">Purpose</Typography>
                    <Typography variant="body1" sx={{ fontWeight: 500 }}>{application.purpose}</Typography>
                  </Box>
                  <Box>
                    <Typography variant="body2" color="text.secondary">Interest Rate</Typography>
                    <Typography variant="body1" sx={{ fontWeight: 500 }}>
                      {product ? formatBasisPoints(product.interest_rate_basis_points) : '—'} ({product?.interest_method ?? '—'})
                    </Typography>
                  </Box>
                  <Box>
                    <Typography variant="body2" color="text.secondary">Repayment Term</Typography>
                    <Typography variant="body1" sx={{ fontWeight: 500 }}>{product?.repayment_months ?? '—'} months</Typography>
                  </Box>
                  <Box>
                    <Typography variant="body2" color="text.secondary">Submitted</Typography>
                    <Typography variant="body1" sx={{ fontWeight: 500 }}>{application.submitted_at ? formatDate(application.submitted_at) : '—'}</Typography>
                  </Box>
                  <Box>
                    <Typography variant="body2" color="text.secondary">Status</Typography>
                    <StatusPill status={application.status} />
                  </Box>
                </Stack>
              </Box>
            </Stack>
          </CardContent>
        </Card>

        {application.guarantors.length > 0 ? (
          <Card variant="outlined">
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Guarantors
              </Typography>
              <TableContainer>
                <Table>
                  <TableHead>
                    <TableRow>
                      <TableCell>Member #</TableCell>
                      <TableCell>Status</TableCell>
                      <TableCell>Requested</TableCell>
                      <TableCell>Responded</TableCell>
                      <TableCell>Response Note</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {application.guarantors.map((g) => (
                      <TableRow key={g.id}>
                        <TableCell>
                          <Typography variant="body2" sx={{ fontWeight: 500 }}>
                            {g.guarantor_member?.member_number ?? '—'}
                          </Typography>
                        </TableCell>
                        <TableCell>
                          <StatusPill status={g.status} />
                        </TableCell>
                        <TableCell>
                          <Typography variant="body2" color="text.secondary">{g.requested_at ? formatDate(g.requested_at) : '—'}</Typography>
                        </TableCell>
                        <TableCell>
                          <Typography variant="body2" color="text.secondary">{g.responded_at ? formatDate(g.responded_at) : '—'}</Typography>
                        </TableCell>
                        <TableCell>
                          <Typography variant="body2" color="text.secondary">{g.response_note ?? '—'}</Typography>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </TableContainer>
            </CardContent>
          </Card>
        ) : null}

        {application.investigation ? (
          <Card variant="outlined">
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Investigation
              </Typography>
              <Stack spacing={1.5}>
                <Stack direction="row" spacing={2} sx={{ flexWrap: 'wrap', alignItems: 'center' }}>
                  <StatusPill status={application.investigation.status} />
                  {application.investigation.submitted_at ? (
                    <Typography variant="body2" color="text.secondary">
                      Submitted {formatDate(application.investigation.submitted_at)}
                    </Typography>
                  ) : null}
                </Stack>
                {application.investigation.recommendation ? (
                  <Box>
                    <Typography variant="body2" color="text.secondary">Recommendation</Typography>
                    <Typography variant="body1" sx={{ fontWeight: 500 }}>{application.investigation.recommendation}</Typography>
                  </Box>
                ) : null}
                {application.investigation.committee_comments ? (
                  <Box>
                    <Typography variant="body2" color="text.secondary">Committee Comments</Typography>
                    <Typography variant="body1" sx={{ whiteSpace: 'pre-wrap' }}>{application.investigation.committee_comments}</Typography>
                  </Box>
                ) : null}
              </Stack>
            </CardContent>
          </Card>
        ) : null}

        {application.decision ? (
          <Card variant="outlined">
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Decision
              </Typography>
              <Stack spacing={1.5}>
                <Stack direction="row" spacing={2} sx={{ flexWrap: 'wrap', alignItems: 'center' }}>
                  <StatusPill status={application.decision.decision} />
                  {application.decision.decided_at ? (
                    <Typography variant="body2" color="text.secondary">
                      Decided {formatDate(application.decision.decided_at)}
                    </Typography>
                  ) : null}
                </Stack>
                {application.decision.decision_reason ? (
                  <Box>
                    <Typography variant="body2" color="text.secondary">Reason</Typography>
                    <Typography variant="body1" sx={{ whiteSpace: 'pre-wrap' }}>{application.decision.decision_reason}</Typography>
                  </Box>
                ) : null}
              </Stack>
            </CardContent>
          </Card>
        ) : null}
      </Stack>

      {/* Approve dialog */}
      <Dialog open={approveOpen} onClose={() => setApproveOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Approve Loan Application</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            <TextField
              label="Approved amount (₦)"
              type="number"
              value={apprAmount}
              onChange={(e) => setApprAmount(e.target.value)}
              fullWidth
              size="small"
            />
            <TextField
              label="Interest rate (%)"
              type="number"
              value={apprRate}
              onChange={(e) => setApprRate(e.target.value)}
              fullWidth
              size="small"
            />
            <Select
              label="Interest method"
              value={apprMethod}
              onChange={(e) => setApprMethod(e.target.value as 'flat' | 'reducing_balance')}
              fullWidth
              size="small"
            >
              <MenuItem value="flat">Flat</MenuItem>
              <MenuItem value="reducing_balance">Reducing Balance</MenuItem>
            </Select>
            <TextField
              label="Repayment months"
              type="number"
              value={apprMonths}
              onChange={(e) => setApprMonths(e.target.value)}
              fullWidth
              size="small"
            />
            <TextField
              label="Decision reason"
              value={apprReason}
              onChange={(e) => setApprReason(e.target.value)}
              fullWidth
              multiline
              minRows={3}
              size="small"
            />
            <DialogContentText>
              The loan will be created in the selected terms and queued for disbursement.
            </DialogContentText>
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setApproveOpen(false)} disabled={approveMutation.isPending}>
            Cancel
          </Button>
          <Button
            color="success"
            variant="contained"
            onClick={() => approveMutation.mutate()}
            disabled={approveMutation.isPending || !apprAmount || !apprRate || !apprMonths || !apprReason.trim()}
          >
            {approveMutation.isPending ? 'Approving...' : 'Approve'}
          </Button>
        </DialogActions>
      </Dialog>

      {/* Reject dialog */}
      <Dialog open={rejectOpen} onClose={() => setRejectOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Reject Loan Application</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            <TextField
              label="Rejection reason"
              value={rejectReason}
              onChange={(e) => setRejectReason(e.target.value)}
              fullWidth
              multiline
              minRows={3}
              size="small"
              placeholder="Explain why the application is being rejected..."
            />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setRejectOpen(false)} disabled={rejectMutation.isPending}>
            Cancel
          </Button>
          <Button
            color="error"
            variant="contained"
            onClick={() => rejectMutation.mutate()}
            disabled={rejectMutation.isPending || !rejectReason.trim()}
          >
            {rejectMutation.isPending ? 'Rejecting...' : 'Reject Application'}
          </Button>
        </DialogActions>
      </Dialog>

      {/* Assign dialog */}
      <Dialog open={assignOpen} onClose={() => setAssignOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Assign Investigation</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            <TextField
              label="Officer user ID"
              value={assignTo}
              onChange={(e) => setAssignTo(e.target.value)}
              fullWidth
              size="small"
              placeholder="UUID of the committee officer..."
            />
            <DialogContentText>
              The application will be assigned to this officer for investigation.
            </DialogContentText>
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setAssignOpen(false)} disabled={assignMutation.isPending}>
            Cancel
          </Button>
          <Button
            variant="contained"
            onClick={() => assignMutation.mutate()}
            disabled={assignMutation.isPending || !assignTo.trim()}
          >
            {assignMutation.isPending ? 'Assigning...' : 'Assign'}
          </Button>
        </DialogActions>
      </Dialog>

      {/* Cancel confirmation */}
      <ConfirmDialog
        open={cancelOpen}
        title="Cancel this application?"
        message="Cancelling removes this application from the review pipeline. This cannot be undone."
        confirmLabel="Cancel application"
        danger
        loading={cancelMutation.isPending}
        onConfirm={() => cancelMutation.mutate()}
        onClose={() => setCancelOpen(false)}
      />
    </PageContainer>
  )
}