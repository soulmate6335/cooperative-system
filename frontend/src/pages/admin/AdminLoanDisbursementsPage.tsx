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
import { StatusPill } from '../../components/common/StatusPill'
import { listAdminLoanDisbursements } from '../../services/loans'
import { formatBasisPoints, formatDate, formatNaira } from '../../utils/format'

interface DisbursementRow {
  id: string
  loan_number: string
  status: string
  principal_amount_minor: number
  interest_rate_basis_points: number
  member: { name: string | null; member_number: string } | null
  product: { name: string } | null
  decision: { approved_amount_minor: number | null; interest_rate_basis_points: number | null; decided_at: string | null } | null
}

export function AdminLoanDisbursementsPage(): ReactNode {
  const navigate = useNavigate()
  const [page, setPage] = useState(0)
  const [rowsPerPage, setRowsPerPage] = useState(10)
  const [searchDraft, setSearchDraft] = useState('')
  const [search, setSearch] = useState('')

  const { data: result, isLoading, error, refetch } = useQuery({
    queryKey: ['admin-loan-disbursements', page, rowsPerPage, search],
    queryFn: () =>
      listAdminLoanDisbursements({ per_page: rowsPerPage, page: page + 1, search: search.trim() || undefined }),
    placeholderData: (previousData) => previousData,
  })

  const submitSearch = (): void => {
    setSearch(searchDraft.trim())
    setPage(0)
  }

  if (isLoading) {
    return (
      <PageContainer title="Loan Disbursements" subtitle="Approved loans awaiting disbursement">
        <div style={{ display: 'flex', justifyContent: 'center', padding: '3rem' }}>
          <Typography>Loading disbursement queue...</Typography>
        </div>
      </PageContainer>
    )
  }

  if (error) {
    return (
      <PageContainer title="Loan Disbursements" subtitle="Approved loans awaiting disbursement">
        <div style={{ textAlign: 'center', padding: '3rem' }}>
          <Typography color="error">Failed to load the disbursement queue. Please try again.</Typography>
          <Button variant="contained" onClick={() => void refetch()} sx={{ mt: 2 }}>
            Retry
          </Button>
        </div>
      </PageContainer>
    )
  }

  const loans = (result?.data ?? []) as DisbursementRow[]
  const total = result?.meta.total ?? 0

  return (
    <PageContainer title="Loan Disbursements" subtitle="Approved loans awaiting disbursement">
      <Stack direction="row" spacing={1} sx={{ mb: 3, alignItems: 'center' }}>
        <TextField
          label="Search member, member number, loan, or application number"
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
        {total} approved loan{total === 1 ? '' : 's'} currently awaiting disbursement.
      </Typography>

      {loans.length === 0 ? (
        <Box sx={{ textAlign: 'center', py: 6 }}>
          <Typography variant="h6" color="text.secondary" gutterBottom>
            No loans awaiting disbursement
          </Typography>
          <Typography variant="body2" color="text.secondary" sx={{ maxWidth: 440, mx: 'auto' }}>
            {search.trim()
              ? 'No loans match your search criteria.'
              : 'Approved loans appear here and are ready for a single, full disbursement to the member.'}
          </Typography>
        </Box>
      ) : (
        <>
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>Member</TableCell>
                  <TableCell>Product</TableCell>
                  <TableCell align="right">Approved Amount</TableCell>
                  <TableCell align="right">Interest Rate</TableCell>
                  <TableCell>Approved At</TableCell>
                  <TableCell align="center">Actions</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {loans.map((loan) => (
                  <TableRow key={loan.id} hover>
                    <TableCell>
                      <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                        <Typography variant="body2">{loan.member?.name ?? '—'}</Typography>
                        <Typography variant="caption" color="text.secondary">
                          {loan.member?.member_number ?? ''}
                        </Typography>
                      </Stack>
                      <Typography variant="caption" color="text.secondary">
                        {loan.loan_number}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                        <Typography variant="body2">{loan.product?.name ?? '—'}</Typography>
                        <StatusPill status={loan.status} />
                      </Stack>
                    </TableCell>
                    <TableCell align="right">
                      <Typography variant="body2" sx={{ fontWeight: 500 }}>
                        {formatNaira(loan.principal_amount_minor)}
                      </Typography>
                    </TableCell>
                    <TableCell align="right">
                      <Typography variant="body2" color="text.secondary">
                        {formatBasisPoints(loan.decision?.interest_rate_basis_points ?? loan.interest_rate_basis_points)}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2" color="text.secondary">
                        {loan.decision?.decided_at ? formatDate(loan.decision.decided_at) : '—'}
                      </Typography>
                    </TableCell>
                    <TableCell align="center">
                      <Button size="small" color="primary" onClick={() => navigate(`/admin/loans/${loan.id}/disbursement`)}>
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