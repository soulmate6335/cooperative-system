import type { ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Dialog from '@mui/material/Dialog'
import DialogActions from '@mui/material/DialogActions'
import DialogContent from '@mui/material/DialogContent'
import DialogContentText from '@mui/material/DialogContentText'
import DialogTitle from '@mui/material/DialogTitle'
import Divider from '@mui/material/Divider'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import { useParams } from 'react-router-dom'
import { useState } from 'react'

import { PageContainer } from '../../components/common/PageContainer'
import { formatDate } from '../../utils/format'
import { getMemberApplication, approveMemberApplication, rejectMemberApplication } from '../../services/membership'
import { statusStyle } from '../../utils/status'
import { StatusPill } from '../../components/common/StatusPill'
import { ErrorState } from '../../components/common/ErrorState'
import { getErrorMessage } from '../../utils/errors'

export function MembershipApplicationDetailPage(): ReactNode {
  const { id } = useParams<{ id: string }>()
  const [rejectDialogOpen, setRejectDialogOpen] = useState(false)
  const [rejectReason, setRejectReason] = useState('')
  const [rejecting, setRejecting] = useState(false)

  const { data: application, isLoading, error, refetch } = useQuery({
    queryKey: ['admin-membership-application', id],
    queryFn: () => getMemberApplication(id!),
    enabled: !!id,
  })

  const handleApprove = async () => {
    try {
      await approveMemberApplication(id!)
      refetch()
    } catch (unknownError) {
      alert(getErrorMessage(unknownError, 'Failed to approve application.'))
    }
  }

  const handleReject = async () => {
    if (!rejectReason.trim()) return
    setRejecting(true)
    try {
      await rejectMemberApplication(id!, rejectReason)
      setRejectDialogOpen(false)
      setRejectReason('')
      refetch()
    } catch (unknownError) {
      alert(getErrorMessage(unknownError, 'Failed to reject application.'))
    } finally {
      setRejecting(false)
    }
  }

  if (isLoading) {
    return (
      <PageContainer title="Application Detail" subtitle="Review membership application">
        <div style={{ display: 'flex', justifyContent: 'center', padding: '3rem' }}>
          <Typography>Loading application...</Typography>
        </div>
      </PageContainer>
    )
  }

  if (error || !application) {
    return (
      <PageContainer title="Application Detail" subtitle="Review membership application">
        <ErrorState message="Failed to load application details." onRetry={() => refetch()} />
      </PageContainer>
    )
  }

  const statusInfo = statusStyle(application.status)

  return (
    <PageContainer
      title={`Application: ${application.application_number}`}
      subtitle={`Status: ${statusInfo.label}`}
      backTo="/admin/membership-applications"
      actions={
        application.status === 'pending' ? (
          <Stack direction="row" spacing={1.5}>
            <Button variant="outlined" onClick={() => setRejectDialogOpen(true)} color="error">
              Reject
            </Button>
            <Button variant="contained" onClick={handleApprove} color="success">
              Approve
            </Button>
          </Stack>
        ) : null
      }
    >
      <Stack spacing={3}>
        <Card variant="outlined">
          <CardContent>
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={3} sx={{ flexWrap: 'wrap' }}>
              <Box sx={{ flexGrow: 1, minWidth: 280 }}>
                <Typography variant="h6" gutterBottom>
                  Personal Information
                </Typography>
                <Divider sx={{ mb: 2 }} />
                <Stack spacing={1.5}>
                  <Box>
                    <Typography variant="body2" color="text.secondary">Full Name</Typography>
                    <Typography variant="body1" sx={{ fontWeight: 500 }}>{application.full_name}</Typography>
                  </Box>
                  <Box>
                    <Typography variant="body2" color="text.secondary">Email</Typography>
                    <Typography variant="body1" sx={{ fontWeight: 500 }}>{application.email}</Typography>
                  </Box>
                  <Box>
                    <Typography variant="body2" color="text.secondary">Phone</Typography>
                    <Typography variant="body1" sx={{ fontWeight: 500 }}>{application.phone}</Typography>
                  </Box>
                  <Box>
                    <Typography variant="body2" color="text.secondary">Address</Typography>
                    <Typography variant="body1" sx={{ fontWeight: 500 }}>{application.address}</Typography>
                  </Box>
                  <Box>
                    <Typography variant="body2" color="text.secondary">Date of Birth</Typography>
                    <Typography variant="body1" sx={{ fontWeight: 500 }}>{application.date_of_birth ? formatDate(application.date_of_birth) : '—'}</Typography>
                  </Box>
                  <Box>
                    <Typography variant="body2" color="text.secondary">Occupation</Typography>
                    <Typography variant="body1" sx={{ fontWeight: 500 }}>{application.occupation}</Typography>
                  </Box>
                  <Box>
                    <Typography variant="body2" color="text.secondary">Department</Typography>
                    <Typography variant="body1" sx={{ fontWeight: 500 }}>{application.department ?? '—'}</Typography>
                  </Box>
                </Stack>
              </Box>
              <Box sx={{ flexGrow: 1, minWidth: 280 }}>
                <Typography variant="h6" gutterBottom>
                  Application Details
                </Typography>
                <Divider sx={{ mb: 2 }} />
                <Stack spacing={1.5}>
                  <Box>
                    <Typography variant="body2" color="text.secondary">Application Number</Typography>
                    <Typography variant="body1" sx={{ fontWeight: 500 }}>{application.application_number}</Typography>
                  </Box>
                  <Box>
                    <Typography variant="body2" color="text.secondary">Status</Typography>
                    <StatusPill status={application.status} />
                  </Box>
                  <Box>
                    <Typography variant="body2" color="text.secondary">Submitted</Typography>
                    <Typography variant="body1" sx={{ fontWeight: 500 }}>{application.submitted_at ? formatDate(application.submitted_at) : '—'}</Typography>
                  </Box>
                  {application.reviewed_at && (
                    <Box>
                      <Typography variant="body2" color="text.secondary">Reviewed</Typography>
                      <Typography variant="body1" sx={{ fontWeight: 500 }}>{formatDate(application.reviewed_at)}</Typography>
                    </Box>
                  )}
                  {application.reviewer && (
                    <Box>
                      <Typography variant="body2" color="text.secondary">Reviewed By</Typography>
                      <Typography variant="body1" sx={{ fontWeight: 500 }}>{application.reviewer.name}</Typography>
                    </Box>
                  )}
                  {application.rejection_reason && (
                    <Box>
                      <Typography variant="body2" color="text.secondary">Rejection Reason</Typography>
                      <Typography variant="body1" sx={{ fontWeight: 500, color: 'error.main' }}>{application.rejection_reason}</Typography>
                    </Box>
                  )}
                </Stack>
              </Box>
            </Stack>
          </CardContent>
        </Card>

        <Dialog
          open={rejectDialogOpen}
          onClose={() => setRejectDialogOpen(false)}
          maxWidth="md"
          fullWidth
        >
          <DialogTitle>Reject Membership Application</DialogTitle>
          <DialogContent>
            <DialogContentText>
              Please provide a reason for rejecting this membership application. The applicant will be notified.
            </DialogContentText>
            <Box component="form" sx={{ mt: 2 }}>
              <label htmlFor="rejection-reason" style={{ display: 'block', marginBottom: 8 }}>
                Rejection Reason
              </label>
              <textarea
                id="rejection-reason"
                rows={4}
                style={{ width: '100%', padding: 12, borderRadius: 4, background: 'transparent', color: 'inherit', border: '1px solid var(--cs-divider)' }}
                value={rejectReason}
                onChange={(e) => setRejectReason(e.target.value)}
                placeholder="Enter the reason for rejection..."
              />
            </Box>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setRejectDialogOpen(false)} disabled={rejecting}>
              Cancel
            </Button>
            <Button
              color="error"
              variant="contained"
              onClick={handleReject}
              disabled={rejecting || !rejectReason.trim()}
            >
              {rejecting ? 'Rejecting...' : 'Reject Application'}
            </Button>
          </DialogActions>
        </Dialog>
      </Stack>
    </PageContainer>
  )
}
