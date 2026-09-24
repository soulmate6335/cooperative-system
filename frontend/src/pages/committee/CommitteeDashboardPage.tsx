import type { ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Stack from '@mui/material/Stack'
import Table from '@mui/material/Table'
import TableBody from '@mui/material/TableBody'
import TableCell from '@mui/material/TableCell'
import TableContainer from '@mui/material/TableContainer'
import TableHead from '@mui/material/TableHead'
import TableRow from '@mui/material/TableRow'
import Typography from '@mui/material/Typography'
import AssignmentIcon from '@mui/icons-material/Assignment'
import ArrowForwardIcon from '@mui/icons-material/ArrowForward'

import { PageContainer } from '../../components/common/PageContainer'
import { formatDate, formatNaira } from '../../utils/format'
import { listCommitteeApplications } from '../../services/loans'
import { StatusPill } from '../../components/common/StatusPill'

export function CommitteeDashboardPage(): ReactNode {
  const { data: result, isLoading, error, refetch } = useQuery({
    queryKey: ['committee-applications'],
    queryFn: () => listCommitteeApplications({ per_page: 20 }),
  })

  if (isLoading) {
    return (
      <PageContainer title="Committee Review" subtitle="Applications assigned to you or ready for committee review">
        <div style={{ display: 'flex', justifyContent: 'center', padding: '3rem' }}>
          <Typography>Loading applications...</Typography>
        </div>
      </PageContainer>
    )
  }

  if (error) {
    return (
      <PageContainer title="Committee Review" subtitle="Applications assigned to you or ready for committee review">
        <div style={{ textAlign: 'center', padding: '3rem' }}>
          <Typography color="error">Failed to load applications. Please try again.</Typography>
          <Button variant="contained" onClick={() => refetch()} sx={{ mt: 2 }}>
            Retry
          </Button>
        </div>
      </PageContainer>
    )
  }

  const applications = result?.data ?? []

  return (
    <PageContainer title="Committee Review" subtitle="Applications assigned to you or ready for committee review">
      {applications.length === 0 ? (
        <Box sx={{ textAlign: 'center', py: 6 }}>
          <AssignmentIcon sx={{ fontSize: 64, color: 'text.secondary', mb: 2 }} />
          <Typography variant="h6" color="text.secondary" gutterBottom>
            No applications ready for review
          </Typography>
          <Typography variant="body2" color="text.secondary" sx={{ maxWidth: 420, mx: 'auto' }}>
            When a loan application completes its guarantor requirement it will appear here for committee
            investigation.
          </Typography>
        </Box>
      ) : (
        <TableContainer>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>Application #</TableCell>
                <TableCell>Applicant</TableCell>
                <TableCell>Product</TableCell>
                <TableCell align="right">Amount</TableCell>
                <TableCell>Guarantors</TableCell>
                <TableCell>Committee Meeting</TableCell>
                <TableCell>Status</TableCell>
                <TableCell align="center">Action</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {applications.map((app) => {
                const accepted = app.guarantors.filter((guarantor) => guarantor.status === 'accepted').length
                const required = app.loan_product?.required_guarantors
                return (
                  <TableRow key={app.id} hover>
                    <TableCell>
                      <Typography variant="body2" component="span" sx={{ fontWeight: 600 }}>
                        {app.application_number}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                        <Typography variant="body2" sx={{ fontWeight: 500 }}>
                          {app.member?.name ?? '—'}
                        </Typography>
                        {app.member?.member_number ? (
                          <Typography variant="caption" color="text.secondary">
                            {app.member.member_number}
                          </Typography>
                        ) : null}
                      </Stack>
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2">{app.loan_product?.name ?? '—'}</Typography>
                    </TableCell>
                    <TableCell align="right">
                      <Typography variant="body2" sx={{ fontWeight: 500 }}>
                        {formatNaira(app.amount_requested_minor)}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2" color="text.secondary">
                        {required != null ? `${accepted} / ${required}` : '—'}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2" color="text.secondary">
                        {app.committee_meeting?.meeting_date ? formatDate(app.committee_meeting.meeting_date) : '—'}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <StatusPill status={app.status} />
                    </TableCell>
                    <TableCell align="center">
                      <Button
                        size="small"
                        component="a"
                        href={`/committee/loan-applications/${app.id}`}
                        startIcon={<ArrowForwardIcon fontSize="small" />}
                      >
                        Review
                      </Button>
                    </TableCell>
                  </TableRow>
                )
              })}
            </TableBody>
          </Table>
        </TableContainer>
      )}
    </PageContainer>
  )
}