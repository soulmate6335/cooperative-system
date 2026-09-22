import type { FormEvent, ReactNode } from 'react'
import { useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Grid from '@mui/material/Grid'
import MenuItem from '@mui/material/MenuItem'
import Stack from '@mui/material/Stack'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import PaymentsIcon from '@mui/icons-material/Payments'
import { Link as RouterLink } from 'react-router-dom'

import { PageContainer } from '../../components/common/PageContainer'
import { ErrorState } from '../../components/common/ErrorState'
import { listPaymentMethods, recordPayment, type PaymentPurpose } from '../../services/financial'
import { getErrorMessage } from '../../utils/errors'

const PURPOSES: PaymentPurpose[] = ['contribution', 'savings', 'shares']

export function PaymentsPage(): ReactNode {
  const [memberId, setMemberId] = useState('')
  const [paymentMethodId, setPaymentMethodId] = useState('')
  const [amountNaira, setAmountNaira] = useState('')
  const [paymentDate, setPaymentDate] = useState('')
  const [referenceNumber, setReferenceNumber] = useState('')
  const [purpose, setPurpose] = useState<PaymentPurpose>('contribution')
  const [notes, setNotes] = useState('')
  const [success, setSuccess] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const methodsQuery = useQuery({
    queryKey: ['payment-methods', 'active'],
    queryFn: () => listPaymentMethods({ active_only: true }),
  })

  const recordMutation = useMutation({
    mutationFn: () =>
      recordPayment({
        member_id: memberId,
        payment_method_id: paymentMethodId,
        amount_minor: Math.round(Number(amountNaira) * 100),
        payment_date: paymentDate || null,
        reference_number: referenceNumber,
        purpose,
        notes: notes || null,
      }),
    onSuccess: (payment) => {
      setMemberId('')
      setPaymentMethodId('')
      setAmountNaira('')
      setPaymentDate('')
      setReferenceNumber('')
      setNotes('')
      setSuccess(`Payment recorded successfully. Reference: ${payment.reference_number}`)
      setError(null)
    },
    onError: (err) => setError(getErrorMessage(err, 'Failed to record the payment.')),
  })

  const submit = (event: FormEvent<HTMLFormElement>): void => {
    event.preventDefault()
    setSuccess(null)
    setError(null)
    recordMutation.mutate()
  }

  const methods = methodsQuery.data ?? []
  const canSubmit = Boolean(memberId.trim()) && Boolean(paymentMethodId) && Number(amountNaira) > 0 && Boolean(referenceNumber.trim())

  return (
    <PageContainer title="Record Payment" subtitle="Record a member contribution, savings, or shares payment">
      {methodsQuery.error ? (
        <ErrorState message={getErrorMessage(methodsQuery.error, 'Failed to load payment methods.')} onRetry={() => methodsQuery.refetch()} />
      ) : null}

      <Grid container spacing={3}>
        <Grid size={{ xs: 12, md: 8 }}>
          <Card variant="outlined">
            <CardContent>
              <form onSubmit={submit}>
                <Stack spacing={2.5}>
                  {success ? <Alert severity="success">{success}</Alert> : null}
                  {error ? <Alert severity="error">{error}</Alert> : null}

                  <Box>
                    <Typography variant="overline" color="text.secondary">
                      Payment details
                    </Typography>
                    <Stack spacing={2} sx={{ mt: 0.5 }}>
                      <TextField
                        label="Member ID"
                        placeholder="UUID of the paying member"
                        value={memberId}
                        onChange={(e) => setMemberId(e.target.value)}
                        fullWidth
                        size="small"
                        required
                      />
                      <Grid container spacing={2}>
                        <Grid size={{ xs: 12, sm: 6 }}>
                          <TextField
                            label="Amount (₦)"
                            type="number"
                            inputMode="decimal"
                            value={amountNaira}
                            onChange={(e) => setAmountNaira(e.target.value)}
                            fullWidth
                            size="small"
                            required
                          />
                        </Grid>
                        <Grid size={{ xs: 12, sm: 6 }}>
                          <TextField
                            label="Payment date"
                            type="date"
                            slotProps={{ inputLabel: { shrink: true } }}
                            value={paymentDate}
                            onChange={(e) => setPaymentDate(e.target.value)}
                            fullWidth
                            size="small"
                          />
                        </Grid>
                        <Grid size={{ xs: 12, sm: 6 }}>
                          <TextField
                            label="Reference number"
                            placeholder="Bank transfer reference"
                            value={referenceNumber}
                            onChange={(e) => setReferenceNumber(e.target.value)}
                            fullWidth
                            size="small"
                            required
                          />
                        </Grid>
                        <Grid size={{ xs: 12, sm: 6 }}>
                          <TextField
                            select
                            label="Purpose"
                            value={purpose}
                            onChange={(e) => setPurpose(e.target.value as PaymentPurpose)}
                            fullWidth
                            size="small"
                          >
                            {PURPOSES.map((p) => (
                              <MenuItem key={p} value={p}>
                                {p.charAt(0).toUpperCase() + p.slice(1)}
                              </MenuItem>
                            ))}
                          </TextField>
                        </Grid>
                      </Grid>
                      <TextField
                        label="Notes (optional)"
                        value={notes}
                        onChange={(e) => setNotes(e.target.value)}
                        fullWidth
                        multiline
                        minRows={2}
                        size="small"
                      />
                    </Stack>
                  </Box>

                  <Box>
                    <Typography variant="overline" color="text.secondary">
                      Payment method
                    </Typography>
                    <Stack spacing={1} sx={{ mt: 0.5 }}>
                      {methods.length === 0 ? (
                        <Alert severity="info">
                          No active payment methods available. Add one in{' '}
                          <Button component={RouterLink} to="/admin/payment-methods" size="small" sx={{ textTransform: 'none' }}>
                            Payment Methods
                          </Button>
                          .
                        </Alert>
                      ) : (
                        <TextField
                          select
                          label="Method"
                          value={paymentMethodId}
                          onChange={(e) => setPaymentMethodId(e.target.value)}
                          fullWidth
                          size="small"
                          required
                        >
                          {methods.map((method) => (
                            <MenuItem key={method.id} value={method.id}>
                              {method.name}
                            </MenuItem>
                          ))}
                        </TextField>
                      )}
                    </Stack>
                  </Box>

                  <Box>
                    <Button type="submit" variant="contained" startIcon={<PaymentsIcon />} disabled={!canSubmit || recordMutation.isPending}>
                      {recordMutation.isPending ? 'Recording...' : 'Record payment'}
                    </Button>
                  </Box>
                </Stack>
              </form>
            </CardContent>
          </Card>
        </Grid>

        <Grid size={{ xs: 12, md: 4 }}>
          <Card variant="outlined" sx={{ bgcolor: 'action.hover' }}>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                About payments
              </Typography>
              <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                Recorded payments are posted as financial transactions and can be verified from the Receipts page before
                members receive their receipts.
              </Typography>
              <Typography variant="body2" color="text.secondary">
                Each reference number must be unique.
              </Typography>
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </PageContainer>
  )
}