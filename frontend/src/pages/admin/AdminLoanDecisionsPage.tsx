import type { ReactNode } from 'react'
import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Stack from '@mui/material/Stack'
import Table from '@mui/material/Table'
import TableBody from '@mui/material/TableBody'
import TableCell from '@mui/material/TableCell'
import TableContainer from '@mui/material/TableContainer'
import TableHead from '@mui/material/TableHead'
import TablePagination from '@mui/material/TablePagination'
import TableRow from '@mui/material/TableRow'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import { useNavigate } from 'react-router-dom'

import { PageContainer } from '../../components/common/PageContainer'
import { listAdminLoanDecisions } from '../../services/loans'
import { formatDate, formatNaira } from '../../utils/format'

interface LoanDecisionRow {
  id: string
  application_number: string
  amount_requested_minor: number
  member: { name: string | null; member_number: string } | null
  loan_product: { name: string } | null
  committee_meeting: { meeting_date: string | null } | null
  investigation: { recommendation: string | null; submitted_at: string | null } | null
}

export function AdminLoanDecisionsPage(): ReactNode {
  const navigate = useNavigate()
  const [page, setPage] = useState(0)
  const [rowsPerPage, setRowsPerPage] = useState(10)
  const [searchDraft, setSearchDraft] = useState('')
  const [search, setSearch] = useState('')

  const { data: result, isLoading, error, refetch } = useQuery({
    queryKey: ['admin-loan-decisions', page, rowsPerPage, search],
    queryFn: () =>
      listAdminLoanDecisions({ per_page: rowsPerPage, page: page + 1, search: search.trim() || undefined }),
    placeholderData: (previousData) => previousData,
  })

  const submitSearch = (): void => {
    setSearch(searchDraft.trim())
    setPage(0)
  }

  if (isLoading) {
    return (
      <PageContainer title="Final Loan Decisions" subtitle="Approvals and rejections awaiting an administrator">
        <div style={{ display: 'flex', justifyContent: 'center', padding: '3rem' }}>
          <Typography>Loading decision queue...</Typography>
        </div>
      </PageContainer>
    )
  }

  if (error) {
    return (
      <PageContainer title="Final Loan Decisions" subtitle="Approvals and rejections awaiting an administrator">
        <div style={{ textAlign: 'center', padding: '3rem' }}>
          <Typography color="error">Failed to load the decision queue. Please try again.</Typography>
          <Button variant="contained" onClick={() => void refetch()} sx={{ mt: 2 }}>
            Retry
          </Button>
        </div>
      </PageContainer>
    )
  }

  const applications = (result?.data ?? []) as LoanDecisionRow[]
  const total = result?.meta.total ?? 0

  return (
    <PageContainer title="Final Loan Decisions" subtitle="Approvals and rejections awaiting an administrator">
      <Stack direction="row" spacing={1} sx={{ mb: 3, alignItems: 'center' }}>
        <TextField
          label="Search applicant, member number, or application number"
          size="small"
          value={searchDraft}
          onChange={(event) => setSearchDraft(event.target.value)}
          onKeyDown={(event) => {
            if (event.key === 'Enter') {
              submitSearch()
            }
          }}
          sx={{ flexGrow: 1, maxWidth: 480 }}
        />
        <Button variant="contained" onClick={submitSearch} disabled={searchDraft.trim() === search && !searchDraft.trim()}>
          Search
        </Button>
      </Stack>

      <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
        {total} application{total === 1 ? '' : 's'} currently awaiting a final decision.
      </Typography>

      {applications.length === 0 ? (
        <Box sx={{ textAlign: 'center', py: 6 }}>
          <Typography variant="h6" color="text.secondary" gutterBottom>
            No applications awaiting final decision
          </Typography>
          <Typography variant="body2" color="text.secondary" sx={{ maxWidth: 440, mx: 'auto' }}>
            {search.trim()
              ? 'No applications match your search criteria.'
              : 'Applications reach this queue once the committee investigation has been submitted and the member is active.'}
          </Typography>
        </Box>
      ) : (
        <>
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>Applicant</TableCell>
                  <TableCell>Product</TableCell>
                  <TableCell align="right">Requested</TableCell>
                  <TableCell>Meeting</TableCell>
                  <TableCell>Recommendation</TableCell>
                  <TableCell>Investigation Submitted</TableCell>
                  <TableCell align="center">Actions</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {applications.map((app) => (
                  <TableRow key={app.id} hover>
                    <TableCell>
                      <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                        <Typography variant="body2">{app.member?.name ?? '—'}</Typography>
                        <Typography variant="caption" color="text.secondary">
                          {app.member?.member_number ?? ''}
                        </Typography>
                      </Stack>
                      <Typography variant="caption" color="text.secondary">
                        {app.application_number}
                      </Typography>
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
                        {app.committee_meeting?.meeting_date ? formatDate(app.committee_meeting.meeting_date) : '—'}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <Typography
                        variant="body2"
                        color="text.secondary"
                        sx={{
                          maxWidth: 260,
                          whiteSpace: 'nowrap',
                          overflow: 'hidden',
                          textOverflow: 'ellipsis',
                        }}
                      >
                        {app.investigation?.recommendation ?? '—'}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2" color="text.secondary">
                        {app.investigation?.submitted_at ? formatDate(app.investigation.submitted_at) : '—'}
                      </Typography>
                    </TableCell>
                    <TableCell align="center">
                      <Button
                        size="small"
                        color="primary"
                        onClick={() => navigate(`/admin/loan-decisions/${app.id}`)}
                      >
                        Review
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
          <TablePagination
            rowsPerPageOptions={[10, 25, 50]}
            component="div"
            count={total}
            rowsPerPage={rowsPerPage}
            page={page}
            onPageChange={(_event, newPage) => setPage(newPage)}
            onRowsPerPageChange={(event) => {
              setRowsPerPage(parseInt(event.target.value, 10))
              setPage(0)
            }}
          />
        </>
      )}
    </PageContainer>
  )
}