import type { ReactNode } from 'react'
import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import Alert from '@mui/material/Alert'
import Autocomplete from '@mui/material/Autocomplete'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import CircularProgress from '@mui/material/CircularProgress'
import Divider from '@mui/material/Divider'
import Stack from '@mui/material/Stack'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import CheckCircleIcon from '@mui/icons-material/CheckCircle'
import ErrorOutlineOutlinedIcon from '@mui/icons-material/ErrorOutlineOutlined'
import FactCheckIcon from '@mui/icons-material/FactCheck'
import GavelIcon from '@mui/icons-material/Gavel'

import { ConfirmDialog } from '../../components/common/ConfirmDialog'
import { EmptyState } from '../../components/common/EmptyState'
import { ErrorState } from '../../components/common/ErrorState'
import { PageContainer } from '../../components/common/PageContainer'
import { StatusDetail, StatusPill } from '../../components/common/StatusPill'
import { CardSkeleton } from '../../components/common/Skeletons'
import {
  decideEligibility,
  fetchEligibilityAssessment,
  listAdminLoanProducts,
  listEligibilityMembers,
} from '../../services/loans'
import type { EligibilityAssessment, EligibilityMemberSummary, LoanProduct } from '../../types'
import { formatBasisPoints, formatDate, formatDateTime, formatNaira } from '../../utils/format'
import { getErrorMessage } from '../../utils/errors'

const INTEREST_METHOD_LABELS: Record<LoanProduct['interest_method'], string> = {
  flat: 'Flat rate',
  reducing_balance: 'Reducing balance',
}

const DECISION_LABELS: Record<string, string> = {
  pending: 'Pending review',
  eligible: 'Eligible',
  ineligible: 'Ineligible',
}

export function AdminLoanEligibilityPage(): ReactNode {
  const queryClient = useQueryClient()
  const [memberSearch, setMemberSearch] = useState('')
  const [selectedMember, setSelectedMember] = useState<EligibilityMemberSummary | null>(null)
  const [selectedProduct, setSelectedProduct] = useState<LoanProduct | null>(null)
  const [dialogAction, setDialogAction] = useState<'eligible' | 'ineligible' | null>(null)
  const [dialogReason, setDialogReason] = useState('')
  const [dialogReasonError, setDialogReasonError] = useState(false)
  const [notice, setNotice] = useState<{ severity: 'success' | 'error'; text: string } | null>(null)

  const membersQuery = useQuery({
    queryKey: ['admin-eligibility-members', memberSearch],
    queryFn: () => listEligibilityMembers({ search: memberSearch || undefined, per_page: 50 }),
    placeholderData: (previous) => previous,
  })

  const productsQuery = useQuery({
    queryKey: ['admin-loan-products', 'eligibility'],
    queryFn: () => listAdminLoanProducts({ per_page: 100 }),
    placeholderData: (previous) => previous,
  })

  const assessmentQuery = useQuery({
    queryKey: ['admin-eligibility-assessment', selectedMember?.id, selectedProduct?.id],
    queryFn: () => fetchEligibilityAssessment(selectedMember!.id, selectedProduct!.id),
    enabled: Boolean(selectedMember && selectedProduct),
  })

  const decideMutation = useMutation({
    mutationFn: decideEligibility,
    onSuccess: (result) => {
      void queryClient.invalidateQueries({
        queryKey: ['admin-eligibility-assessment', selectedMember?.id, selectedProduct?.id],
      })
      setDialogAction(null)
      setDialogReason('')
      setDialogReasonError(false)
      const status = result.admin_decision.status
      setNotice({
        severity: 'success',
        text:
          status === 'eligible'
            ? `Eligibility granted for ${selectedMember?.name ?? selectedMember?.member_number ?? 'the member'}.`
            : 'Eligibility denied. The member cannot apply for this product.',
      })
    },
    onError: (error) => {
      setNotice({ severity: 'error', text: getErrorMessage(error, 'The eligibility decision could not be recorded.') })
    },
  })

  const openDialog = (action: 'eligible' | 'ineligible'): void => {
    setDialogReason('')
    setDialogReasonError(false)
    setDialogAction(action)
  }

  const confirmDecision = (): void => {
    if (!dialogAction || !selectedMember || !selectedProduct) {
      return
    }

    const factors = assessmentQuery.data?.factors
    const overrideNeedsReason = dialogAction === 'eligible' && factors?.eligible === false
    if (overrideNeedsReason && dialogReason.trim() === '') {
      setDialogReasonError(true)
      return
    }

    decideMutation.mutate({
      member_id: selectedMember.id,
      loan_product_id: selectedProduct.id,
      status: dialogAction,
      reason: dialogReason.trim() === '' ? null : dialogReason.trim(),
    })
  }

  const assessment = assessmentQuery.data

  return (
    <PageContainer title="Loan Eligibility" subtitle="Review member eligibility and grant or deny the right to apply">
      <Stack spacing={3}>
        {notice ? <Alert severity={notice.severity}>{notice.text}</Alert> : null}

        <Card variant="outlined">
          <CardContent>
            <Typography variant="h6" gutterBottom>
              Find a member and loan product
            </Typography>
            <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
              The system calculates eligibility factors, but an administrator is the authority who decides whether a
              member may apply.
            </Typography>
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
              <Autocomplete<EligibilityMemberSummary>
                fullWidth
                options={membersQuery.data?.data ?? []}
                value={selectedMember}
                loading={membersQuery.isFetching}
                loadingText="Loading members…"
                noOptionsText="No members found"
                getOptionLabel={(option) =>
                  option.name ? `${option.name} (${option.member_number})` : option.member_number
                }
                isOptionEqualToValue={(option, value) => option.id === value.id}
                onInputChange={(_, value) => setMemberSearch(value)}
                onChange={(_, value) => setSelectedMember(value)}
                renderInput={(params) => (
                  <TextField {...params} label="Member" placeholder="Search by name or member number" />
                )}
              />
              <Autocomplete<LoanProduct>
                fullWidth
                options={(productsQuery.data?.data ?? []).filter((product) => product.status === 'active')}
                value={selectedProduct}
                loading={productsQuery.isLoading}
                loadingText="Loading products…"
                noOptionsText="No active loan products"
                getOptionLabel={(option) => option.name}
                isOptionEqualToValue={(option, value) => option.id === value.id}
                onChange={(_, value) => setSelectedProduct(value)}
                renderInput={(params) => (
                  <TextField {...params} label="Loan product" placeholder="Select a loan product" />
                )}
              />
            </Stack>
          </CardContent>
        </Card>

        {productsQuery.isLoading ? <CardSkeleton /> : null}
        {productsQuery.isError ? (
          <ErrorState
            message={getErrorMessage(productsQuery.error, 'Failed to load loan products.')}
            onRetry={() => void productsQuery.refetch()}
          />
        ) : null}
        {!productsQuery.isLoading && !productsQuery.isError && (productsQuery.data?.data.length ?? 0) === 0 ? (
          <EmptyState
            icon={GavelIcon}
            title="No loan products"
            description="Create an active loan product before reviewing eligibility."
          />
        ) : null}

        {!selectedMember || !selectedProduct ? (
          <EmptyState
            icon={FactCheckIcon}
            title="Select a member and a product"
            description="Choose a member and an active loan product to see the system assessment and current eligibility decision."
          />
        ) : assessmentQuery.isPending ? (
          <Card variant="outlined">
            <CardContent>
              <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}>
                <CircularProgress size={28} />
              </Box>
            </CardContent>
          </Card>
        ) : assessmentQuery.isError ? (
          <ErrorState
            message={getErrorMessage(assessmentQuery.error, 'We could not load the eligibility assessment.')}
            onRetry={() => void assessmentQuery.refetch()}
          />
        ) : assessment ? (
          <AssessmentPanel
            assessment={assessment}
            decidePending={decideMutation.isPending}
            onMarkEligible={() => openDialog('eligible')}
            onMarkIneligible={() => openDialog('ineligible')}
          />
        ) : null}
      </Stack>

      <ConfirmDialog
        open={dialogAction !== null}
        title={dialogAction === 'ineligible' ? 'Mark member ineligible' : 'Mark member eligible'}
        message={
          <Stack spacing={1.5}>
            <Typography variant="body2">
              {dialogAction === 'ineligible'
                ? 'This will prevent the member from applying for this loan product.'
                : assessment?.factors.eligible === false
                  ? 'The system assessment is not eligible. Marking the member eligible records an explicit administrative override. A reason is required.'
                  : 'This records the administrative approval that allows the member to apply for this loan product.'}
            </Typography>
            <TextField
              autoFocus
              label="Reason"
              multiline
              minRows={2}
              required={dialogAction === 'eligible' && assessment?.factors.eligible === false}
              value={dialogReason}
              onChange={(event) => {
                setDialogReason(event.target.value)
                setDialogReasonError(false)
              }}
              error={dialogReasonError}
              helperText={
                dialogReasonError
                  ? 'A reason is required when overriding the system assessment.'
                  : dialogAction === 'eligible' && assessment?.factors.eligible === false
                    ? 'Explain the administrative override decision.'
                    : 'Optional notes recorded with the decision.'
              }
              fullWidth
            />
          </Stack>
        }
        confirmLabel={dialogAction === 'ineligible' ? 'Mark ineligible' : 'Mark eligible'}
        danger={dialogAction === 'ineligible'}
        loading={decideMutation.isPending}
        onConfirm={confirmDecision}
        onClose={() => {
          if (!decideMutation.isPending) {
            setDialogAction(null)
          }
        }}
      />
    </PageContainer>
  )
}

function AssessmentPanel({
  assessment,
  decidePending,
  onMarkEligible,
  onMarkIneligible,
}: {
  assessment: EligibilityAssessment
  decidePending: boolean
  onMarkEligible: () => void
  onMarkIneligible: () => void
}): ReactNode {
  const { member, product, factors } = assessment
  const decision = factors.admin_decision

  return (
    <Stack spacing={3}>
      <Card variant="outlined">
        <CardContent>
          <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'flex-start' }} spacing={2}>
            <Box>
              <Typography variant="h6">{member.name ?? member.member_number}</Typography>
              <Typography variant="body2" color="text.secondary">
                {member.member_number}
              </Typography>
            </Box>
            <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
              <Button variant="outlined" color="success" onClick={onMarkEligible} disabled={decidePending} startIcon={<CheckCircleIcon />}>
                Mark eligible
              </Button>
              <Button variant="outlined" color="error" onClick={onMarkIneligible} disabled={decidePending} startIcon={<ErrorOutlineOutlinedIcon />}>
                Mark ineligible
              </Button>
            </Stack>
          </Stack>
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ mt: 2 }}>
            <StatusDetail label="Membership status" value={member.status} />
            <StatusDetail label="Membership type" value={member.membership_type} />
            <StatusDetail label="Joined" value={formatDate(member.joined_at)} />
            <StatusDetail label="Current eligibility decision" value={DECISION_LABELS[decision.status] ?? decision.status} />
          </Stack>
        </CardContent>
      </Card>

      <Card variant="outlined">
        <CardContent>
          <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'flex-start' }} spacing={2}>
            <Box>
              <Typography variant="h6">{product.name}</Typography>
              {product.description ? (
                <Typography variant="body2" color="text.secondary">
                  {product.description}
                </Typography>
              ) : null}
            </Box>
            <StatusPill status={product.status} />
          </Stack>
          <Divider sx={{ my: 2 }} />
          <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', sm: '1fr 1fr' }, gap: 1.5 }}>
            <TermValue label="Interest rate" value={formatBasisPoints(product.interest_rate_basis_points)} />
            <TermValue label="Interest method" value={INTEREST_METHOD_LABELS[product.interest_method]} />
            <TermValue
              label="Repayment term"
              value={product.repayment_months === 1 ? '1 month' : `${product.repayment_months} months`}
            />
            <TermValue label="Required guarantors" value={String(product.required_guarantors)} />
            <TermValue
              label="Minimum membership"
              value={
                product.minimum_membership_months === 1 ? '1 month' : `${product.minimum_membership_months} months`
              }
            />
            <TermValue
              label="Loan amount"
              value={
                product.maximum_amount_minor === null
                  ? `From ${formatNaira(product.minimum_amount_minor)}`
                  : `${formatNaira(product.minimum_amount_minor)} – ${formatNaira(product.maximum_amount_minor)}`
              }
            />
          </Box>
        </CardContent>
      </Card>

      <Card variant="outlined">
        <CardContent>
          <Typography variant="h6" gutterBottom>
            System assessment
          </Typography>
          <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
            Calculated from the member&apos;s current records. This is informational and never overridden.
          </Typography>
          <Stack spacing={1}>
            <FactorLine
              label="Membership duration"
              value={`${factors.membership_months} of ${factors.minimum_membership_months} months`}
              ok={factors.membership_months >= factors.minimum_membership_months}
            />
            <FactorLine label="Member status" value={factors.member_active ? 'Active' : 'Inactive'} ok={factors.member_active} />
            <FactorLine label="Savings balance" value={formatNaira(factors.savings_balance_minor)} ok={factors.savings_balance_minor > 0} />
            <FactorLine label="Shares balance" value={formatNaira(factors.shares_balance_minor)} ok={factors.shares_balance_minor > 0} />
          </Stack>
          {factors.reasons.length > 0 ? (
            <Alert severity="warning" sx={{ mt: 2 }}>
              <Typography variant="body2" sx={{ fontWeight: 600 }}>
                System reasons
              </Typography>
              <ul style={{ margin: '4px 0 0', paddingInlineStart: 20 }}>
                {factors.reasons.map((reason) => (
                  <li key={reason}>
                    <Typography variant="body2">{reason}</Typography>
                  </li>
                ))}
              </ul>
            </Alert>
          ) : (
            <Alert severity="success" sx={{ mt: 2 }}>
              The system assessment finds no outstanding eligibility reasons.
            </Alert>
          )}
        </CardContent>
      </Card>

      <Card variant="outlined">
        <CardContent>
          <Typography variant="h6" gutterBottom>
            Administrative decision
          </Typography>
          <Stack spacing={1.5}>
            <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
              <Typography variant="body2" sx={{ width: 160 }}>
                Status
              </Typography>
              <StatusPill status={DECISION_LABELS[decision.status] ?? decision.status} />
            </Stack>
            <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
              <Typography variant="body2" sx={{ width: 160 }}>
                Decided by
              </Typography>
              <Typography variant="body2">{decision.decided_by?.name ?? '—'}</Typography>
            </Stack>
            <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
              <Typography variant="body2" sx={{ width: 160 }}>
                Decided at
              </Typography>
              <Typography variant="body2">{formatDateTime(decision.decided_at)}</Typography>
            </Stack>
            <Stack direction="row" spacing={1} sx={{ alignItems: 'flex-start' }}>
              <Typography variant="body2" sx={{ width: 160 }}>
                Reason
              </Typography>
              <Typography variant="body2" color="text.secondary">
                {decision.reason ?? 'No reason recorded.'}
              </Typography>
            </Stack>
          </Stack>
        </CardContent>
      </Card>
    </Stack>
  )
}

function FactorLine({ label, value, ok }: { label: string; value: ReactNode; ok: boolean }): ReactNode {
  return (
    <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
      {ok ? <CheckCircleIcon color="success" fontSize="small" /> : <ErrorOutlineOutlinedIcon color="error" fontSize="small" />}
      <Typography variant="body2" sx={{ width: 200 }}>
        {label}
      </Typography>
      <Typography variant="body2" color="text.secondary">
        {value}
      </Typography>
    </Stack>
  )
}

function TermValue({ label, value }: { label: string; value: ReactNode }): ReactNode {
  return (
    <Box>
      <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>
        {label}
      </Typography>
      <Typography variant="body2" sx={{ fontWeight: 600 }}>
        {value}
      </Typography>
    </Box>
  )
}