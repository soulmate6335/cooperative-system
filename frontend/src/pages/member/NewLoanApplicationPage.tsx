import type { ReactNode } from 'react'
import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import CircularProgress from '@mui/material/CircularProgress'
import Stack from '@mui/material/Stack'
import { useNavigate } from 'react-router-dom'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormProvider, useForm } from 'react-hook-form'
import { z } from 'zod'

import { FormSelect, type SelectOption } from '../../components/forms/FormSelect'
import { FormTextField } from '../../components/forms/FormTextField'
import { SimplePageContainer } from '../../components/common/PageContainer'
import { EmptyState } from '../../components/common/EmptyState'
import { ErrorState } from '../../components/common/ErrorState'
import { TableSkeleton } from '../../components/common/Skeletons'
import { createLoanApplication, listLoanProducts } from '../../services/loans'
import { getErrorMessage } from '../../utils/errors'

const schema = z.object({
  loan_product_id: z.string().min(1, 'Select a loan product.'),
  amount_requested_minor: z.coerce.number().int().positive('Enter a valid amount.'),
  purpose: z.string().min(1, 'Enter the purpose of the loan.'),
})

type FormValues = z.infer<typeof schema>

export function NewLoanApplicationPage(): ReactNode {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const productsQuery = useQuery({
    queryKey: ['loan-products', 'member'],
    queryFn: listLoanProducts,
  })

  const methods = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { loan_product_id: '', amount_requested_minor: undefined, purpose: '' },
  })

  const submitMutation = useMutation({
    mutationFn: (values: FormValues) => createLoanApplication(values),
    onSuccess: (application) => {
      void queryClient.invalidateQueries({ queryKey: ['member', 'loan-applications'] })
      navigate(`/member/loans/applications/${application.id}`)
    },
    onError: (error) => {
      setServerError(getErrorMessage(error, 'Failed to submit the loan application.'))
    },
  })

  const options: SelectOption[] =
    productsQuery.data?.map((product) => ({
      value: product.id,
      label: product.name,
    })) ?? []

  let content: ReactNode
  if (productsQuery.isPending) {
    content = <TableSkeleton rows={4} columns={3} />
  } else if (productsQuery.isError) {
    content = <ErrorState message={getErrorMessage(productsQuery.error, 'Failed to load loan products.')} onRetry={() => void productsQuery.refetch()} />
  } else if (productsQuery.data.length === 0) {
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
            <form onSubmit={methods.handleSubmit((values) => submitMutation.mutate(values))}>
              <Stack spacing={2.5}>
                {serverError ? <Alert severity="error">{serverError}</Alert> : null}
                <FormSelect name="loan_product_id" label="Loan product" options={options} />
                <FormTextField
                  name="amount_requested_minor"
                  label="Amount requested (₦)"
                  slotProps={{ htmlInput: { inputMode: 'numeric' } }}
                  helperText="Enter the amount in naira. Kobo are not supported for applications."
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
                    onClick={() => navigate('/member/loans/applications')}
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
                    Submit application
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
    <SimplePageContainer title="New loan application" subtitle="Apply for a member loan" backTo="/member/loans/applications">
      {content}
    </SimplePageContainer>
  )
}