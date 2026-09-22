import type { FormEvent, ReactNode } from 'react'
import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Grid from '@mui/material/Grid'
import Stack from '@mui/material/Stack'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import ReceiptLongIcon from '@mui/icons-material/ReceiptLong'
import VerifiedIcon from '@mui/icons-material/Verified'

import { PageContainer } from '../../components/common/PageContainer'
import { StatusPill } from '../../components/common/StatusPill'
import { issueReceipt, verifyPayment } from '../../services/financial'
import type { Payment } from '../../types'
import { formatDate, formatNaira } from '../../utils/format'
import { getErrorMessage } from '../../utils/errors'

export function ReceiptsPage(): ReactNode {
  const [paymentId, setPaymentId] = useState('')
  const [payment, setPayment] = useState<Payment | null>(null)
  const [error, setError] = useState<string | null>(null)

  const verifyMutation = useMutation({
    mutationFn: () => verifyPayment(paymentId),
    onSuccess: (verified) => {
      setPayment(verified)
      setError(null)
    },
    onError: (err) => setError(getErrorMessage(err, 'Failed to verify the payment.')),
  })

  const receiptMutation = useMutation({
    mutationFn: () => issueReceipt(paymentId),
    onSuccess: (withReceipt) => {
      setPayment(withReceipt)
      setError(null)
    },
    onError: (err) => setError(getErrorMessage(err, 'Failed to issue the receipt.')),
  })

  const onVerify = (event: FormEvent<HTMLFormElement>): void => {
    event.preventDefault()
    setPayment(null)
    setError(null)
    verifyMutation.mutate()
  }

  const alreadyReceipted = payment?.status === 'receipted' || payment?.status === 'verified_with_receipt'

  return (
    <PageContainer title="Verify Payments & Receipts" subtitle="Verify a recorded payment and issue its receipt">
      <Grid container spacing={3}>
        <Grid size={{ xs: 12, md: 6 }}>
          <Card variant="outlined">
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Find a payment
              </Typography>
              <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                Enter a payment ID (UUID) to verify the payment and, once verified, issue its receipt.
              </Typography>
              <form onSubmit={onVerify}>
                <Stack spacing={2}>
                  <TextField
                    label="Payment ID"
                    placeholder="UUID of the recorded payment"
                    value={paymentId}
                    onChange={(e) => setPaymentId(e.target.value)}
                    fullWidth
                    size="small"
                    required
                  />
                  {error ? <Alert severity="error">{error}</Alert> : null}
                  <Box>
                    <Button
                      type="submit"
                      variant="outlined"
                      startIcon={<VerifiedIcon />}
                      disabled={!paymentId.trim() || verifyMutation.isPending}
                    >
                      {verifyMutation.isPending ? 'Verifying...' : 'Verify payment'}
                    </Button>
                  </Box>
                </Stack>
              </form>
            </CardContent>
          </Card>
        </Grid>

        <Grid size={{ xs: 12, md: 6 }}>
          <Card variant="outlined">
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Payment status
              </Typography>
              {!payment ? (
                <Typography variant="body2" color="text.secondary">
                  Nothing verified yet — verify a payment on the left to see its details.
                </Typography>
              ) : (
                <Stack spacing={2}>
                  <Stack direction="row" spacing={2} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
                    <StatusPill status={payment.status} />
                    <Typography variant="body2" color="text.secondary">
                      {payment.reference_number}
                    </Typography>
                  </Stack>
                  <Box>
                    <Typography variant="body2" color="text.secondary">Amount</Typography>
                    <Typography variant="h6" sx={{ fontWeight: 700 }}>
                      {formatNaira(payment.amount_minor)}
                    </Typography>
                  </Box>
                  <Box>
                    <Typography variant="body2" color="text.secondary">Purpose</Typography>
                    <Typography variant="body1" sx={{ fontWeight: 500 }}>
                      {payment.purpose.charAt(0).toUpperCase() + payment.purpose.slice(1)}
                    </Typography>
                  </Box>
                  <Box>
                    <Typography variant="body2" color="text.secondary">Payment date</Typography>
                    <Typography variant="body1" sx={{ fontWeight: 500 }}>
                      {payment.payment_date ? formatDate(payment.payment_date) : '—'}
                    </Typography>
                  </Box>
                  {payment.verified_at ? (
                    <Box>
                      <Typography variant="body2" color="text.secondary">Verified at</Typography>
                      <Typography variant="body1" sx={{ fontWeight: 500 }}>
                        {formatDate(payment.verified_at)}
                      </Typography>
                    </Box>
                  ) : null}
                  <Box>
                    <Button
                      variant="contained"
                      startIcon={<ReceiptLongIcon />}
                      onClick={() => receiptMutation.mutate()}
                      disabled={alreadyReceipted || receiptMutation.isPending}
                    >
                      {alreadyReceipted
                        ? 'Receipt issued'
                        : receiptMutation.isPending
                          ? 'Issuing...'
                          : 'Issue receipt'}
                    </Button>
                  </Box>
                </Stack>
              )}
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </PageContainer>
  )
}