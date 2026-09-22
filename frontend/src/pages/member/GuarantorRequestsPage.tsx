import type { ReactNode } from 'react'
import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Dialog from '@mui/material/Dialog'
import DialogActions from '@mui/material/DialogActions'
import DialogContent from '@mui/material/DialogContent'
import DialogContentText from '@mui/material/DialogContentText'
import DialogTitle from '@mui/material/DialogTitle'
import Stack from '@mui/material/Stack'
import Table from '@mui/material/Table'
import TableBody from '@mui/material/TableBody'
import TableCell from '@mui/material/TableCell'
import TableContainer from '@mui/material/TableContainer'
import TableHead from '@mui/material/TableHead'
import TableRow from '@mui/material/TableRow'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import GroupAddIcon from '@mui/icons-material/GroupAdd'

import { ErrorState } from '../../components/common/ErrorState'
import { SimplePageContainer } from '../../components/common/PageContainer'
import { TableSkeleton } from '../../components/common/Skeletons'
import { StatusPill } from '../../components/common/StatusPill'
import { listGuarantorRequests, respondToGuarantorRequest } from '../../services/loans'
import { formatDate, formatNaira } from '../../utils/format'
import { getErrorMessage } from '../../utils/errors'

type ActionTarget = { id: string; applicationNumber: string } | null

export function GuarantorRequestsPage(): ReactNode {
  const queryClient = useQueryClient()
  const [respondTarget, setRespondTarget] = useState<ActionTarget>(null)
  const [responseNote, setResponseNote] = useState('')
  const [error, setError] = useState<string | null>(null)

  const { data: result, isLoading, error: queryError, refetch } = useQuery({
    queryKey: ['member', 'guarantor-requests'],
    queryFn: () => listGuarantorRequests({ per_page: 50 }),
  })

  const respondMutation = useMutation({
    mutationFn: ({ id, action }: { id: string; action: 'accept' | 'decline' }) => respondToGuarantorRequest(id, action, responseNote || undefined),
    onSuccess: () => {
      setRespondTarget(null)
      setResponseNote('')
      setError(null)
      void queryClient.invalidateQueries({ queryKey: ['member', 'guarantor-requests'] })
    },
    onError: (err) => setError(getErrorMessage(err, 'Failed to update the guarantor request.')),
  })

  if (isLoading) {
    return (
      <SimplePageContainer title="Guarantor Requests" subtitle="Respond to requests to guarantee a member's loan">
        <TableSkeleton rows={5} columns={5} />
      </SimplePageContainer>
    )
  }

  if (queryError) {
    return (
      <SimplePageContainer title="Guarantor Requests" subtitle="Respond to requests to guarantee a member's loan">
        <ErrorState message={getErrorMessage(queryError, 'Failed to load guarantor requests.')} onRetry={() => refetch()} />
      </SimplePageContainer>
    )
  }

  const requests = result?.data ?? []

  return (
    <SimplePageContainer title="Guarantor Requests" subtitle="Respond to requests to guarantee a member's loan">
      {error ? (
        <Alert severity="error" sx={{ mb: 2 }}>
          {error}
        </Alert>
      ) : null}

      {requests.length === 0 ? (
        <Box sx={{ textAlign: 'center', py: 7, px: 3 }}>
          <GroupAddIcon color="primary" sx={{ fontSize: 56, mb: 1 }} />
          <Typography variant="h6">No guarantor requests</Typography>
          <Typography variant="body2" color="text.secondary" sx={{ maxWidth: 480, mx: 'auto', mt: 1 }}>
            When a fellow member requests you as a guarantor for their loan, the request will appear here for you to
            accept or decline.
          </Typography>
        </Box>
      ) : (
        <Card variant="outlined">
          <CardContent>
            <TableContainer>
              <Table>
                <TableHead>
                  <TableRow>
                    <TableCell>Application #</TableCell>
                    <TableCell align="right">Amount</TableCell>
                    <TableCell>Requested</TableCell>
                    <TableCell>Status</TableCell>
                    <TableCell align="center">Action</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {requests.map((request) => (
                    <TableRow key={request.id} hover>
                      <TableCell>
                        <Typography variant="body2" sx={{ fontWeight: 600 }}>
                          {request.application?.application_number ?? '—'}
                        </Typography>
                      </TableCell>
                      <TableCell align="right">
                        <Typography variant="body2">
                          {request.application?.amount_requested_minor != null
                            ? formatNaira(request.application.amount_requested_minor)
                            : '—'}
                        </Typography>
                      </TableCell>
                      <TableCell>
                        <Typography variant="body2" color="text.secondary">
                          {request.requested_at ? formatDate(request.requested_at) : '—'}
                        </Typography>
                      </TableCell>
                      <TableCell>
                        <StatusPill status={request.status} />
                      </TableCell>
                      <TableCell align="center">
                        {request.status === 'pending' ? (
                          <Stack direction="row" spacing={1} sx={{ justifyContent: 'center' }}>
                            <Button
                              size="small"
                              color="success"
                              variant="outlined"
                              disabled={respondMutation.isPending}
                              onClick={() => setRespondTarget({ id: request.id, applicationNumber: request.application?.application_number ?? '' })}
                            >
                              Accept
                            </Button>
                            <Button
                              size="small"
                              color="inherit"
                              disabled={respondMutation.isPending}
                              onClick={() =>
                                respondMutation.mutate({ id: request.id, action: 'decline' })
                              }
                            >
                              Decline
                            </Button>
                          </Stack>
                        ) : (
                          <Typography variant="body2" color="text.secondary">
                            —
                          </Typography>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          </CardContent>
        </Card>
      )}

      <Dialog open={respondTarget !== null} onClose={() => setRespondTarget(null)} maxWidth="xs" fullWidth>
        <DialogTitle>Accept guarantor request</DialogTitle>
        <DialogContent>
          <DialogContentText sx={{ mb: 2 }}>
            You are accepting the request to guarantee loan{' '}
            {respondTarget?.applicationNumber ? `application ${respondTarget.applicationNumber}` : 'application'}.
            This means you will be recorded as a guarantor.
          </DialogContentText>
          <TextField
            label="Response note (optional)"
            value={responseNote}
            onChange={(e) => setResponseNote(e.target.value)}
            fullWidth
            multiline
            minRows={2}
            size="small"
          />
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setRespondTarget(null)} disabled={respondMutation.isPending}>
            Cancel
          </Button>
          <Button
            color="success"
            variant="contained"
            disabled={respondMutation.isPending}
            onClick={() => respondTarget && respondMutation.mutate({ id: respondTarget.id, action: 'accept' })}
          >
            {respondMutation.isPending ? 'Updating...' : 'Accept request'}
          </Button>
        </DialogActions>
      </Dialog>
    </SimplePageContainer>
  )
}