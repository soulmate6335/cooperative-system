import type { ReactNode } from 'react'
import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import Box from '@mui/material/Box'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
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

import { PageContainer } from '../../components/common/PageContainer'
import { formatBasisPoints, formatDate, formatNaira } from '../../utils/format'
import { statusStyle } from '../../utils/status'
import { getCommitteeApplication } from '../../services/loans'
import { StatusPill } from '../../components/common/StatusPill'
import { ErrorState } from '../../components/common/ErrorState'

type AppTab = 'overview' | 'guarantors' | 'investigation'

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
        <ErrorState message="Failed to load application details." onRetry={() => refetch()} />
      </PageContainer>
    )
  }

  const member = application.member
  const product = application.loan_product
  const guarantors = application.guarantors
  const investigation = application.investigation

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
                              {g.guarantor_member?.member_number ?? '—'}
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

        {activeTab === 'investigation' ? (
          <Card variant="outlined">
            <CardContent>
              {investigation ? (
                <Stack spacing={3}>
                  <Box>
                    <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ mb: 2, flexWrap: 'wrap', alignItems: 'center' }}>
                      <Typography variant="h6" gutterBottom>
                        Investigation
                      </Typography>
                      <StatusPill status={investigation.status} />
                    </Stack>
                  </Box>
                  <Divider />
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
                </Stack>
              ) : (
                <Box sx={{ textAlign: 'center', py: 4 }}>
                  <Typography variant="body2" color="text.secondary">
                    No investigation has been started for this application.
                  </Typography>
                </Box>
              )}
            </CardContent>
          </Card>
        ) : null}
      </Stack>
    </PageContainer>
  )
}