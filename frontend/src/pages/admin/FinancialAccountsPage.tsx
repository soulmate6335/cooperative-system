import type { FormEvent, ReactNode } from 'react'
import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
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
import AccountBalanceIcon from '@mui/icons-material/AccountBalance'

import { PageContainer } from '../../components/common/PageContainer'
import { createFinancialAccount, type CreateFinancialAccountPayload } from '../../services/financial'
import { getErrorMessage } from '../../utils/errors'

/** Loan accounts are auto-created by disbursement, not through this form. */
type CreateableAccountType = 'contribution' | 'savings' | 'shares'

const ACCOUNT_TYPES: CreateableAccountType[] = ['contribution', 'savings', 'shares']

export function FinancialAccountsPage(): ReactNode {
  const [memberId, setMemberId] = useState('')
  const [accountType, setAccountType] = useState<CreateableAccountType>('contribution')
  const [success, setSuccess] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const createMutation = useMutation({
    mutationFn: () => createFinancialAccount({ member_id: memberId, account_type: accountType } satisfies CreateFinancialAccountPayload),
    onSuccess: (account) => {
      setMemberId('')
      setSuccess(`Account created successfully. Number: ${account.account_number}`)
      setError(null)
    },
    onError: (err) => setError(getErrorMessage(err, 'Failed to create the account.')),
  })

  const submit = (event: FormEvent<HTMLFormElement>): void => {
    event.preventDefault()
    setSuccess(null)
    setError(null)
    createMutation.mutate()
  }

  return (
    <PageContainer title="Financial Accounts" subtitle="Open a contribution, savings, or shares account for a member">
      <Grid container spacing={3}>
        <Grid size={{ xs: 12, md: 7 }}>
          <Card variant="outlined">
            <CardContent>
              <form onSubmit={submit}>
                <Stack spacing={2.5}>
                  {success ? <Alert severity="success">{success}</Alert> : null}
                  {error ? <Alert severity="error">{error}</Alert> : null}
                  <TextField
                    label="Member ID"
                    placeholder="UUID of the member opening the account"
                    value={memberId}
                    onChange={(e) => setMemberId(e.target.value)}
                    fullWidth
                    size="small"
                    required
                  />
                  <TextField
                    select
                    label="Account type"
                    value={accountType}
                    onChange={(e) => setAccountType(e.target.value as CreateableAccountType)}
                    fullWidth
                    size="small"
                  >
                    {ACCOUNT_TYPES.map((type) => (
                      <MenuItem key={type} value={type}>
                        {type.charAt(0).toUpperCase() + type.slice(1)}
                      </MenuItem>
                    ))}
                  </TextField>
                  <Box>
                    <Button type="submit" variant="contained" startIcon={<AccountBalanceIcon />} disabled={!memberId.trim() || createMutation.isPending}>
                      {createMutation.isPending ? 'Creating...' : 'Create account'}
                    </Button>
                  </Box>
                </Stack>
              </form>
            </CardContent>
          </Card>
        </Grid>

        <Grid size={{ xs: 12, md: 5 }}>
          <Card variant="outlined" sx={{ bgcolor: 'action.hover' }}>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Accounts overview
              </Typography>
              <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                Each member can hold one contribution, one savings, and one shares account. Accounts are automatically
                posted to when verified payments are recorded.
              </Typography>
              <Typography variant="body2" color="text.secondary">
                Members can view their own account balances and transaction history from their portal.
              </Typography>
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </PageContainer>
  )
}