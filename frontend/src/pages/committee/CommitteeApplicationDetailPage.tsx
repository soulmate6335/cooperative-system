import type { ReactNode } from 'react'
import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import CircularProgress from '@mui/material/CircularProgress'
import Divider from '@mui/material/Divider'
import Stack from '@mui/material/Stack'
import Table from '@mui/material/Table'
import TableBody from '@mui/material/TableBody'
import TableCell from '@mui/material/TableCell'
import TableContainer from '@mui/material/TableContainer'
import TableHead from '@mui/material/TableHead'
import TableRow from '@mui/material/TableRow'
import Tabs from '@mui/material/Tabs'
import Tab from '@mui/material/Tab'
import Typography from '@mui/material/Typography'
import { useParams } from 'react-router-dom'
import { FormProvider, useForm } from 'react-hook-form'

import { PageContainer } from '../../components/common/PageContainer'
import { ConfirmDialog } from '../../components/common/ConfirmDialog'
import { FormTextField } from '../../components/forms/FormTextField'
import { formatBasisPoints, formatDate, formatNaira } from '../../utils/format'
import { statusStyle } from '../../utils/status'
import { getCommitteeApplication, startInvestigation, submitInvestigation, updateInvestigation } from '../../services/loans'
import { StatusPill } from '../../components/common/StatusPill'
import { ErrorState } from '../../components/common/ErrorState'
import { getErrorMessage } from '../../utils/errors'
import type { LoanApplication } from '../../types'

type AppTab = 'overview' | 'guarantors' | 'investigation'

interface InvestigationFormValues {
  member_findings: string
  savings_findings: string
  shares_findings: string
  existing_loan_findings: string
  guarantor_findings: string
  committee_comments: string
  recommendation: string
}

interface InvestigationPanelProps {
  application: LoanApplication
}

function InvestigationPanel({ application }: InvestigationPanelProps): ReactNode {
  const queryClient = useQueryClient()
  const [confirmSubmit, setConfirmSubmit] = useState(false)
  const [actionError, setActionError] = useState<string | null>(null)
  const investigation = application.investigation

  const methods = useForm<InvestigationFormValues>({
    defaultValues: {
      member_findings: investigation?.member_findings ?? '',
      savings_findings: investigation?.savings_findings ?? '',
      shares_findings: investigation?.shares_findings ?? '',
      existing_loan_findings: investigation?.existing_loan_findings ?? '',
      guarantor_findings: investigation?.guarantor_findings ?? '',
      committee_comments: investigation?.committee_comments ?? '',
      recommendation: investigation?.recommendation ?? '',
    },
  })

  const invalidate = (): void => {
    void queryClient.invalidateQueries({ queryKey: ['committee-application', application.id] })
    void queryClient.invalidateQueries({ queryKey: ['committee-applications'] })
  }

  const startMutation = useMutation({
    mutationFn: () => startInvestigation(application.id),
    onSuccess: () => {
      setActionError(null)
      invalidate()
    },
    onError: (err) => setActionError(getErrorMessage(err, 'Failed to start the investigation.')),
  })

  const saveMutation = useMutation({
    mutationFn: (values: InvestigationFormValues) => updateInvestigation(application.id, values),
    onSuccess: () => {
      setActionError(null)
      invalidate()
    },
    onError: (err) => setActionError(getErrorMessage(err, 'Failed to save the investigation.')),
  })

  const submitMutation = useMutation({
    mutationFn: (values: InvestigationFormValues) => submitInvestigation(application.id, values),
    onSuccess: () => {
      setConfirmSubmit(false)
      setActionError(null)
      invalidate()
    },
    onError: (err) => setActionError(getErrorMessage(err, 'Failed to submit the investigation.')),
  })

  const busy = startMutation.isPending || saveMutation.isPending || submitMutation.isPending

  const confirmSubmission = (): void => {
    const values = methods.getValues()
    if (!values.recommendation.trim()) {
      methods.setError('recommendation', {
        type: 'required',
        message: 'A recommendation is required before the investigation can be submitted.',
      })
      setConfirmSubmit(false)
      return
    }
    submitMutation.mutate(values)
  }

  if (!investigation) {
    return (
      <Card variant="outlined">
        <CardContent>
          {actionError ? (
            <Alert severity="error" sx={{ mb: 2 }}>
              {actionError}
            </Alert>
          ) : null}
          <Box sx={{ textAlign: 'center', py: 5, px: 3 }}>
            <Typography variant="body2" color="text.secondary" gutterBottom>
              No investigation has been started for this application.
            </Typography>
            <Typography variant="body2" color="text.secondary" sx={{ maxWidth: 480, mx: 'auto', mb: 3 }}>
              The guarantor requirement is complete. Start an investigation to record findings and the committee&apos;s
              recommendation for admin decision.
            </Typography>
            <Button variant="contained" onClick={() => startMutation.mutate()} disabled={busy} startIcon={startMutation.isPending ? <CircularProgress size={18} /> : undefined}>
              {startMutation.isPending ? 'Starting...' : 'Start Investigation'}
            </Button>
          </Box>
        </CardContent>
      </Card>
    )
  }

  if (investigation.status === 'submitted') {
    return (
      <Card variant="outlined">
        <CardContent>
          <Alert severity="success" sx={{ mb: 3 }}>
            Investigation submitted{investigation.submitted_at ? ` on ${formatDate(investigation.submitted_at)}` : ''} —
            this application is now pending admin decision.
          </Alert>
          <Stack spacing={2}>
            <Box>
              <Typography variant="body2" color="text.secondary">
                Member Findings
              </Typography>
              <Typography variant="body1" sx={{ whiteSpace: 'pre-wrap' }}>
                {investigation.member_findings ?? '—'}
              </Typography>
            </Box>
            <Box>
              <Typography variant="body2" color="text.secondary">
                Savings Findings
              </Typography>
              <Typography variant="body1" sx={{ whiteSpace: 'pre-wrap' }}>
                {investigation.savings_findings ?? '—'}
              </Typography>
            </Box>
            <Box>
              <Typography variant="body2" color="text.secondary">
                Shares Findings
              </Typography>
              <Typography variant="body1" sx={{ whiteSpace: 'pre-wrap' }}>
                {investigation.shares_findings ?? '—'}
              </Typography>
            </Box>
            <Box>
              <Typography variant="body2" color="text.secondary">
                Existing Loan Findings
              </Typography>
              <Typography variant="body1" sx={{ whiteSpace: 'pre-wrap' }}>
                {investigation.existing_loan_findings ?? '—'}
              </Typography>
            </Box>
            <Box>
              <Typography variant="body2" color="text.secondary">
                Guarantor Findings
              </Typography>
              <Typography variant="body1" sx={{ whiteSpace: 'pre-wrap' }}>
                {investigation.guarantor_findings ?? '—'}
              </Typography>
            </Box>
            <Box>
              <Typography variant="body2" color="text.secondary">
                Committee Comments
              </Typography>
              <Typography variant="body1" sx={{ whiteSpace: 'pre-wrap' }}>
                {investigation.committee_comments ?? '—'}
              </Typography>
            </Box>
            <Box>
              <Typography variant="body2" color="text.secondary">
                Recommendation
              </Typography>
              <Typography variant="body1" sx={{ fontWeight: 500 }}>
                {investigation.recommendation ?? '—'}
              </Typography>
            </Box>
          </Stack>
        </CardContent>
      </Card>
    )
  }

  return (
    <Card variant="outlined">
      <CardContent>
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ mb: 2, flexWrap: 'wrap', alignItems: 'center' }}>
          <Typography variant="h6" sx={{ flexGrow: 1 }}>
            Investigation
          </Typography>
          <StatusPill status={investigation.status} />
        </Stack>
        {actionError ? (
          <Alert severity="error" sx={{ mb: 2 }}>
            {actionError}
          </Alert>
        ) : null}
        <FormProvider {...methods}>
          <Stack spacing={2}>
            <FormTextField<InvestigationFormValues> name="member_findings" label="Member Findings" multiline minRows={2} fullWidth />
            <FormTextField<InvestigationFormValues> name="savings_findings" label="Savings Findings" multiline minRows={2} fullWidth />
            <FormTextField<InvestigationFormValues> name="shares_findings" label="Shares Findings" multiline minRows={2} fullWidth />
            <FormTextField<InvestigationFormValues> name="existing_loan_findings" label="Existing Loan Findings" multiline minRows={2} fullWidth />
            <FormTextField<InvestigationFormValues> name="guarantor_findings" label="Guarantor Findings" multiline minRows={2} fullWidth />
            <FormTextField<InvestigationFormValues> name="committee_comments" label="Committee Comments" multiline minRows={2} fullWidth />
            <FormTextField<InvestigationFormValues> name="recommendation" label="Recommendation" multiline minRows={2} fullWidth />
          </Stack>
        </FormProvider>
        <Alert severity="info" sx={{ mt: 2 }}>
          The recommendation is advisory only. The final loan decision belongs to the admin.
        </Alert>
        <Stack direction="row" spacing={1} sx={{ mt: 2, justifyContent: 'flex-end' }}>
          <Button
            variant="outlined"
            disabled={busy}
            onClick={methods.handleSubmit((values) => saveMutation.mutate(values))}
          >
            {saveMutation.isPending ? 'Saving...' : 'Save Draft'}
          </Button>
          <Button variant="contained" color="primary" disabled={busy} onClick={() => setConfirmSubmit(true)}>
            {submitMutation.isPending ? 'Submitting...' : 'Submit Investigation'}
          </Button>
        </Stack>
      </CardContent>
      <ConfirmDialog
        open={confirmSubmit}
        title="Submit investigation?"
        message="The investigation and recommendation will be locked and the application will move to pending admin decision. This cannot be edited afterwards."
        confirmLabel="Submit investigation"
        loading={submitMutation.isPending}
        onConfirm={confirmSubmission}
        onClose={() => setConfirmSubmit(false)}
      />
    </Card>
  )
}

export function CommitteeApplicationDetailPage(): ReactNode {
  const { id } = useParams<{ id: string }>()
  const [activeTab, setActiveTab] = useState<AppTab>('overview')
  const { data: application, isLoading, error, refetch } = useQuery({
    queryKey: ['committee-application', id],
    queryFn: () => getCommitteeApplication(id!),
    enabled: !!id,
  })

  if (isLoading) {
    return (
      <PageContainer title="Application Detail" subtitle="Review loan application details">
        <div style={{ display: 'flex', justifyContent: 'center', padding: '3rem' }}>
          <Typography>Loading application...</Typography>
        </div>
      </PageContainer>
    )
  }

  if (error || !application) {
    return (
      <PageContainer title="Application Detail" subtitle="Review loan application details">
        <ErrorState message={getErrorMessage(error, 'Failed to load application details.')} onRetry={() => refetch()} />
      </PageContainer>
    )
  }

  const member = application.member
  const product = application.loan_product
  const guarantors = application.guarantors
  const acceptedGuarantors = guarantors.filter((guarantor) => guarantor.status === 'accepted').length
  const pendingGuarantors = guarantors.filter((guarantor) => guarantor.status === 'pending').length

  const tabs: Array<{ id: AppTab; label: string }> = [
    { id: 'overview', label: 'Overview' },
    { id: 'guarantors', label: `Guarantors (${guarantors.length})` },
    { id: 'investigation', label: 'Investigation' },
  ]

  return (
    <PageContainer
      title={`Application: ${application.application_number}`}
      subtitle={`Status: ${statusStyle(application.status).label}`}
      backTo="/committee"
    >
      <Tabs
        value={activeTab}
        onChange={(_event, value: AppTab) => setActiveTab(value)}
        sx={{ mb: 3, borderBottom: 1, borderColor: 'divider' }}
      >
        {tabs.map((tab) => (
          <Tab key={tab.id} label={tab.label} />
        ))}
      </Tabs>

      <Stack spacing={3}>
        {activeTab === 'overview' ? (
          <Card variant="outlined">
            <CardContent>
              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={3} sx={{ flexWrap: 'wrap' }}>
                <Box sx={{ flexGrow: 1, minWidth: 280 }}>
                  <Typography variant="h6" gutterBottom>
                    Member Information
                  </Typography>
                  <Divider sx={{ mb: 2 }} />
                  <Stack spacing={1.5}>
                    <Box>
                      <Typography variant="body2" color="text.secondary">
                        Name
                      </Typography>
                      <Typography variant="body1" sx={{ fontWeight: 500 }}>
                        {member?.name ?? '—'}
                      </Typography>
                    </Box>
                    <Box>
                      <Typography variant="body2" color="text.secondary">
                        Member Number
                      </Typography>
                      <Typography variant="body1" sx={{ fontWeight: 500 }}>
                        {member?.member_number ?? '—'}
                      </Typography>
                    </Box>
                    <Box>
                      <Typography variant="body2" color="text.secondary">
                        Membership Type
                      </Typography>
                      <Typography variant="body1" sx={{ fontWeight: 500 }}>
                        {member?.membership_type ?? '—'}
                      </Typography>
                    </Box>
                    <Box>
                      <Typography variant="body2" color="text.secondary">
                        Joined
                      </Typography>
                      <Typography variant="body1" sx={{ fontWeight: 500 }}>
                        {member?.joined_at ? formatDate(member.joined_at) : '—'}
                      </Typography>
                    </Box>
                    <Box>
                      <Typography variant="body2" color="text.secondary">
                        Status
                      </Typography>
                      <StatusPill status={member?.status ?? '—'} />
                    </Box>
                  </Stack>
                </Box>
                <Box sx={{ flexGrow: 1, minWidth: 280 }}>
                  <Typography variant="h6" gutterBottom>
                    Loan Details
                  </Typography>
                  <Divider sx={{ mb: 2 }} />
                  <Stack spacing={1.5}>
                    <Box>
                      <Typography variant="body2" color="text.secondary">
                        Product
                      </Typography>
                      <Typography variant="body1" sx={{ fontWeight: 500 }}>
                        {product?.name ?? '—'}
                      </Typography>
                    </Box>
                    <Box>
                      <Typography variant="body2" color="text.secondary">
                        Amount Requested
                      </Typography>
                      <Typography variant="h5" component="h2" sx={{ fontWeight: 700, color: 'primary.main' }}>
                        {formatNaira(application.amount_requested_minor)}
                      </Typography>
                    </Box>
                    <Box>
                      <Typography variant="body2" color="text.secondary">
                        Purpose
                      </Typography>
                      <Typography variant="body1" sx={{ fontWeight: 500 }}>
                        {application.purpose}
                      </Typography>
                    </Box>
                    <Box>
                      <Typography variant="body2" color="text.secondary">
                        Repayment Term
                      </Typography>
                      <Typography variant="body1" sx={{ fontWeight: 500 }}>
                        {product?.repayment_months ?? '—'} months
                      </Typography>
                    </Box>
                    <Box>
                      <Typography variant="body2" color="text.secondary">
                        Interest Rate
                      </Typography>
                      <Typography variant="body1" sx={{ fontWeight: 500 }}>
                        {product ? formatBasisPoints(product.interest_rate_basis_points) : '—'} ({product?.interest_method ?? '—'})
                      </Typography>
                    </Box>
                    <Box>
                      <Typography variant="body2" color="text.secondary">
                        Required Guarantors
                      </Typography>
                      <Typography variant="body1" sx={{ fontWeight: 500 }}>
                        {product?.required_guarantors ?? '—'}
                      </Typography>
                    </Box>
                    <Box>
                      <Typography variant="body2" color="text.secondary">
                        Committee Meeting
                      </Typography>
                      <Typography variant="body1" sx={{ fontWeight: 500 }}>
                        {application.committee_meeting?.meeting_date ? formatDate(application.committee_meeting.meeting_date) : '—'}
                        {application.committee_meeting?.meeting_type ? ` (${application.committee_meeting.meeting_type})` : ''}
                      </Typography>
                    </Box>
                    <Box>
                      <Typography variant="body2" color="text.secondary">
                        Submitted
                      </Typography>
                      <Typography variant="body1" sx={{ fontWeight: 500 }}>
                        {application.submitted_at ? formatDate(application.submitted_at) : '—'}
                      </Typography>
                    </Box>
                    <Box>
                      <Typography variant="body2" color="text.secondary">
                        Status
                      </Typography>
                      <StatusPill status={application.status} />
                    </Box>
                  </Stack>
                </Box>
              </Stack>
            </CardContent>
          </Card>
        ) : null}

        {activeTab === 'guarantors' ? (
          <Card variant="outlined">
            <CardContent>
              {product?.required_guarantors != null ? (
                <Typography variant="body2" sx={{ fontWeight: 600, mb: 2 }}>
                  Required {product.required_guarantors} · Accepted {acceptedGuarantors} · Pending {pendingGuarantors}
                </Typography>
              ) : null}
              {guarantors.length === 0 ? (
                <Box sx={{ textAlign: 'center', py: 4 }}>
                  <Typography variant="body2" color="text.secondary">
                    No guarantors have been added to this application.
                  </Typography>
                </Box>
              ) : (
                <TableContainer>
                  <Table>
                    <TableHead>
                      <TableRow>
                        <TableCell>Guarantor</TableCell>
                        <TableCell>Member #</TableCell>
                        <TableCell>Status</TableCell>
                        <TableCell>Requested</TableCell>
                        <TableCell>Responded</TableCell>
                        <TableCell>Response Note</TableCell>
                      </TableRow>
                    </TableHead>
                    <TableBody>
                      {guarantors.map((g) => (
                        <TableRow key={g.id}>
                          <TableCell>
                            <Typography variant="body2" sx={{ fontWeight: 500 }}>
                              {g.guarantor_member?.name ?? g.guarantor_member?.member_number ?? '—'}
                            </Typography>
                          </TableCell>
                          <TableCell>
                            <Typography variant="body2" color="text.secondary">
                              {g.guarantor_member?.member_number ?? '—'}
                            </Typography>
                          </TableCell>
                          <TableCell>
                            <StatusPill status={g.status} />
                          </TableCell>
                          <TableCell>
                            <Typography variant="body2" color="text.secondary">
                              {g.requested_at ? formatDate(g.requested_at) : '—'}
                            </Typography>
                          </TableCell>
                          <TableCell>
                            <Typography variant="body2" color="text.secondary">
                              {g.responded_at ? formatDate(g.responded_at) : '—'}
                            </Typography>
                          </TableCell>
                          <TableCell>
                            <Typography variant="body2" color="text.secondary">
                              {g.response_note ?? '—'}
                            </Typography>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </TableContainer>
              )}
            </CardContent>
          </Card>
        ) : null}

        {activeTab === 'investigation' ? <InvestigationPanel application={application} /> : null}
      </Stack>
    </PageContainer>
  )
}