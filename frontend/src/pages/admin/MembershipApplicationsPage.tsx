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
import TablePagination from '@mui/material/TablePagination'
import TableSortLabel from '@mui/material/TableSortLabel'
import Typography from '@mui/material/Typography'
import { useState } from 'react'

import { PageContainer } from '../../components/common/PageContainer'
import { formatDate } from '../../utils/format'
import { listMemberApplications } from '../../services/membership'
import { StatusPill } from '../../components/common/StatusPill'

interface MembershipApplicationRow {
  id: string
  application_number: string
  full_name: string
  email: string
  phone: string
  status: string
  submitted_at: string | null
}

export function MembershipApplicationsPage(): ReactNode {
  const [page, setPage] = useState(0)
  const [rowsPerPage, setRowsPerPage] = useState(10)
  const [order, setOrder] = useState<'asc' | 'desc'>('desc')
  const [orderBy, setOrderBy] = useState('submitted_at')

  const { data: result, isLoading, error, refetch } = useQuery({
    queryKey: ['admin-membership-applications', page, rowsPerPage, order, orderBy],
    queryFn: () => listMemberApplications({ per_page: rowsPerPage, page: page + 1, search: '', status: '' }),
    placeholderData: (previousData) => previousData,
  })

  const handleRequestSort = (property: keyof MembershipApplicationRow) => {
    const isAsc = orderBy === property && order === 'asc'
    setOrder(isAsc ? 'desc' : 'asc')
    setOrderBy(property)
  }

  if (isLoading) {
    return (
      <PageContainer title="Membership Applications" subtitle="Review and process membership applications">
        <div style={{ display: 'flex', justifyContent: 'center', padding: '3rem' }}>
          <Typography>Loading applications...</Typography>
        </div>
      </PageContainer>
    )
  }

  if (error) {
    return (
      <PageContainer title="Membership Applications" subtitle="Review and process membership applications">
        <div style={{ textAlign: 'center', padding: '3rem' }}>
          <Typography color="error">Failed to load applications. Please try again.</Typography>
          <Button variant="contained" onClick={() => refetch()} sx={{ mt: 2 }}>
            Retry
          </Button>
        </div>
      </PageContainer>
    )
  }

  const applications = (result?.data ?? []) as MembershipApplicationRow[]
  const total = result?.meta.total ?? 0

  return (
    <PageContainer title="Membership Applications" subtitle="Review and process membership applications">
      {applications.length === 0 ? (
        <Box sx={{ textAlign: 'center', py: 6 }}>
          <Typography variant="h6" color="text.secondary" gutterBottom>
            No membership applications
          </Typography>
          <Typography variant="body2" color="text.secondary" sx={{ maxWidth: 400, mx: 'auto' }}>
            There are no membership applications to review at this time.
          </Typography>
        </Box>
      ) : (
        <>
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>
                    <TableSortLabel active={orderBy === 'application_number'} direction={orderBy === 'application_number' ? order : 'asc'} onClick={() => handleRequestSort('application_number')}>
                      Application #
                    </TableSortLabel>
                  </TableCell>
                  <TableCell>
                    <TableSortLabel active={orderBy === 'full_name'} direction={orderBy === 'full_name' ? order : 'asc'} onClick={() => handleRequestSort('full_name')}>
                      Applicant
                    </TableSortLabel>
                  </TableCell>
                  <TableCell>
                    <TableSortLabel active={orderBy === 'email'} direction={orderBy === 'email' ? order : 'asc'} onClick={() => handleRequestSort('email')}>
                      Email
                    </TableSortLabel>
                  </TableCell>
                  <TableCell>
                    <TableSortLabel active={orderBy === 'status'} direction={orderBy === 'status' ? order : 'asc'} onClick={() => handleRequestSort('status')}>
                      Status
                    </TableSortLabel>
                  </TableCell>
                  <TableCell>
                    <TableSortLabel active={orderBy === 'submitted_at'} direction={orderBy === 'submitted_at' ? order : 'asc'} onClick={() => handleRequestSort('submitted_at')}>
                      Submitted
                    </TableSortLabel>
                  </TableCell>
                  <TableCell align="center">Actions</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {applications.map((app) => (
                  <TableRow key={app.id} hover>
                    <TableCell>
                      <Typography variant="body2" component="span" sx={{ fontWeight: 600 }}>
                        {app.application_number}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                        <Typography variant="body2">{app.full_name}</Typography>
                        <Typography variant="caption" color="text.secondary">
                          {app.phone}
                        </Typography>
                      </Stack>
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2">{app.email}</Typography>
                    </TableCell>
                    <TableCell>
                      <StatusPill status={app.status} />
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2" color="text.secondary">
                        {app.submitted_at ? formatDate(app.submitted_at) : '—'}
                      </Typography>
                    </TableCell>
                    <TableCell align="center">
                      <Button size="small" component="a" href={`/admin/membership-applications/${app.id}`}>
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
