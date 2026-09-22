import type { ReactNode } from 'react'
import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Dialog from '@mui/material/Dialog'
import DialogActions from '@mui/material/DialogActions'
import DialogContent from '@mui/material/DialogContent'
import DialogTitle from '@mui/material/DialogTitle'
import Divider from '@mui/material/Divider'
import Grid from '@mui/material/Grid'
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
import AddIcon from '@mui/icons-material/Add'
import EditIcon from '@mui/icons-material/Edit'
import ToggleOffIcon from '@mui/icons-material/ToggleOff'
import ToggleOnIcon from '@mui/icons-material/ToggleOn'

import { PageContainer } from '../../components/common/PageContainer'
import { EmptyState } from '../../components/common/EmptyState'
import { ErrorState } from '../../components/common/ErrorState'
import { TableSkeleton } from '../../components/common/Skeletons'
import { StatusPill } from '../../components/common/StatusPill'
import {
  createLoanProduct,
  listAdminLoanProducts,
  setLoanProductActive,
  updateLoanProduct,
  type SaveLoanProductPayload,
} from '../../services/loans'
import type { LoanProduct } from '../../types'
import { formatBasisPoints, formatNaira } from '../../utils/format'
import { getErrorMessage } from '../../utils/errors'

interface ProductFormState {
  name: string
  description: string
  minimum_membership_months: string
  minimum_amount_minor: string
  maximum_amount_minor: string
  interest_rate_basis_points: string
  interest_method: 'flat' | 'reducing_balance'
  repayment_months: string
  required_guarantors: string
}

const EMPTY_FORM: ProductFormState = {
  name: '',
  description: '',
  minimum_membership_months: '',
  minimum_amount_minor: '',
  maximum_amount_minor: '',
  interest_rate_basis_points: '',
  interest_method: 'flat',
  repayment_months: '',
  required_guarantors: '',
}

function toPayload(form: ProductFormState): SaveLoanProductPayload {
  return {
    name: form.name,
    description: form.description || null,
    minimum_membership_months: Number(form.minimum_membership_months),
    minimum_amount_minor: Math.round(Number(form.minimum_amount_minor) * 100),
    maximum_amount_minor: form.maximum_amount_minor ? Math.round(Number(form.maximum_amount_minor) * 100) : null,
    interest_rate_basis_points: Math.round(Number(form.interest_rate_basis_points) * 100),
    interest_method: form.interest_method,
    repayment_months: Number(form.repayment_months),
    required_guarantors: Number(form.required_guarantors),
  }
}

function productToForm(product: LoanProduct): ProductFormState {
  return {
    name: product.name,
    description: product.description ?? '',
    minimum_membership_months: String(product.minimum_membership_months),
    minimum_amount_minor: String(product.minimum_amount_minor / 100),
    maximum_amount_minor: product.maximum_amount_minor != null ? String(product.maximum_amount_minor / 100) : '',
    interest_rate_basis_points: String(product.interest_rate_basis_points / 100),
    interest_method: product.interest_method,
    repayment_months: String(product.repayment_months),
    required_guarantors: String(product.required_guarantors),
  }
}

export function LoanProductsAdminPage(): ReactNode {
  const queryClient = useQueryClient()
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editing, setEditing] = useState<LoanProduct | null>(null)
  const [form, setForm] = useState<ProductFormState>(EMPTY_FORM)
  const [error, setError] = useState<string | null>(null)

  const { data: result, isLoading, error: queryError, refetch } = useQuery({
    queryKey: ['admin-loan-products'],
    queryFn: () => listAdminLoanProducts({ per_page: 50 }),
  })

  const invalidate = (): void => {
    void queryClient.invalidateQueries({ queryKey: ['admin-loan-products'] })
  }

  const saveMutation = useMutation({
    mutationFn: () =>
      editing ? updateLoanProduct(editing.id, toPayload(form)) : createLoanProduct(toPayload(form)),
    onSuccess: () => {
      setDialogOpen(false)
      setEditing(null)
      setForm(EMPTY_FORM)
      setError(null)
      invalidate()
    },
    onError: (err) => setError(getErrorMessage(err, 'Failed to save the loan product.')),
  })

  const toggleMutation = useMutation({
    mutationFn: ({ id, active }: { id: string; active: boolean }) => setLoanProductActive(id, active),
    onSuccess: invalidate,
    onError: (err) => alert(getErrorMessage(err, 'Failed to update product status.').toString()),
  })

  const openCreate = (): void => {
    setEditing(null)
    setForm(EMPTY_FORM)
    setError(null)
    setDialogOpen(true)
  }

  const openEdit = (product: LoanProduct): void => {
    setEditing(product)
    setForm(productToForm(product))
    setError(null)
    setDialogOpen(true)
  }

  const update = (patch: Partial<ProductFormState>): void => {
    setForm((prev) => ({ ...prev, ...patch }))
  }

  if (isLoading) {
    return (
      <PageContainer title="Loan Products" subtitle="Configure loan products for members">
        <TableSkeleton rows={8} columns={6} />
      </PageContainer>
    )
  }

  if (queryError) {
    return (
      <PageContainer title="Loan Products" subtitle="Configure loan products for members">
        <ErrorState message={getErrorMessage(queryError, 'Failed to load loan products.')} onRetry={() => refetch()} />
      </PageContainer>
    )
  }

  const products = result?.data ?? []

  return (
    <PageContainer
      title="Loan Products"
      subtitle="Configure loan products for members"
      actions={
        <Button variant="contained" startIcon={<AddIcon />} onClick={openCreate} sx={{ textTransform: 'none' }}>
          New product
        </Button>
      }
    >
      {products.length === 0 ? (
        <EmptyState
          icon={AddIcon}
          title="No loan products"
          description="Create your first loan product to let members check eligibility and apply."
        />
      ) : (
        <Card variant="outlined">
          <CardContent>
            <TableContainer>
              <Table>
                <TableHead>
                  <TableRow>
                    <TableCell>Name</TableCell>
                    <TableCell align="right">Min / Max</TableCell>
                    <TableCell align="right">Interest</TableCell>
                    <TableCell align="right">Repayment</TableCell>
                    <TableCell>Method</TableCell>
                    <TableCell>Status</TableCell>
                    <TableCell align="center">Actions</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {products.map((product) => (
                    <TableRow key={product.id} hover>
                      <TableCell>
                        <Typography variant="body2" sx={{ fontWeight: 600 }}>
                          {product.name}
                        </Typography>
                        <Typography variant="caption" color="text.secondary">
                          {product.description || '—'}
                        </Typography>
                      </TableCell>
                      <TableCell align="right">
                        <Typography variant="body2">
                          {formatNaira(product.minimum_amount_minor)}
                          {product.maximum_amount_minor != null ? ` – ${formatNaira(product.maximum_amount_minor)}` : ' – Unlimited'}
                        </Typography>
                      </TableCell>
                      <TableCell align="right">
                        <Typography variant="body2">{formatBasisPoints(product.interest_rate_basis_points)}</Typography>
                      </TableCell>
                      <TableCell align="right">
                        <Typography variant="body2">{product.repayment_months} months</Typography>
                      </TableCell>
                      <TableCell>
                        <Typography variant="body2">{product.interest_method === 'flat' ? 'Flat' : 'Reducing Balance'}</Typography>
                      </TableCell>
                      <TableCell>
                        <StatusPill status={product.status} />
                      </TableCell>
                      <TableCell align="center">
                        <Stack direction="row" spacing={1} sx={{ justifyContent: 'center' }}>
                          <Button size="small" startIcon={<EditIcon />} onClick={() => openEdit(product)}>
                            Edit
                          </Button>
                          {product.status === 'active' ? (
                            <Button
                              size="small"
                              color="inherit"
                              startIcon={<ToggleOffIcon />}
                              onClick={() => toggleMutation.mutate({ id: product.id, active: false })}
                              disabled={toggleMutation.isPending}
                            >
                              Deactivate
                            </Button>
                          ) : (
                            <Button
                              size="small"
                              color="success"
                              startIcon={<ToggleOnIcon />}
                              onClick={() => toggleMutation.mutate({ id: product.id, active: true })}
                              disabled={toggleMutation.isPending}
                            >
                              Activate
                            </Button>
                          )}
                        </Stack>
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
        <DialogTitle>{editing ? `Edit ${editing.name}` : 'New Loan Product'}</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            {error ? (
              <Typography variant="body2" color="error">
                {error}
              </Typography>
            ) : null}
            <TextField label="Name" value={form.name} onChange={(e) => update({ name: e.target.value })} fullWidth size="small" />
            <TextField
              label="Description"
              value={form.description}
              onChange={(e) => update({ description: e.target.value })}
              fullWidth
              multiline
              minRows={2}
              size="small"
            />
            <Divider />
            <Grid container spacing={2}>
              <Grid size={{ xs: 12, sm: 6 }}>
                <TextField
                  label="Minimum amount (₦)"
                  type="number"
                  value={form.minimum_amount_minor}
                  onChange={(e) => update({ minimum_amount_minor: e.target.value })}
                  fullWidth
                  size="small"
                />
              </Grid>
              <Grid size={{ xs: 12, sm: 6 }}>
                <TextField
                  label="Maximum amount (₦, optional)"
                  type="number"
                  value={form.maximum_amount_minor}
                  onChange={(e) => update({ maximum_amount_minor: e.target.value })}
                  fullWidth
                  size="small"
                />
              </Grid>
              <Grid size={{ xs: 12, sm: 6 }}>
                <TextField
                  label="Interest rate (%)"
                  type="number"
                  value={form.interest_rate_basis_points}
                  onChange={(e) => update({ interest_rate_basis_points: e.target.value })}
                  fullWidth
                  size="small"
                />
              </Grid>
              <Grid size={{ xs: 12, sm: 6 }}>
                <TextField
                  label="Minimum membership (months)"
                  type="number"
                  value={form.minimum_membership_months}
                  onChange={(e) => update({ minimum_membership_months: e.target.value })}
                  fullWidth
                  size="small"
                />
              </Grid>
              <Grid size={{ xs: 12, sm: 6 }}>
                <TextField
                  label="Repayment term (months)"
                  type="number"
                  value={form.repayment_months}
                  onChange={(e) => update({ repayment_months: e.target.value })}
                  fullWidth
                  size="small"
                />
              </Grid>
              <Grid size={{ xs: 12, sm: 6 }}>
                <TextField
                  label="Required guarantors"
                  type="number"
                  value={form.required_guarantors}
                  onChange={(e) => update({ required_guarantors: e.target.value })}
                  fullWidth
                  size="small"
                />
              </Grid>
              <Grid size={{ xs: 12 }}>
                <Select
                  label="Interest method"
                  value={form.interest_method}
                  onChange={(e) => update({ interest_method: e.target.value as 'flat' | 'reducing_balance' })}
                  fullWidth
                  size="small"
                >
                  <MenuItem value="flat">Flat</MenuItem>
                  <MenuItem value="reducing_balance">Reducing Balance</MenuItem>
                </Select>
              </Grid>
            </Grid>
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDialogOpen(false)} disabled={saveMutation.isPending}>
            Cancel
          </Button>
          <Button
            variant="contained"
            onClick={() => saveMutation.mutate()}
            disabled={saveMutation.isPending || !form.name.trim() || !form.minimum_amount_minor || !form.repayment_months}
          >
            {saveMutation.isPending ? 'Saving...' : editing ? 'Save changes' : 'Create product'}
          </Button>
        </DialogActions>
      </Dialog>
    </PageContainer>
  )
}