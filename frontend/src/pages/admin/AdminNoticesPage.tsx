import type { ReactNode } from 'react'
import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Chip from '@mui/material/Chip'
import Dialog from '@mui/material/Dialog'
import DialogActions from '@mui/material/DialogActions'
import DialogContent from '@mui/material/DialogContent'
import DialogTitle from '@mui/material/DialogTitle'
import MenuItem from '@mui/material/MenuItem'
import Pagination from '@mui/material/Pagination'
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
import ArchiveIcon from '@mui/icons-material/Archive'
import CampaignIcon from '@mui/icons-material/Campaign'
import EditIcon from '@mui/icons-material/Edit'
import PublishIcon from '@mui/icons-material/Publish'
import SearchIcon from '@mui/icons-material/Search'

import { PageContainer } from '../../components/common/PageContainer'
import { EmptyState } from '../../components/common/EmptyState'
import { ErrorState } from '../../components/common/ErrorState'
import { TableSkeleton } from '../../components/common/Skeletons'
import {
  archiveNotice,
  createNotice,
  listAdminNotices,
  publishNotice,
  updateNotice,
  type SaveNoticePayload,
} from '../../services/content'
import type { AdminNotice, NoticeStatus, NoticeVisibility } from '../../types'
import { formatDate } from '../../utils/format'
import { getErrorMessage } from '../../utils/errors'

interface NoticeFormState {
  title: string
  body: string
  excerpt: string
  visibility: NoticeVisibility
  publish_at: string
  expires_at: string
}

const EMPTY_FORM: NoticeFormState = {
  title: '',
  body: '',
  excerpt: '',
  visibility: 'public',
  publish_at: '',
  expires_at: '',
}

function toLocalInput(value: string | null | undefined): string {
  if (!value) {
    return ''
  }
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return ''
  }
  const pad = (part: number): string => String(part).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

function statusColor(status: NoticeStatus): 'primary' | 'success' | 'default' {
  if (status === 'published') {
    return 'success'
  }
  if (status === 'draft') {
    return 'primary'
  }
  return 'default'
}

export function AdminNoticesPage(): ReactNode {
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const [statusFilter, setStatusFilter] = useState<string>('')
  const [search, setSearch] = useState('')
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editing, setEditing] = useState<AdminNotice | null>(null)
  const [form, setForm] = useState<NoticeFormState>(EMPTY_FORM)
  const [error, setError] = useState<string | null>(null)

  const noticesQuery = useQuery({
    queryKey: ['admin-notices', page, statusFilter, search],
    queryFn: () =>
      listAdminNotices({
        page,
        per_page: 10,
        status: statusFilter === '' ? undefined : (statusFilter as NoticeStatus),
        search: search === '' ? undefined : search,
      }),
  })

  const invalidate = (): void => {
    void queryClient.invalidateQueries({ queryKey: ['admin-notices'] })
  }

  const saveMutation = useMutation({
    mutationFn: () => {
      const payload: SaveNoticePayload = {
        title: form.title,
        body: form.body,
        excerpt: form.excerpt || null,
        visibility: form.visibility,
        publish_at: form.publish_at ? new Date(form.publish_at).toISOString() : null,
        expires_at: form.expires_at ? new Date(form.expires_at).toISOString() : null,
      }
      return editing ? updateNotice(editing.id, payload) : createNotice(payload)
    },
    onSuccess: () => {
      setDialogOpen(false)
      setEditing(null)
      setForm(EMPTY_FORM)
      setError(null)
      invalidate()
    },
    onError: (err) => setError(getErrorMessage(err, 'Failed to save the notice.')),
  })

  const publishMutation = useMutation({
    mutationFn: (notice: AdminNotice) => publishNotice(notice.id),
    onSuccess: () => invalidate(),
  })

  const archiveMutation = useMutation({
    mutationFn: (notice: AdminNotice) => archiveNotice(notice.id),
    onSuccess: () => invalidate(),
  })

  const openCreate = (): void => {
    setEditing(null)
    setForm(EMPTY_FORM)
    setError(null)
    setDialogOpen(true)
  }

  const openEdit = (notice: AdminNotice): void => {
    setEditing(notice)
    setForm({
      title: notice.title,
      body: notice.body,
      excerpt: notice.excerpt ?? '',
      visibility: notice.visibility,
      publish_at: toLocalInput(notice.publish_at),
      expires_at: toLocalInput(notice.expires_at),
    })
    setError(null)
    setDialogOpen(true)
  }

  const update = (patch: Partial<NoticeFormState>): void => {
    setForm((prev) => ({ ...prev, ...patch }))
  }

  const notices = noticesQuery.data?.data ?? []
  const meta = noticesQuery.data?.meta

  return (
    <PageContainer
      title="Notices"
      subtitle="Create, publish and archive organization announcements"
      actions={
        <Button variant="contained" startIcon={<AddIcon />} onClick={openCreate} sx={{ textTransform: 'none' }}>
          New notice
        </Button>
      }
    >
      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ mb: 2 }}>
        <TextField
          size="small"
          placeholder="Search title or body…"
          value={search}
          onChange={(e) => {
            setSearch(e.target.value)
            setPage(1)
          }}
          slotProps={{ input: { startAdornment: <SearchIcon fontSize="small" sx={{ mr: 1, color: 'text.secondary' }} /> } }}
          sx={{ width: { xs: '100%', sm: 320 } }}
        />
        <TextField
          select
          size="small"
          label="Status"
          value={statusFilter}
          onChange={(e) => {
            setStatusFilter(e.target.value)
            setPage(1)
          }}
          sx={{ width: { xs: '100%', sm: 180 } }}
        >
          <MenuItem value="">All statuses</MenuItem>
          <MenuItem value="draft">Draft</MenuItem>
          <MenuItem value="published">Published</MenuItem>
          <MenuItem value="archived">Archived</MenuItem>
        </TextField>
      </Stack>

      {noticesQuery.isPending ? (
        <TableSkeleton rows={6} columns={5} />
      ) : noticesQuery.isError ? (
        <ErrorState message={getErrorMessage(noticesQuery.error)} onRetry={() => noticesQuery.refetch()} />
      ) : notices.length === 0 ? (
        <EmptyState
          icon={CampaignIcon}
          title="No notices"
          description="Create a notice to keep members and the public informed."
        />
      ) : (
        <Card variant="outlined">
          <CardContent>
            <TableContainer>
              <Table>
                <TableHead>
                  <TableRow>
                    <TableCell>Title</TableCell>
                    <TableCell>Visibility</TableCell>
                    <TableCell>Status</TableCell>
                    <TableCell>Published</TableCell>
                    <TableCell align="right">Actions</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {notices.map((notice) => (
                    <TableRow key={notice.id} hover>
                      <TableCell>
                        <Typography variant="body2" sx={{ fontWeight: 600 }}>
                          {notice.title}
                        </Typography>
                        {notice.excerpt ? (
                          <Typography variant="caption" color="text.secondary">
                            {notice.excerpt}
                          </Typography>
                        ) : null}
                      </TableCell>
                      <TableCell>
                        <Chip size="small" label={notice.visibility === 'members' ? 'Members' : 'Public'} variant="outlined" />
                      </TableCell>
                      <TableCell>
                        <Chip size="small" color={statusColor(notice.status)} label={notice.status} variant="outlined" />
                      </TableCell>
                      <TableCell>
                        <Typography variant="body2">{notice.publish_at ? formatDate(notice.publish_at) : '—'}</Typography>
                        {notice.expires_at ? (
                          <Typography variant="caption" color="text.secondary">
                            expires {formatDate(notice.expires_at)}
                          </Typography>
                        ) : null}
                      </TableCell>
                      <TableCell align="right">
                        <Stack direction="row" spacing={0.5} sx={{ justifyContent: 'flex-end' }}>
                          {notice.status === 'draft' ? (
                            <Button
                              size="small"
                              startIcon={<PublishIcon />}
                              onClick={() => publishMutation.mutate(notice)}
                              disabled={publishMutation.isPending}
                            >
                              Publish
                            </Button>
                          ) : null}
                          {notice.status === 'published' ? (
                            <Button size="small" startIcon={<ArchiveIcon />} onClick={() => archiveMutation.mutate(notice)} disabled={archiveMutation.isPending}>
                              Archive
                            </Button>
                          ) : null}
                          <Button size="small" startIcon={<EditIcon />} onClick={() => openEdit(notice)}>
                            Edit
                          </Button>
                        </Stack>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
            {meta && meta.last_page > 1 ? (
              <Stack sx={{ alignItems: 'center', mt: 2 }}>
                <Pagination count={meta.last_page} page={page} onChange={(_, next) => setPage(next)} color="primary" />
              </Stack>
            ) : null}
          </CardContent>
        </Card>
      )}

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} maxWidth="md" fullWidth>
        <DialogTitle>{editing ? 'Edit Notice' : 'New Notice'}</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            {error ? (
              <Typography variant="body2" color="error">
                {error}
              </Typography>
            ) : null}
            <TextField
              label="Title"
              value={form.title}
              onChange={(e) => update({ title: e.target.value })}
              fullWidth
              size="small"
            />
            <TextField
              label="Excerpt (optional, shown in lists)"
              value={form.excerpt}
              onChange={(e) => update({ excerpt: e.target.value })}
              fullWidth
              size="small"
            />
            <TextField
              label="Body"
              value={form.body}
              onChange={(e) => update({ body: e.target.value })}
              fullWidth
              multiline
              minRows={5}
              size="small"
            />
            <TextField
              select
              label="Visibility"
              value={form.visibility}
              onChange={(e) => update({ visibility: e.target.value as NoticeVisibility })}
              fullWidth
              size="small"
            >
              <MenuItem value="public">Public (anyone)</MenuItem>
              <MenuItem value="members">Members only</MenuItem>
            </TextField>
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
              <TextField
                label="Publish date / time (optional)"
                type="datetime-local"
                slotProps={{ inputLabel: { shrink: true } }}
                value={form.publish_at}
                onChange={(e) => update({ publish_at: e.target.value })}
                fullWidth
                size="small"
              />
              <TextField
                label="Expiry date / time (optional)"
                type="datetime-local"
                slotProps={{ inputLabel: { shrink: true } }}
                value={form.expires_at}
                onChange={(e) => update({ expires_at: e.target.value })}
                fullWidth
                size="small"
              />
            </Stack>
            <Typography variant="caption" color="text.secondary">
              Drafts are saved as private; publishing activates the notice and, for members-only notices, notifies every
              active member once.
            </Typography>
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDialogOpen(false)} disabled={saveMutation.isPending}>
            Cancel
          </Button>
          <Button variant="contained" onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending || !form.title || !form.body}>
            {saveMutation.isPending ? 'Saving…' : editing ? 'Save changes' : 'Create draft'}
          </Button>
        </DialogActions>
      </Dialog>
    </PageContainer>
  )
}