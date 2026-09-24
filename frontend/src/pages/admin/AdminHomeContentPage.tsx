import type { ReactNode } from 'react'
import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import Avatar from '@mui/material/Avatar'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import FormHelperText from '@mui/material/FormHelperText'
import Stack from '@mui/material/Stack'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import CloudUploadIcon from '@mui/icons-material/CloudUpload'
import HomeIcon from '@mui/icons-material/Home'

import { ErrorState } from '../../components/common/ErrorState'
import { PageContainer } from '../../components/common/PageContainer'
import { TableSkeleton } from '../../components/common/Skeletons'
import { fetchAdminHomeContent, updateAdminHomeContent } from '../../services/content'
import type { OrganizationContent } from '../../types'
import { getErrorMessage } from '../../utils/errors'

const ACCEPTED_IMAGE = 'image/jpeg,image/png,image/webp'

interface EditorProps {
  initial: OrganizationContent | null
  onSaved: () => void
  onError: (message: string) => void
}

/**
 * The editable form, remounted via `key` when the server record changes so it
 * always hydrates from server data without effects or render-phase setState.
 */
function HomeContentEditor({ initial, onSaved, onError }: EditorProps): ReactNode {
  const queryClient = useQueryClient()
  const [heroTitle, setHeroTitle] = useState(initial?.hero_title ?? '')
  const [heroDescription, setHeroDescription] = useState(initial?.hero_description ?? '')
  const [introduction, setIntroduction] = useState(initial?.introduction ?? '')
  const [heroImage, setHeroImage] = useState<File | null>(null)

  const saveMutation = useMutation({
    mutationFn: () =>
      updateAdminHomeContent({
        hero_title: heroTitle || null,
        hero_description: heroDescription || null,
        introduction: introduction || null,
        hero_image: heroImage,
      }),
    onSuccess: () => {
      setHeroImage(null)
      onSaved()
      void queryClient.invalidateQueries({ queryKey: ['admin-home-content'] })
    },
    onError: (err) => onError(getErrorMessage(err, 'Failed to save the homepage content.')),
  })

  const currentHeroUrl = initial?.hero_image_url ?? null

  return (
    <Card variant="outlined">
      <CardContent>
        <Stack spacing={2.5}>
          <Stack spacing={0.5}>
            <Typography variant="subtitle2">Hero image</Typography>
            <Stack direction="row" spacing={2} sx={{ alignItems: 'center' }}>
              {(currentHeroUrl || heroImage) && (
                <Avatar
                  src={heroImage ? undefined : (currentHeroUrl ?? undefined)}
                  alt="Hero preview"
                  variant="rounded"
                  sx={{ width: 120, height: 68, bgcolor: 'primary.light', fontSize: 14 }}
                >
                  {heroImage ? 'New' : <HomeIcon />}
                </Avatar>
              )}
              <Box sx={{ flexGrow: 1 }}>
                <input
                  id="home-hero-image"
                  type="file"
                  accept={ACCEPTED_IMAGE}
                  style={{ display: 'none' }}
                  onChange={(event) => setHeroImage(event.target.files?.[0] ?? null)}
                />
                <label htmlFor="home-hero-image">
                  <Button component="span" variant="outlined" startIcon={<CloudUploadIcon />} fullWidth sx={{ py: 1 }}>
                    {heroImage ? heroImage.name : 'Upload hero image (optional)'}
                  </Button>
                </label>
                <FormHelperText>JPG, PNG or WebP, up to 2 MB — leave empty to keep the current image</FormHelperText>
              </Box>
            </Stack>
          </Stack>

          <TextField label="Hero title" value={heroTitle} onChange={(e) => setHeroTitle(e.target.value)} fullWidth size="small" />
          <TextField
            label="Hero description"
            value={heroDescription}
            onChange={(e) => setHeroDescription(e.target.value)}
            fullWidth
            size="small"
            multiline
            minRows={2}
          />
          <TextField
            label="Introduction"
            value={introduction}
            onChange={(e) => setIntroduction(e.target.value)}
            fullWidth
            size="small"
            multiline
            minRows={4}
            helperText="Shown on the public homepage. Leave blank to fall back to the default cooperative introduction."
          />

          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ alignItems: 'center', justifyContent: 'space-between' }}>
            <Typography variant="caption" color="text.secondary">
              Content goes live immediately on the public homepage once saved.
            </Typography>
            <Button
              variant="contained"
              onClick={() => saveMutation.mutate()}
              disabled={saveMutation.isPending}
              sx={{ textTransform: 'none', alignSelf: { xs: 'stretch', sm: 'auto' } }}
            >
              {saveMutation.isPending ? 'Saving…' : 'Save changes'}
            </Button>
          </Stack>
        </Stack>
      </CardContent>
    </Card>
  )
}

export function AdminHomeContentPage(): ReactNode {
  const [savedMessage, setSavedMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const contentQuery = useQuery({
    queryKey: ['admin-home-content'],
    queryFn: fetchAdminHomeContent,
  })

  const subtitle = 'Configure the public homepage hero and introduction'

  if (contentQuery.isPending) {
    return (
      <PageContainer title="Homepage Content" subtitle={subtitle}>
        <Card variant="outlined">
          <CardContent>
            <TableSkeleton rows={4} columns={2} />
          </CardContent>
        </Card>
      </PageContainer>
    )
  }

  if (contentQuery.isError) {
    return (
      <PageContainer title="Homepage Content" subtitle={subtitle}>
        <ErrorState message={getErrorMessage(contentQuery.error)} onRetry={() => contentQuery.refetch()} />
      </PageContainer>
    )
  }

  return (
    <PageContainer title="Homepage Content" subtitle={subtitle}>
      <Stack spacing={2}>
        {savedMessage ? (
          <Typography variant="body2" color="success.main">
            {savedMessage}
          </Typography>
        ) : null}
        {error ? (
          <Typography variant="body2" color="error">
            {error}
          </Typography>
        ) : null}
        <HomeContentEditor
          key={contentQuery.data?.updated_at ?? 'empty'}
          initial={contentQuery.data}
          onSaved={() => setSavedMessage('Homepage content saved.')}
          onError={(message) => setError(message)}
        />
      </Stack>
    </PageContainer>
  )
}