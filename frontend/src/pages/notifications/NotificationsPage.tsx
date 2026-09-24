import type { ReactNode } from 'react'
import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Chip from '@mui/material/Chip'
import List from '@mui/material/List'
import ListItem from '@mui/material/ListItem'
import ListItemButton from '@mui/material/ListItemButton'
import ListItemText from '@mui/material/ListItemText'
import Pagination from '@mui/material/Pagination'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import NotificationsNoneIcon from '@mui/icons-material/NotificationsNone'
import DoneAllIcon from '@mui/icons-material/DoneAll'

import { EmptyState } from '../../components/common/EmptyState'
import { ErrorState } from '../../components/common/ErrorState'
import { SimplePageContainer } from '../../components/common/PageContainer'
import { TableSkeleton } from '../../components/common/Skeletons'
import { NOTIFICATION_LIST_KEY, invalidateNotifications } from '../../features/notifications/queryKeys'
import { listNotifications, markAllNotificationsRead, markNotificationRead } from '../../services/notifications'
import { formatDateTime } from '../../utils/format'
import { getErrorMessage } from '../../utils/errors'

export function NotificationsPage(): ReactNode {
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)

  const listQuery = useQuery({
    queryKey: [...NOTIFICATION_LIST_KEY, page],
    queryFn: () => listNotifications({ page, per_page: 20 }),
  })

  const readMutation = useMutation({
    mutationFn: (id: string) => markNotificationRead(id),
    onSuccess: () => invalidateNotifications(queryClient),
  })

  const readAllMutation = useMutation({
    mutationFn: () => markAllNotificationsRead(),
    onSuccess: () => invalidateNotifications(queryClient),
  })

  const notifications = listQuery.data?.data ?? []
  const meta = listQuery.data?.meta

  return (
    <SimplePageContainer
      title="Notifications"
      subtitle="Updates from your membership, loans, payments and the cooperative"
      actions={
        <Button
          variant="outlined"
          startIcon={<DoneAllIcon />}
          sx={{ textTransform: 'none' }}
          onClick={() => readAllMutation.mutate()}
          disabled={readAllMutation.isPending || notifications.every((n) => n.read_at !== null)}
        >
          Mark all as read
        </Button>
      }
    >
      {listQuery.isPending ? (
        <TableSkeleton rows={6} columns={3} />
      ) : listQuery.isError ? (
        <ErrorState message={getErrorMessage(listQuery.error)} onRetry={() => listQuery.refetch()} />
      ) : notifications.length === 0 ? (
        <EmptyState
          icon={NotificationsNoneIcon}
          title="No notifications yet"
          description="Workflow updates — approvals, loans, repayments and notices — will appear here."
        />
      ) : (
        <Card variant="outlined">
          <CardContent>
            <List disablePadding>
              {notifications.map((notification, index) => (
                <ListItem key={notification.id} disablePadding divider={index < notifications.length - 1}>
                  <ListItemButton
                    onClick={() => {
                      if (notification.read_at === null) {
                        readMutation.mutate(notification.id)
                      }
                    }}
                    sx={{ px: { xs: 0.5, sm: 1.5 }, py: 1 }}
                  >
                    <ListItemText
                      primary={
                        <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
                          <Typography variant="subtitle2" sx={{ fontWeight: notification.read_at === null ? 700 : 500 }}>
                            {notification.title ?? 'Update'}
                          </Typography>
                          {notification.read_at === null ? <Chip size="small" color="primary" label="New" variant="outlined" /> : null}
                        </Stack>
                      }
                      secondary={
                        <Box>
                          <Typography variant="body2" color="text.secondary">
                            {notification.message ?? ''}
                          </Typography>
                          <Typography variant="caption" color="text.secondary">
                            {notification.created_at ? formatDateTime(notification.created_at) : ''}
                          </Typography>
                        </Box>
                      }
                    />
                  </ListItemButton>
                </ListItem>
              ))}
            </List>
            {meta && meta.last_page > 1 ? (
              <Stack sx={{ alignItems: 'center', mt: 2 }}>
                <Pagination
                  count={meta.last_page}
                  page={page}
                  onChange={(_, next) => setPage(next)}
                  size="small"
                  color="primary"
                />
              </Stack>
            ) : null}
          </CardContent>
        </Card>
      )}
    </SimplePageContainer>
  )
}