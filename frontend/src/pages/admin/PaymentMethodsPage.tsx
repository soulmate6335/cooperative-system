import type { ReactNode } from 'react'
import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Checkbox from '@mui/material/Checkbox'
import Dialog from '@mui/material/Dialog'
import DialogActions from '@mui/material/DialogActions'
import DialogContent from '@mui/material/DialogContent'
import DialogTitle from '@mui/material/DialogTitle'
import FormControlLabel from '@mui/material/FormControlLabel'
import Stack from '@mui/material/Stack'
import Table from '@mui/material/Table'
import TableBody from '@mui/material/TableBody'
import TableCell from '@mui/material/TableCell'
import TableContainer from '@mui/material/TableContainer'
import TableHead from '@mui/material/TableHead'
import TableRow from '@mui/material/TableRow'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import AddIcon from '@mui/icons-material/Add'
import PaymentsIcon from '@mui/icons-material/Payments'

import { PageContainer } from '../../components/common/PageContainer'
import { EmptyState } from '../../components/common/EmptyState'
import { ErrorState } from '../../components/common/ErrorState'
import { TableSkeleton } from '../../components/common/Skeletons'
import { StatusPill } from '../../components/common/StatusPill'
import { createPaymentMethod, listPaymentMethods } from '../../services/financial'
import { getErrorMessage } from '../../utils/errors'

export function PaymentMethodsPage(): ReactNode {
  const queryClient = useQueryClient()
  const [dialogOpen, setDialogOpen] = useState(false)
  const [code, setCode] = useState('')
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [isActive, setIsActive] = useState(true)
  const [displayOrder, setDisplayOrder] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  const { data: methods, isLoading, error: queryError, refetch } = useQuery({
    queryKey: ['payment-methods'],
    queryFn: () => listPaymentMethods(),
  })

  const createMutation = useMutation({
    mutationFn: () =>
      createPaymentMethod({
        code: code.trim(),
        name: name.trim(),
        description: description || null,
        is_active: isActive,
        display_order: displayOrder ? Number(displayOrder) : undefined,
      }),
    onSuccess: () => {
      setDialogOpen(false)
      setCode('')
      setName('')
      setDescription('')
      setIsActive(true)
      setDisplayOrder('')
      setError(null)
      setSuccess('Payment method created successfully.')
      void queryClient.invalidateQueries({ queryKey: ['payment-methods'] })
    },
    onError: (err) => setError(getErrorMessage(err, 'Failed to create the payment method.')),
  })

  const openDialog = (): void => {
    setCode('')
    setName('')
    setDescription('')
    setIsActive(true)
    setDisplayOrder('')
    setError(null)
    setDialogOpen(true)
  }

  if (isLoading) {
    return (
      <PageContainer title="Payment Methods" subtitle="Manage how members can pay">
        <TableSkeleton rows={5} columns={4} />
      </PageContainer>
    )
  }

  if (queryError) {
    return (
      <PageContainer title="Payment Methods" subtitle="Manage how members can pay">
        <ErrorState message={getErrorMessage(queryError, 'Failed to load payment methods.')} onRetry={() => refetch()} />
      </PageContainer>
    )
  }

  const methodList = methods ?? []

  return (
    <PageContainer
      title="Payment Methods"
      subtitle="Manage how members can pay"
      actions={
        <Button variant="contained" startIcon={<AddIcon />} onClick={openDialog} sx={{ textTransform: 'none' }}>
          New method
        </Button>
      }
    >
      {success ? (
        <Typography variant="body2" color="success.main" sx={{ mb: 2 }}>
          {success}
        </Typography>
      ) : null}

      {methodList.length === 0 ? (
        <EmptyState
          icon={PaymentsIcon}
          title="No payment methods yet"
          description="Add payment methods such as bank transfer or mobile money so members know how to pay."
        />
      ) : (
        <Card variant="outlined">
          <CardContent>
            <TableContainer>
              <Table>
                <TableHead>
                  <TableRow>
                    <TableCell>Code</TableCell>
                    <TableCell>Name</TableCell>
                    <TableCell>Description</TableCell>
                    <TableCell align="right">Display order</TableCell>
                    <TableCell>Status</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {methodList.map((method) => (
                    <TableRow key={method.id} hover>
                      <TableCell>
                        <Typography variant="body2" sx={{ fontWeight: 600 }}>
                          {method.code}
                        </Typography>
                      </TableCell>
                      <TableCell>
                        <Typography variant="body2">{method.name}</Typography>
                      </TableCell>
                      <TableCell>
                        <Typography variant="body2" color="text.secondary">
                          {method.description ?? '—'}
                        </Typography>
                      </TableCell>
                      <TableCell align="right">
                        <Typography variant="body2">{method.display_order}</Typography>
                      </TableCell>
                      <TableCell>
                        <StatusPill status={method.is_active ? 'active' : 'inactive'} />
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          </CardContent>
        </Card>
      )}

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>New Payment Method</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            {error ? (
              <Typography variant="body2" color="error">
                {error}
              </Typography>
            ) : null}
            <TextField label="Code" value={code} onChange={(e) => setCode(e.target.value)} fullWidth size="small" placeholder="e.g. bank_transfer" />
            <TextField label="Name" value={name} onChange={(e) => setName(e.target.value)} fullWidth size="small" placeholder="e.g. Bank Transfer" />
            <TextField
              label="Description"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              fullWidth
              multiline
              minRows={2}
              size="small"
            />
            <TextField
              label="Display order"
              type="number"
              value={displayOrder}
              onChange={(e) => setDisplayOrder(e.target.value)}
              fullWidth
              size="small"
            />
            <FormControlLabel
              control={<Checkbox checked={isActive} onChange={(e) => setIsActive(e.target.checked)} />}
              label="Active (available for payments)"
            />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDialogOpen(false)} disabled={createMutation.isPending}>
            Cancel
          </Button>
          <Button
            variant="contained"
            onClick={() => createMutation.mutate()}
            disabled={createMutation.isPending || !code.trim() || !name.trim()}
          >
            {createMutation.isPending ? 'Creating...' : 'Create method'}
          </Button>
        </DialogActions>
      </Dialog>
    </PageContainer>
  )
}