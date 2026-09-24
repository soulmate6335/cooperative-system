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
import Stack from '@mui/material/Stack'
import Table from '@mui/material/Table'
import TableBody from '@mui/material/TableBody'
import TableCell from '@mui/material/TableCell'
import TableContainer from '@mui/material/TableContainer'
import TableHead from '@mui/material/TableHead'
import TableRow from '@mui/material/TableRow'
import Typography from '@mui/material/Typography'
import { useNavigate, useParams } from 'react-router-dom'

import { PageContainer } from '../../components/common/PageContainer'
import { StatusPill } from '../../components/common/StatusPill'
import { disburseLoan, getAdminLoanDisbursement } from '../../services/loans'
import { formatBasisPoints, formatDate, formatDateTime, formatNaira } from '../../utils/format'
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

export function AdminLoanDisbursementDetailPage(): ReactNode {
  const { loanId } = useParams<{ loanId: string }>()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const [disburseOpen, setDisburseOpen] = useState(false)
  const [disburseError, setDisburseError] = useState<string | null>(null)

  const { data: loan, isLoading, error, refetch } = useQuery({
    queryKey: ['admin-loan-disbursement', loanId],
    queryFn: () => getAdminLoanDisbursement(loanId!),
    enabled: !!loanId,
  })

  const disburseMutation = useMutation({
    mutationFn: () => disburseLoan(loanId!, loan!.principal_amount_minor),
    onSuccess: () => {
      setDisburseOpen(false)
      setDisburseError(null)
      void queryClient.invalidateQueries({ queryKey: ['admin-loan-disbursement', loanId] })
      void queryClient.invalidateQueries({ queryKey: ['admin-loan-disbursements'] })
    },
    onError: (err) => setDisburseError(getErrorMessage(err, 'Failed to disburse the loan.')),
  })

  const openDisburse = (): void => {
    setDisburseError(null)
    setDisburseOpen(true)
  }

  if (isLoading) {
    return (
      <PageContainer title="Loan Disbursement" subtitle="Review before authorizing the disbursement">
        <div style={{ display: 'flex', justifyContent: 'center', padding: '3rem' }}>
          <Typography>Loading loan...</Typography>
        </div>
      </PageContainer>
    )
  }

  if (error || !loan) {
    return (
      <PageContainer title="Loan Disbursement" subtitle="Review before authorizing the disbursement">
        <div style={{ textAlign: 'center', padding: '3rem' }}>
          <Typography color="error">Failed to load the loan. Please try again.</Typography>
          <Button variant="contained" onClick={() => void refetch()} sx={{ mt: 2 }}>
            Retry
          </Button>
        </div>
      </PageContainer>
    )
  }

  const installments = loan.installments ?? []
  const nextDue = loan.obligations?.next_due_installment ?? null
  const isReducingBalance = loan.interest_method === 'reducing_balance'
  const isDisbursed = loan.status === 'disbursed' || loan.status === 'completed'

  return (
    <PageContainer
      title="Loan Disbursement"
      subtitle={`Loan ${loan.loan_number} • ${loan.member?.name ?? 'Member'}`}
    >
      <Button
        startIcon={<ArrowBackIcon fontSize="small" />}
        size="small"
        onClick={() => navigate('/admin/loans/disbursements')}
        sx={{ mb: 2 }}
      >
        Disbursement queue
      </Button>

      <Stack spacing={2.5}>
        {/* Loan overview */}
        <DetailSection title="Loan Overview">
          <Alert severity={isDisbursed ? 'success' : 'info'} sx={{ mb: 2 }}>
            {isDisbursed
              ? `This loan has been disbursed (${formatNaira(loan.disbursement?.amount_minor ?? loan.disbursed_amount_minor)}).`
              : 'This loan is approved and ready for its single, full disbursement.'}
          </Alert>
          <FieldGrid>
            <DetailField label="Status" value={<StatusPill status={loan.status} />} />
            <DetailField label="Loan number" value={loan.loan_number} />
            <DetailField label="Member" value={loan.member?.name ?? '—'} />
            <DetailField label="Member number" value={loan.member?.member_number ?? '—'} />
            <DetailField label="Product" value={loan.product?.name ?? '—'} />
            <DetailField
              label="Principal (full disbursement)"
              value={formatNaira(loan.principal_amount_minor)}
            />
            <DetailField label="Interest rate" value={formatBasisPoints(loan.interest_rate_basis_points)} />
            <DetailField label="Interest method" value={loan.interest_method} />
            <DetailField label="Repayment months" value={loan.repayment_months} />
            <DetailField label="Total interest" value={formatNaira(loan.interest_amount_minor)} />
            <DetailField label="Total payable" value={formatNaira(loan.total_payable_minor)} />
            <DetailField label="Created" value={formatDate(loan.created_at)} />
          </FieldGrid>
          {!isDisbursed && isReducingBalance && (
            <Alert severity="warning" sx={{ mt: 2 }}>
              This loan was approved with the reducing-balance interest method. The repayment schedule formula
              for that method is not yet defined in the system, so disbursement is blocked until the formula is
              specified.
            </Alert>
          )}
          {!isDisbursed && !isReducingBalance && (
            <Alert severity="info" sx={{ mt: 2 }}>
              Disbursement posts the full approved principal to this member's loan account as a financial
              transaction and generates the flat-rate repayment schedule in one atomic step.
            </Alert>
          )}
        </DetailSection>

        {/* Disbursement record */}
        {loan.disbursement && (
          <DetailSection title="Disbursement Record">
            <FieldGrid>
              <DetailField label="Disbursed amount" value={formatNaira(loan.disbursement.amount_minor)} />
              <DetailField label="Status" value={<StatusPill status={loan.disbursement.status} />} />
              <DetailField
                label="Disbursed at"
                value={loan.disbursement.disbursed_at ? formatDateTime(loan.disbursement.disbursed_at) : '—'}
              />
              <DetailField label="Reference" value={loan.disbursement.reference ?? '—'} />
              <DetailField label="Financial account" value={loan.disbursement.financial_account_id ?? '—'} />
            </FieldGrid>
          </DetailSection>
        )}

        {/* Obligations */}
        {loan.obligations && (
          <DetailSection title="Obligations">
            <FieldGrid>
              <DetailField label="Total obligation" value={formatNaira(loan.obligations.total_obligation_minor)} />
              <DetailField label="Total paid" value={formatNaira(loan.obligations.total_paid_minor)} />
              <DetailField
                label="Total outstanding"
                value={formatNaira(loan.obligations.total_outstanding_minor)}
              />
              <DetailField
                label="Principal outstanding"
                value={formatNaira(loan.obligations.principal_outstanding_minor)}
              />
              <DetailField
                label="Interest outstanding"
                value={formatNaira(loan.obligations.interest_outstanding_minor)}
              />
              <DetailField
                label="Next due"
                value={
                  nextDue
                    ? `Installment ${nextDue.installment_number} • ${formatNaira(nextDue.outstanding_minor)}`
                    : '—'
                }
              />
            </FieldGrid>
          </DetailSection>
        )}

        {/* Repayment schedule */}
        <DetailSection title="Repayment Schedule">
          {installments.length === 0 ? (
            <Typography variant="body2" color="text.secondary">
              The repayment schedule is generated when the loan is disbursed.
            </Typography>
          ) : (
            <TableContainer>
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>#</TableCell>
                    <TableCell>Due Date</TableCell>
                    <TableCell align="right">Principal</TableCell>
                    <TableCell align="right">Interest</TableCell>
                    <TableCell align="right">Total</TableCell>
                    <TableCell align="right">Paid</TableCell>
                    <TableCell align="right">Outstanding</TableCell>
                    <TableCell>Status</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {installments.map((installment) => (
                    <TableRow key={installment.id}>
                      <TableCell>{installment.installment_number}</TableCell>
                      <TableCell>{installment.due_date ? formatDate(installment.due_date) : '—'}</TableCell>
                      <TableCell align="right">{formatNaira(installment.principal_due_minor)}</TableCell>
                      <TableCell align="right">{formatNaira(installment.interest_due_minor)}</TableCell>
                      <TableCell align="right">{formatNaira(installment.total_due_minor)}</TableCell>
                      <TableCell align="right">{formatNaira(installment.total_paid_minor)}</TableCell>
                      <TableCell align="right">{formatNaira(installment.outstanding_minor)}</TableCell>
                      <TableCell>
                        <StatusPill status={installment.status} />
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          )}
        </DetailSection>

        {/* Disburse action */}
        {!isDisbursed && !isReducingBalance && (
          <Stack direction="row" spacing={2} sx={{ justifyContent: 'flex-end' }}>
            <Button variant="contained" color="success" onClick={openDisburse}>
              Disburse Loan
            </Button>
          </Stack>
        )}
      </Stack>

      {/* Disburse confirmation dialog */}
      <Dialog open={disburseOpen} onClose={() => setDisburseOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Disburse Loan</DialogTitle>
        <DialogContent>
          <Alert severity="warning" sx={{ mb: 2 }}>
            You are about to disburse the full approved principal of{' '}
            <strong>{formatNaira(loan.principal_amount_minor)}</strong> to {loan.member?.name ?? 'this member'}. A
            disburse posts a financial transaction and creates the repayment schedule. It cannot be undone — any
            correction must go through the financial reversal mechanism.
          </Alert>
          <FieldGrid>
            <DetailField label="Loan number" value={loan.loan_number} />
            <DetailField label="Member" value={loan.member?.name ?? '—'} />
            <DetailField label="Principal to disburse" value={formatNaira(loan.principal_amount_minor)} />
            <DetailField label="Interest method" value={loan.interest_method} />
          </FieldGrid>
          {disburseError && (
            <Alert severity="error" sx={{ mt: 2 }}>
              {disburseError}
            </Alert>
          )}
        </DialogContent>
        <DialogActions sx={{ px: 3, pb: 2 }}>
          <Button onClick={() => setDisburseOpen(false)} disabled={disburseMutation.isPending}>
            Cancel
          </Button>
          <Button
            variant="contained"
            color="success"
            onClick={() => disburseMutation.mutate()}
            disabled={disburseMutation.isPending}
          >
            {disburseMutation.isPending ? 'Disbursing…' : 'Confirm Disbursement'}
          </Button>
        </DialogActions>
      </Dialog>
    </PageContainer>
  )
}