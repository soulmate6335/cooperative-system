import type { ReactNode } from 'react'
import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import Box from '@mui/material/Box'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Chip from '@mui/material/Chip'
import Pagination from '@mui/material/Pagination'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import CampaignIcon from '@mui/icons-material/Campaign'

import { EmptyState } from '../../components/common/EmptyState'
import { ErrorState } from '../../components/common/ErrorState'
import { SimplePageContainer } from '../../components/common/PageContainer'
import { TableSkeleton } from '../../components/common/Skeletons'
import { listMemberNotices } from '../../services/content'
import { formatDate } from '../../utils/format'
import { getErrorMessage } from '../../utils/errors'

export function MemberNoticesPage(): ReactNode {
  const [page, setPage] = useState(1)

  const noticesQuery = useQuery({
    queryKey: ['member-notices', page],
    queryFn: () => listMemberNotices({ page, per_page: 10 }),
  })

  const notices = noticesQuery.data?.data ?? []
  const meta = noticesQuery.data?.meta

  return (
    <SimplePageContainer title="Notices" subtitle="Announcements from the cooperative">
      {noticesQuery.isPending ? (
        <TableSkeleton rows={5} columns={2} />
      ) : noticesQuery.isError ? (
        <ErrorState message={getErrorMessage(noticesQuery.error)} onRetry={() => noticesQuery.refetch()} />
      ) : notices.length === 0 ? (
        <EmptyState icon={CampaignIcon} title="No notices yet" description="Published notices will appear here." />
      ) : (
        <Stack spacing={2}>
          {notices.map((notice) => (
            <Card key={notice.id} variant="outlined">
              <CardContent>
                <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ alignItems: { sm: 'center' }, justifyContent: 'space-between', mb: 1 }}>
                  <Typography variant="h6" sx={{ fontWeight: 700 }}>
                    {notice.title}
                  </Typography>
                  {notice.visibility === 'members' ? (
                    <Chip size="small" label="Members only" color="secondary" variant="outlined" />
                  ) : (
                    <Chip size="small" label="Public" color="default" variant="outlined" />
                  )}
                </Stack>
                {notice.excerpt ? (
                  <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                    {notice.excerpt}
                  </Typography>
                ) : null}
                <Typography variant="body2" sx={{ whiteSpace: 'pre-wrap' }}>
                  {notice.body}
                </Typography>
                <Typography variant="caption" color="text.secondary" sx={{ display: 'block', mt: 1.5 }}>
                  Published {notice.publish_at ? formatDate(notice.publish_at) : ''}
                </Typography>
              </CardContent>
            </Card>
          ))}
          {meta && meta.last_page > 1 ? (
            <Box sx={{ display: 'flex', justifyContent: 'center' }}>
              <Pagination count={meta.last_page} page={page} onChange={(_, next) => setPage(next)} color="primary" />
            </Box>
          ) : null}
        </Stack>
      )}
    </SimplePageContainer>
  )
}