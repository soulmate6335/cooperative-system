import type { ReactNode } from 'react'
import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Chip from '@mui/material/Chip'
import Dialog from '@mui/material/Dialog'
import DialogActions from '@mui/material/DialogActions'
import DialogContent from '@mui/material/DialogContent'
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
import AddIcon from '@mui/icons-material/Add'
import EditIcon from '@mui/icons-material/Edit'
import EventAvailableIcon from '@mui/icons-material/EventAvailable'

import { PageContainer } from '../../components/common/PageContainer'
import { EmptyState } from '../../components/common/EmptyState'
import { ErrorState } from '../../components/common/ErrorState'
import { TableSkeleton } from '../../components/common/Skeletons'
import {
  createCommitteeMeeting,
  listCommitteeMeetings,
  updateCommitteeMeeting,
} from '../../services/meetings'
import type { CommitteeMeeting } from '../../types'
import { formatDate } from '../../utils/format'
import { getErrorMessage } from '../../utils/errors'

interface MeetingFormState {
  meeting_date: string
  meeting_type: string
  cutoff_days: string
  notes: string
}

const EMPTY_FORM: MeetingFormState = { meeting_date: '', meeting_type: 'loan_review', cutoff_days: '', notes: '' }

export function CommitteeMeetingsPage(): ReactNode {
  const queryClient = useQueryClient()
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editing, setEditing] = useState<CommitteeMeeting | null>(null)
  const [form, setForm] = useState<MeetingFormState>(EMPTY_FORM)
  const [error, setError] = useState<string | null>(null)

  const { data: result, isLoading, error: queryError, refetch } = useQuery({
    queryKey: ['admin-committee-meetings'],
    queryFn: () => listCommitteeMeetings({ per_page: 50 }),
  })

  const invalidate = (): void => {
    void queryClient.invalidateQueries({ queryKey: ['admin-committee-meetings'] })
  }

  const saveMutation = useMutation({
    mutationFn: () => {
      const payload = {
        meeting_date: form.meeting_date,
        meeting_type: form.meeting_type,
        cutoff_days: Number(form.cutoff_days),
        notes: form.notes || null,
      }
      return editing ? updateCommitteeMeeting(editing.id, payload) : createCommitteeMeeting(payload)
    },
    onSuccess: () => {
      setDialogOpen(false)
      setEditing(null)
      setForm(EMPTY_FORM)
      setError(null)
      invalidate()
    },
    onError: (err) => setError(getErrorMessage(err, 'Failed to save the meeting.')),
  })

  const openCreate = (): void => {
    setEditing(null)
    setForm(EMPTY_FORM)
    setError(null)
    setDialogOpen(true)
  }

  const openEdit = (meeting: CommitteeMeeting): void => {
    setEditing(meeting)
    setForm({
      meeting_date: meeting.meeting_date ?? '',
      meeting_type: meeting.meeting_type,
      cutoff_days: String(meeting.cutoff_days),
      notes: meeting.notes ?? '',
    })
    setError(null)
    setDialogOpen(true)
  }

  const update = (patch: Partial<MeetingFormState>): void => {
    setForm((prev) => ({ ...prev, ...patch }))
  }

  if (isLoading) {
    return (
      <PageContainer title="Committee Meetings" subtitle="Schedule and manage committee meetings">
        <TableSkeleton rows={6} columns={4} />
      </PageContainer>
    )
  }

  if (queryError) {
    return (
      <PageContainer title="Committee Meetings" subtitle="Schedule and manage committee meetings">
        <ErrorState message={getErrorMessage(queryError, 'Failed to load committee meetings.')} onRetry={() => refetch()} />
      </PageContainer>
    )
  }

  const meetings = result?.data ?? []

  return (
    <PageContainer
      title="Committee Meetings"
      subtitle="Schedule and manage committee meetings"
      actions={
        <Button variant="contained" startIcon={<AddIcon />} onClick={openCreate} sx={{ textTransform: 'none' }}>
          Schedule meeting
        </Button>
      }
    >
      {meetings.length === 0 ? (
        <EmptyState
          icon={EventAvailableIcon}
          title="No committee meetings yet"
          description="Schedule a meeting to give the loan committee a review window for applications."
        />
      ) : (
        <Card variant="outlined">
          <CardContent>
            <TableContainer>
              <Table>
                <TableHead>
                  <TableRow>
                    <TableCell>Meeting Date</TableCell>
                    <TableCell>Type</TableCell>
                    <TableCell align="right">Cutoff (days)</TableCell>
                    <TableCell>Status</TableCell>
                    <TableCell align="center">Actions</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {meetings.map((meeting) => (
                    <TableRow key={meeting.id} hover>
                      <TableCell>
                        <Typography variant="body2" sx={{ fontWeight: 600 }}>
                          {meeting.meeting_date ? formatDate(meeting.meeting_date) : '—'}
                        </Typography>
                        {meeting.notes ? (
                          <Typography variant="caption" color="text.secondary">
                            {meeting.notes}
                          </Typography>
                        ) : null}
                      </TableCell>
                      <TableCell>
                        <Typography variant="body2">{meeting.meeting_type}</Typography>
                      </TableCell>
                      <TableCell align="right">
                        <Typography variant="body2">{meeting.cutoff_days}</Typography>
                      </TableCell>
                      <TableCell>
                        <Chip
                          size="small"
                          label={meeting.status === 'upcoming' ? 'Upcoming' : meeting.status}
                          color={meeting.status === 'upcoming' ? 'primary' : meeting.status === 'completed' ? 'success' : 'default'}
                          variant="outlined"
                        />
                      </TableCell>
                      <TableCell align="center">
                        <Button size="small" startIcon={<EditIcon />} onClick={() => openEdit(meeting)}>
                          Edit
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          </CardContent>
        </Card>
      )}

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>{editing ? 'Edit Committee Meeting' : 'Schedule Committee Meeting'}</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            {error ? (
              <Typography variant="body2" color="error">
                {error}
              </Typography>
            ) : null}
            <TextField
              label="Meeting date"
              type="date"
              slotProps={{ inputLabel: { shrink: true } }}
              value={form.meeting_date}
              onChange={(e) => update({ meeting_date: e.target.value })}
              fullWidth
              size="small"
            />
            <TextField
              label="Meeting type"
              value={form.meeting_type}
              onChange={(e) => update({ meeting_type: e.target.value })}
              fullWidth
              size="small"
              placeholder="e.g. loan_review"
            />
            <TextField
              label="Cutoff days (applications before this window)" 
              type="number"
              value={form.cutoff_days}
              onChange={(e) => update({ cutoff_days: e.target.value })}
              fullWidth
              size="small"
            />
            <TextField
              label="Notes"
              value={form.notes}
              onChange={(e) => update({ notes: e.target.value })}
              fullWidth
              multiline
              minRows={3}
              size="small"
            />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDialogOpen(false)} disabled={saveMutation.isPending}>
            Cancel
          </Button>
          <Button
            variant="contained"
            onClick={() => saveMutation.mutate()}
            disabled={saveMutation.isPending || !form.meeting_date}
          >
            {saveMutation.isPending ? 'Saving...' : editing ? 'Save changes' : 'Schedule meeting'}
          </Button>
        </DialogActions>
      </Dialog>

      <Typography variant="body2" color="text.secondary" sx={{ mt: 2 }}>
        Meeting details are linked to loan applications; applications submitted after the cutoff date are held for the
        next meeting.
      </Typography>
    </PageContainer>
  )
}