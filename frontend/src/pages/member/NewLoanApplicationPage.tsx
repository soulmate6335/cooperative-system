import type { ReactNode } from 'react'
import { useEffect, useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import CircularProgress from '@mui/material/CircularProgress'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormProvider, useForm, useWatch } from 'react-hook-form'
import { z } from 'zod'

import { FormSelect, type SelectOption } from '../../components/forms/FormSelect'
import { FormTextField } from '../../components/forms/FormTextField'
import { SimplePageContainer } from '../../components/common/PageContainer'
import { EmptyState } from '../../components/common/EmptyState'
import { ErrorState } from '../../components/common/ErrorState'
import { TableSkeleton } from '../../components/common/Skeletons'
import { createLoanApplication, updateLoanApplication, listLoanProducts, fetchEligibility, getMyLoanApplication } from '../../services/loans'
import type { LoanProduct } from '../../types'
import { formatBasisPoints, formatNaira } from '../../utils/format'
import { getErrorMessage } from '../../utils/errors'

const schema = z.object({
  loan_product_id: z.string().min(1, 'Select a loan product.'),
  amount_requested_minor: z.coerce.number().int().positive('Enter a valid amount.'),
  purpose: z.string().min(1, 'Enter the purpose of the loan.'),
})

type FormValues = z.infer<typeof schema>

function WeightedDisplay({ label, value }: { label: string; value: ReactNode }): ReactNode {
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

export function NewLoanApplicationPage(): ReactNode {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [searchParams] = useSearchParams()
  const [serverError, setServerError] = useState<string | null>(null)

  const editId = searchParams.get('edit')
  const isEditing = editId !== null && editId !== ''

  const productsQuery = useQuery({
    queryKey: ['loan-products', 'member'],
    queryFn: listLoanProducts,
  })

  const draftQuery = useQuery({
    queryKey: ['member', 'loan-application', editId],
    queryFn: () => getMyLoanApplication(editId!),
    enabled: isEditing,
  })

  const methods = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      loan_product_id: searchParams.get('product_id') ?? '',
      amount_requested_minor: undefined,
      purpose: '',
    },
  })

  const selectedProductId = useWatch({ control: methods.control, name: 'loan_product_id' })
  const selectedProduct: LoanProduct | undefined = productsQuery.data?.find((product) => product.id === selectedProductId)

  // Pre-fill the form from the existing draft when editing. A non-draft
  // application cannot be edited and is redirected to its own detail page.
  useEffect(() => {
    if (isEditing && draftQuery.data) {
      if (draftQuery.data.status !== 'draft') {
        navigate(`/member/loans/applications/${draftQuery.data.id}`, { replace: true })
        return
      }
      methods.reset({
        loan_product_id: draftQuery.data.loan_product?.id ?? '',
        amount_requested_minor: draftQuery.data.amount_requested_minor,
        purpose: draftQuery.data.purpose,
      })
    }
  }, [isEditing, draftQuery.data, methods, navigate])

  const eligibilityQuery = useQuery({
    queryKey: ['member', 'eligibility', selectedProductId],
    queryFn: () => fetchEligibility(selectedProductId),
    enabled: !!selectedProductId,
  })

  const submitMutation = useMutation({
    mutationFn: (values: FormValues) =>
      isEditing ? updateLoanApplication(editId!, values) : createLoanApplication(values),
    onSuccess: (application) => {
      void queryClient.invalidateQueries({ queryKey: ['member', 'loan-applications'] })
      void queryClient.invalidateQueries({ queryKey: ['member', 'loan-application'] })
      navigate(`/member/loans/applications/${application.id}`)
    },
    onError: (error) => {
      setServerError(getErrorMessage(error, 'Failed to save the loan application.'))
    },
  })

  const options: SelectOption[] =
    productsQuery.data?.map((product) => ({
      value: product.id,
      label: product.name,
    })) ?? []

  const handleSubmit = (values: FormValues): void => {
    // Client-side validation against the selected product's configured range.
    const product = productsQuery.data?.find((candidate) => candidate.id === values.loan_product_id)
    if (!product) {
      setServerError('Select a loan product to continue.')
      return
    }
    const amount = values.amount_requested_minor
    if (amount < product.minimum_amount_minor) {
      methods.setError('amount_requested_minor', {
        message: `The minimum amount for this product is ${formatNaira(product.minimum_amount_minor)}.`,
      })
      setServerError(null)
      return
    }
    if (product.maximum_amount_minor != null && amount > product.maximum_amount_minor) {
      methods.setError('amount_requested_minor', {
        message: `The maximum amount for this product is ${formatNaira(product.maximum_amount_minor)}.`,
      })
      setServerError(null)
      return
    }
    setServerError(null)
    submitMutation.mutate(values)
  }

  const decision = eligibilityQuery.data?.admin_decision

  let content: ReactNode
  if (productsQuery.isPending || (isEditing && draftQuery.isPending)) {
    content = <TableSkeleton rows={4} columns={3} />
  } else if (productsQuery.isError) {
    content = <ErrorState message={getErrorMessage(productsQuery.error, 'Failed to load loan products.')} onRetry={() => void productsQuery.refetch()} />
  } else if (isEditing && draftQuery.isError) {
    content = <ErrorState message={getErrorMessage(draftQuery.error, 'Failed to load the draft application.')} onRetry={() => void draftQuery.refetch()} />
  } else if (productsQuery.data?.length === 0) {
    content = (
      <EmptyState
        title="No loan products available"
        description="There are no active loan products to apply for right now. Check back later."
      />
    )
  } else {
    content = (
      <Card variant="outlined">
        <CardContent>
          <FormProvider {...methods}>
            <form onSubmit={methods.handleSubmit(handleSubmit)}>
              <Stack spacing={2.5}>
                {serverError ? <Alert severity="error">{serverError}</Alert> : null}

                {selectedProduct ? (
                  <Card variant="outlined" sx={{ bgcolor: 'action.hover' }}>
                    <CardContent>
                      <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1 }}>
                        {selectedProduct.name}
                      </Typography>
                      {selectedProduct.description ? (
                        <Typography variant="body2" color="text.secondary" sx={{ mb: 1.5 }}>
                          {selectedProduct.description}
                        </Typography>
                      ) : null}
                      <Stack direction="row" spacing={2} sx={{ flexWrap: 'wrap' }}>
                        <WeightedDisplay
                          label="Amount range"
                          value={`${formatNaira(selectedProduct.minimum_amount_minor)} – ${selectedProduct.maximum_amount_minor != null ? formatNaira(selectedProduct.maximum_amount_minor) : 'Unlimited'}`}
                        />
                        <WeightedDisplay label="Interest rate" value={formatBasisPoints(selectedProduct.interest_rate_basis_points)} />
                        <WeightedDisplay label="Interest method" value={selectedProduct.interest_method} />
                        <WeightedDisplay label="Repayment term" value={`${selectedProduct.repayment_months} months`} />
                        <WeightedDisplay label="Required guarantors" value={selectedProduct.required_guarantors} />
                      </Stack>
                    </CardContent>
                  </Card>
                ) : null}

                {selectedProduct && decision ? (
                  decision.status === 'eligible' ? (
                    <Alert severity="success">
                      <Typography variant="body2" sx={{ fontWeight: 600 }}>
                        You are eligible to apply for this product
                      </Typography>
                      <Typography variant="body2" color="text.secondary">
                        Your eligibility has been approved by an administrator. You can create a draft and submit it.
                      </Typography>
                    </Alert>
                  ) : decision.status === 'pending' ? (
                    <Alert severity="info">
                      <Typography variant="body2" sx={{ fontWeight: 600 }}>
                        Eligibility review pending
                      </Typography>
                      <Typography variant="body2" color="text.secondary">
                        You can save a draft, but submission is blocked until an administrator approves your eligibility
                        for this product.
                      </Typography>
                    </Alert>
                  ) : (
                    <Alert severity="warning">
                      <Typography variant="body2" sx={{ fontWeight: 600 }}>
                        Eligibility not approved
                      </Typography>
                      <Typography variant="body2" color="text.secondary">
                        An administrator has not approved your eligibility for this product, so the draft cannot be
                        submitted. {decision.reason ? <>Reason: {decision.reason}</> : 'Contact the cooperative office for more information.'}
                      </Typography>
                    </Alert>
                  )
                ) : null}

                <FormSelect name="loan_product_id" label="Loan product" options={options} />
                <FormTextField
                  name="amount_requested_minor"
                  label="Amount requested (₦)"
                  slotProps={{ htmlInput: { inputMode: 'numeric' } }}
                  helperText={
                    selectedProduct
                      ? `Allowed range: ${formatNaira(selectedProduct.minimum_amount_minor)} – ${
                          selectedProduct.maximum_amount_minor != null ? formatNaira(selectedProduct.maximum_amount_minor) : 'Unlimited'
                        }. Enter the amount in naira.`
                      : 'Enter the amount in naira. Kobo are not supported for applications.'
                  }
                />
                <FormTextField
                  name="purpose"
                  label="Purpose of the loan"
                  helperText="Briefly explain what the loan is for."
                  slotProps={{ htmlInput: { maxLength: 500 } }}
                />
                <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1.5 }}>
                  <Button
                    variant="outlined"
                    color="inherit"
                    sx={{ textTransform: 'none' }}
                    onClick={() => navigate(isEditing ? `/member/loans/applications/${editId}` : '/member/loans/applications')}
                  >
                    Cancel
                  </Button>
                  <Button
                    type="submit"
                    variant="contained"
                    sx={{ textTransform: 'none' }}
                    disabled={submitMutation.isPending}
                    startIcon={submitMutation.isPending ? <CircularProgress size={18} /> : null}
                  >
                    {submitMutation.isPending ? 'Saving…' : isEditing ? 'Save changes' : 'Save draft'}
                  </Button>
                </Box>
              </Stack>
            </form>
          </FormProvider>
        </CardContent>
      </Card>
    )
  }

  return (
    <SimplePageContainer
      title={isEditing ? 'Edit draft application' : 'New loan application'}
      subtitle={
        isEditing
          ? 'Update the draft and save your changes. Submission happens from the application detail page.'
          : 'Apply for a member loan. Saving creates a draft you can submit later.'
      }
      backTo={isEditing ? `/member/loans/applications/${editId}` : '/member/loans/applications'}
    >
      {content}
    </SimplePageContainer>
  )
}