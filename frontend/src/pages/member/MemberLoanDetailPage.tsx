import type { ReactNode } from 'react'
import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
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
import { useParams } from 'react-router-dom'

import { EmptyState } from '../../components/common/EmptyState'
import { ErrorState } from '../../components/common/ErrorState'
import { StatusPill } from '../../components/common/StatusPill'
import { TableSkeleton } from '../../components/common/Skeletons'
import { SimplePageContainer } from '../../components/common/PageContainer'
import { listPaymentMethods } from '../../services/financial'
import { getMemberLoan, submitLoanRepayment } from '../../services/loans'
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

export function MemberLoanDetailPage(): ReactNode {
  const { loanId } = useParams<{ loanId: string }>()
  const queryClient = useQueryClient()

  const [amount, setAmount] = useState('')
  const [methodId, setMethodId] = useState('')
  const [reference, setReference] = useState('')
  const [formError, setFormError] = useState<string | null>(null)
  const [formFieldErrors, setFormFieldErrors] = useState<Record<string, string>>({})
  const [successMessage, setSuccessMessage] = useState<string | null>(null)

  const loanQuery = useQuery({
    queryKey: ['member', 'loan', loanId],
    queryFn: () => getMemberLoan(loanId!),
    enabled: !!loanId,
  })

  const methodsQuery = useQuery({
    queryKey: ['payment-methods'],
    queryFn: () => listPaymentMethods(),
  })

  // Default to the first active payment method until the member picks one.
  const selectedMethodId = methodId || (methodsQuery.data?.[0]?.id ?? '')

  const repaymentMutation = useMutation({
    mutationFn: () =>
      submitLoanRepayment(loanId!, {
        amount_minor: Math.round(Number(amount) * 100),
        payment_method_id: selectedMethodId,
        payment_date: new Date().toISOString(),
        reference_number: reference.trim() || undefined,
      }),
    onSuccess: () => {
      setAmount('')
      setReference('')
      setFormError(null)
      setFormFieldErrors({})
      setSuccessMessage('Your repayment has been recorded and is awaiting verification by the finance team.')
      void queryClient.invalidateQueries({ queryKey: ['member', 'loan', loanId] })
      void queryClient.invalidateQueries({ queryKey: ['member', 'loans'] })
    },
    onError: (err) => {
      setSuccessMessage(null)
      const apiError = err as { fieldErrors?: Record<string, string[]>; message?: string }
      if (apiError.fieldErrors) {
        const mapped: Record<string, string> = {}
        for (const [field, messages] of Object.entries(apiError.fieldErrors)) {
          mapped[field] = messages[0] ?? 'Invalid value.'
        }
        setFormFieldErrors(mapped)
        setFormError(getErrorMessage(err, 'Failed to submit the repayment.'))
      } else {
        setFormError(getErrorMessage(err, 'Failed to submit the repayment.'))
      }
    },
  })

  const outstanding = loanQuery.data?.obligations?.total_outstanding_minor ?? 0
  const canRepay = loanQuery.data?.status === 'disbursed' && outstanding > 0

  const handleSubmit = (): void => {
    setFormError(null)
    setFormFieldErrors({})
    setSuccessMessage(null)

    const parsed = Number(amount)
    if (!amount.trim() || Number.isNaN(parsed) || parsed <= 0) {
      setFormFieldErrors({ amount: 'Enter a valid repayment amount.' })
      return
    }
    const minor = Math.round(parsed * 100)
    if (minor > outstanding) {
      setFormFieldErrors({
        amount: `This exceeds your outstanding balance of ${formatNaira(outstanding)}.`,
      })
      return
    }
    if (!selectedMethodId) {
      setFormFieldErrors({ method: 'Choose a payment method.' })
      return
    }
    repaymentMutation.mutate()
  }

  let content: ReactNode
  if (loanQuery.isPending || (methodsQuery.isPending && canRepay)) {
    content = <TableSkeleton rows={6} columns={5} />
  } else if (loanQuery.isError) {
    content = <ErrorState message={getErrorMessage(loanQuery.error, 'Failed to load the loan.')} onRetry={() => void loanQuery.refetch()} />
  } else if (!loanQuery.data) {
    content = <EmptyState title="Loan not found" description="This loan could not be loaded." />
  } else {
    const loan = loanQuery.data
    const installments = loan.installments ?? []
    const nextDue = loan.obligations?.next_due_installment ?? null
    content = (
      <Stack spacing={2.5}>
        {/* Overview */}
        <DetailSection title="Loan">
          {loan.status === 'pending_disbursement' ? (
            <Alert severity="info" sx={{ mb: 2 }}>
              This loan has been approved and is awaiting disbursement by the cooperative.
            </Alert>
          ) : (
            <Alert severity={loan.status === 'completed' ? 'success' : 'info'} sx={{ mb: 2 }}>
              {loan.status === 'completed'
                ? 'This loan has been fully repaid.'
                : `Outstanding balance: ${formatNaira(outstanding)}.`}
            </Alert>
          )}
          <FieldGrid>
            <DetailField label="Status" value={<StatusPill status={loan.status} />} />
            <DetailField label="Loan number" value={loan.loan_number} />
            <DetailField label="Principal" value={formatNaira(loan.principal_amount_minor)} />
            <DetailField label="Interest rate" value={formatBasisPoints(loan.interest_rate_basis_points)} />
            <DetailField label="Interest method" value={loan.interest_method} />
            <DetailField label="Repayment months" value={loan.repayment_months} />
            <DetailField label="Total interest" value={formatNaira(loan.interest_amount_minor)} />
            <DetailField label="Total payable" value={formatNaira(loan.total_payable_minor)} />
            <DetailField
              label="Start date"
              value={loan.start_date ? formatDate(loan.start_date) : '—'}
            />
            <DetailField
              label="Maturity date"
              value={loan.maturity_date ? formatDate(loan.maturity_date) : '—'}
            />
            {loan.disbursement ? (
              <>
                <DetailField label="Disbursed amount" value={formatNaira(loan.disbursement.amount_minor)} />
                <DetailField
                  label="Disbursed at"
                  value={loan.disbursement.disbursed_at ? formatDateTime(loan.disbursement.disbursed_at) : '—'}
                />
              </>
            ) : null}
          </FieldGrid>
        </DetailSection>

        {/* Obligations */}
        {loan.obligations ? (
          <DetailSection title="Obligations">
            <FieldGrid>
              <DetailField label="Total obligation" value={formatNaira(loan.obligations.total_obligation_minor)} />
              <DetailField label="Total paid" value={formatNaira(loan.obligations.total_paid_minor)} />
              <DetailField label="Total outstanding" value={formatNaira(loan.obligations.total_outstanding_minor)} />
              <DetailField label="Principal outstanding" value={formatNaira(loan.obligations.principal_outstanding_minor)} />
              <DetailField label="Interest outstanding" value={formatNaira(loan.obligations.interest_outstanding_minor)} />
              <DetailField
                label="Next due"
                value={
                  nextDue
                    ? `Installment ${nextDue.installment_number} • ${formatNaira(nextDue.outstanding_minor)} due ${formatDate(nextDue.due_date)}`
                    : '—'
                }
              />
            </FieldGrid>
          </DetailSection>
        ) : null}

        {/* Schedule */}
        <DetailSection title="Repayment Schedule">
          {installments.length === 0 ? (
            <Typography variant="body2" color="text.secondary">
              The repayment schedule is generated at disbursement.
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

        {/* Repayment */}
        {canRepay ? (
          <DetailSection title="Make a Repayment">
            <Alert severity="info" sx={{ mb: 2 }}>
              Payments are applied to your oldest outstanding installment, interest first. Repayments are
              recorded as pending and become effective after the finance team verifies them.
            </Alert>
            {successMessage && (
              <Alert severity="success" sx={{ mb: 2 }}>
                {successMessage}
              </Alert>
            )}
            {formError && (
              <Alert severity="error" sx={{ mb: 2 }}>
                {formError}
              </Alert>
            )}
            <FieldGrid>
              <TextField
                label="Amount (₦)"
                value={amount}
                onChange={(event) => setAmount(event.target.value)}
                error={Boolean(formFieldErrors.amount)}
                helperText={formFieldErrors.amount ?? `Outstanding balance is ${formatNaira(outstanding)}.`}
                fullWidth
              />
              <TextField
                select
                label="Payment method"
                value={selectedMethodId}
                onChange={(event) => setMethodId(event.target.value)}
                error={Boolean(formFieldErrors.method)}
                helperText={formFieldErrors.method ?? 'How will you pay?'}
                fullWidth
              >
                {(methodsQuery.data ?? []).map((method) => (
                  <MenuItem key={method.id} value={method.id}>
                    {method.name}
                  </MenuItem>
                ))}
              </TextField>
              <TextField
                label="Reference number (optional)"
                value={reference}
                onChange={(event) => setReference(event.target.value)}
                helperText="Bank transfer reference, receipt number, etc."
                fullWidth
              />
            </FieldGrid>
            <Box sx={{ mt: 2, display: 'flex', justifyContent: 'flex-end' }}>
              <Button variant="contained" onClick={handleSubmit} disabled={repaymentMutation.isPending}>
                {repaymentMutation.isPending ? 'Submitting…' : 'Submit Repayment'}
              </Button>
            </Box>
          </DetailSection>
        ) : null}
      </Stack>
    )
  }

  return (
    <SimplePageContainer title="Loan" subtitle="Loan summary and repayment" backTo="/member/loans">
      {content}
    </SimplePageContainer>
  )
}

export default MemberLoanDetailPage