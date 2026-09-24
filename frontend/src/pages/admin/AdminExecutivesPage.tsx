import type { ReactNode } from 'react'
import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import Avatar from '@mui/material/Avatar'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Chip from '@mui/material/Chip'
import Dialog from '@mui/material/Dialog'
import DialogActions from '@mui/material/DialogActions'
import DialogContent from '@mui/material/DialogContent'
import DialogTitle from '@mui/material/DialogTitle'
import FormHelperText from '@mui/material/FormHelperText'
import IconButton from '@mui/material/IconButton'
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
import CloudUploadIcon from '@mui/icons-material/CloudUpload'
import EditIcon from '@mui/icons-material/Edit'
import GroupsIcon from '@mui/icons-material/Groups'
import VisibilityOffIcon from '@mui/icons-material/VisibilityOff'
import VisibilityIcon from '@mui/icons-material/Visibility'

import { PageContainer } from '../../components/common/PageContainer'
import { EmptyState } from '../../components/common/EmptyState'
import { ErrorState } from '../../components/common/ErrorState'
import { TableSkeleton } from '../../components/common/Skeletons'
import {
  createExecutive,
  listAdminExecutives,
  setExecutiveVisibility,
  updateExecutive,
  type SaveExecutivePayload,
} from '../../services/content'
import type { AdminExecutive } from '../../types'
import { getErrorMessage } from '../../utils/errors'

interface ExecutiveFormState {
  name: string
  position: string
  biography: string
  display_order: string
  photo: File | null
}

const EMPTY_FORM: ExecutiveFormState = {
  name: '',
  position: '',
  biography: '',
  display_order: '0',
  photo: null,
}

const ACCEPTED_IMAGE = 'image/jpeg,image/png,image/webp'

export function AdminExecutivesPage(): ReactNode {
  const queryClient = useQueryClient()
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editing, setEditing] = useState<AdminExecutive | null>(null)
  const [form, setForm] = useState<ExecutiveFormState>(EMPTY_FORM)
  const [error, setError] = useState<string | null>(null)
  const [busyId, setBusyId] = useState<string | null>(null)

  const executivesQuery = useQuery({
    queryKey: ['admin-executives'],
    queryFn: () => listAdminExecutives({ per_page: 50 }),
  })

  const invalidate = (): void => {
    void queryClient.invalidateQueries({ queryKey: ['admin-executives'] })
  }

  const saveMutation = useMutation({
    mutationFn: () => {
      const payload: SaveExecutivePayload = {
        name: form.name,
        position: form.position,
        biography: form.biography || null,
        display_order: Number(form.display_order),
        photo: form.photo,
      }
      return editing ? updateExecutive(editing.id, payload) : createExecutive(payload)
    },
    onSuccess: () => {
      setDialogOpen(false)
      setEditing(null)
      setForm(EMPTY_FORM)
      setError(null)
      invalidate()
    },
    onError: (err) => setError(getErrorMessage(err, 'Failed to save the executive.')),
  })

  const toggleVisibility = async (executive: AdminExecutive): Promise<void> => {
    setBusyId(executive.id)
    try {
      await setExecutiveVisibility(executive.id, !executive.is_visible)
      invalidate()
    } finally {
      setBusyId(null)
    }
  }

  const openCreate = (): void => {
    setEditing(null)
    setForm(EMPTY_FORM)
    setError(null)
    setDialogOpen(true)
  }

  const openEdit = (executive: AdminExecutive): void => {
    setEditing(executive)
    setForm({
      name: executive.name,
      position: executive.position,
      biography: executive.biography ?? '',
      display_order: String(executive.display_order),
      photo: null,
    })
    setError(null)
    setDialogOpen(true)
  }

  const update = (patch: Partial<ExecutiveFormState>): void => {
    setForm((prev) => ({ ...prev, ...patch }))
  }

  const executives = executivesQuery.data?.data ?? []

  return (
    <PageContainer
      title="Executives"
      subtitle="Leadership profiles shown on the public website"
      actions={
        <Button variant="contained" startIcon={<AddIcon />} onClick={openCreate} sx={{ textTransform: 'none' }}>
          Add executive
        </Button>
      }
    >
      {executivesQuery.isPending ? (
        <TableSkeleton rows={6} columns={5} />
      ) : executivesQuery.isError ? (
        <ErrorState message={getErrorMessage(executivesQuery.error)} onRetry={() => executivesQuery.refetch()} />
      ) : executives.length === 0 ? (
        <EmptyState
          icon={GroupsIcon}
          title="No executive profiles"
          description="Add leadership profiles to introduce the cooperative's executive team on the public website."
        />
      ) : (
        <Card variant="outlined">
          <CardContent>
            <TableContainer>
              <Table>
                <TableHead>
                  <TableRow>
                    <TableCell>Executive</TableCell>
                    <TableCell>Position</TableCell>
                    <TableCell align="right">Order</TableCell>
                    <TableCell>Visibility</TableCell>
                    <TableCell align="center">Actions</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {executives.map((executive) => (
                    <TableRow key={executive.id} hover>
                      <TableCell>
                        <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}>
                          <Avatar
                            src={executive.photo_url ?? undefined}
                            alt={executive.name}
                            sx={{ width: 40, height: 40, bgcolor: 'primary.light' }}
                          >
                            {executive.name.charAt(0).toUpperCase()}
                          </Avatar>
                          <Box>
                            <Typography variant="body2" sx={{ fontWeight: 600 }}>
                              {executive.name}
                            </Typography>
                            {executive.biography ? (
                              <Typography variant="caption" color="text.secondary" sx={{ display: '-webkit-box', WebkitLineClamp: 1, WebkitBoxOrient: 'vertical', overflow: 'hidden', maxWidth: 420 }}>
                                {executive.biography}
                              </Typography>
                            ) : null}
                          </Box>
                        </Stack>
                      </TableCell>
                      <TableCell>
                        <Typography variant="body2">{executive.position}</Typography>
                      </TableCell>
                      <TableCell align="right">
                        <Typography variant="body2">{executive.display_order}</Typography>
                      </TableCell>
                      <TableCell>
                        <Chip
                          size="small"
                          label={executive.is_visible ? 'Visible' : 'Hidden'}
                          color={executive.is_visible ? 'success' : 'default'}
                          variant="outlined"
                        />
                      </TableCell>
                      <TableCell align="center">
                        <Stack direction="row" spacing={0.5} sx={{ justifyContent: 'center' }}>
                          <IconButton
                            size="small"
                            title={executive.is_visible ? 'Hide from public' : 'Show publicly'}
                            onClick={() => void toggleVisibility(executive)}
                            disabled={busyId === executive.id}
                          >
                            {executive.is_visible ? <VisibilityOffIcon fontSize="small" /> : <VisibilityIcon fontSize="small" />}
                          </IconButton>
                          <Button size="small" startIcon={<EditIcon />} onClick={() => openEdit(executive)}>
                            Edit
                          </Button>
                        </Stack>
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
        <DialogTitle>{editing ? 'Edit Executive' : 'Add Executive'}</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            {error ? (
              <Typography variant="body2" color="error">
                {error}
              </Typography>
            ) : null}
            <Stack direction="row" spacing={2} sx={{ alignItems: 'center' }}>
              {editing?.photo_url || form.photo ? (
                <Avatar
                  src={form.photo ? undefined : (editing?.photo_url ?? undefined)}
                  alt="Photo preview"
                  variant="rounded"
                  sx={{ width: 72, height: 72, bgcolor: 'primary.light', fontSize: 24 }}
                >
                  {form.photo ? 'New' : editing ? editing.name.charAt(0).toUpperCase() : '?'}
                </Avatar>
              ) : null}
              <Box sx={{ flexGrow: 1 }}>
                <input
                  id="executive-photo"
                  type="file"
                  accept={ACCEPTED_IMAGE}
                  style={{ display: 'none' }}
                  onChange={(event) => update({ photo: event.target.files?.[0] ?? null })}
                />
                <label htmlFor="executive-photo">
                  <Button component="span" variant="outlined" startIcon={<CloudUploadIcon />} fullWidth sx={{ py: 1 }}>
                    {form.photo ? form.photo.name : 'Upload photo (optional)'}
                  </Button>
                </label>
                <FormHelperText>JPG, PNG or WebP, up to 2 MB{editing ? ' — leave empty to keep the current photo' : ''}</FormHelperText>
              </Box>
            </Stack>
            <TextField label="Full name" value={form.name} onChange={(e) => update({ name: e.target.value })} fullWidth size="small" />
            <TextField label="Position" value={form.position} onChange={(e) => update({ position: e.target.value })} fullWidth size="small" placeholder="e.g. President, Treasurer" />
            <TextField label="Biography (optional)" value={form.biography} onChange={(e) => update({ biography: e.target.value })} fullWidth multiline minRows={3} size="small" />
            <TextField
              label="Display order (lower shows first)"
              type="number"
              value={form.display_order}
              onChange={(e) => update({ display_order: e.target.value })}
              fullWidth
              size="small"
            />
            <Typography variant="caption" color="text.secondary">
              New executives are visible publicly by default; use the visibility toggle to hide a profile.
            </Typography>
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDialogOpen(false)} disabled={saveMutation.isPending}>
            Cancel
          </Button>
          <Button variant="contained" onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending || !form.name || !form.position}>
            {saveMutation.isPending ? 'Saving…' : editing ? 'Save changes' : 'Add executive'}
          </Button>
        </DialogActions>
      </Dialog>
    </PageContainer>
  )
}