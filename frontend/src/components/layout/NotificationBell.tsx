import type { ReactNode } from 'react'
import { useState } from 'react'
import Badge from '@mui/material/Badge'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Divider from '@mui/material/Divider'
import IconButton from '@mui/material/IconButton'
import List from '@mui/material/List'
import ListItemButton from '@mui/material/ListItemButton'
import ListItemText from '@mui/material/ListItemText'
import Menu from '@mui/material/Menu'
import Tooltip from '@mui/material/Tooltip'
import Typography from '@mui/material/Typography'
import NotificationsNoneIcon from '@mui/icons-material/NotificationsNone'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'

import { fetchUnreadCount, listNotifications, markAllNotificationsRead, markNotificationRead } from '../../services/notifications'
import { invalidateNotifications, NOTIFICATION_LIST_KEY, NOTIFICATION_UNREAD_KEY } from '../../features/notifications/queryKeys'
import { formatDateTime } from '../../utils/format'
import { getErrorMessage } from '../../utils/errors'

/**
 * App bar notification bell. The unread badge is refreshed on open, after
 * actions and on window focus — deliberately no aggressive polling.
 */
export function NotificationBell(): ReactNode {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [anchorEl, setAnchorEl] = useState<HTMLElement | null>(null)
  const open = Boolean(anchorEl)

  const unreadCountQuery = useQuery({
    queryKey: NOTIFICATION_UNREAD_KEY,
    queryFn: fetchUnreadCount,
    refetchOnWindowFocus: true,
  })

  // The preview list is fetched on demand when the menu is opened, then cached.
  const previewQuery = useQuery({
    queryKey: NOTIFICATION_LIST_KEY,
    queryFn: () => listNotifications({ per_page: 5 }),
    enabled: open,
  })

  const markAll = async (): Promise<void> => {
    try {
      await markAllNotificationsRead()
    } finally {
      invalidateNotifications(queryClient)
    }
  }

  const markRead = async (id: string): Promise<void> => {
    try {
      await markNotificationRead(id)
    } finally {
      invalidateNotifications(queryClient)
    }
  }

  const preview = previewQuery.data?.data ?? []
  const unread = unreadCountQuery.data ?? 0

  return (
    <>
      <Tooltip title="Notifications">
        <IconButton
          onClick={(event) => {
            setAnchorEl(event.currentTarget)
            void unreadCountQuery.refetch()
          }}
          aria-label="Notifications"
          size="small"
          sx={{ border: '1px solid', borderColor: 'divider' }}
        >
          <Badge badgeContent={unread} color="error" max={9}>
            <NotificationsNoneIcon fontSize="small" />
          </Badge>
        </IconButton>
      </Tooltip>

      <Menu
        anchorEl={anchorEl}
        open={open}
        onClose={() => setAnchorEl(null)}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
        transformOrigin={{ vertical: 'top', horizontal: 'right' }}
        slotProps={{ paper: { sx: { width: 360, maxHeight: 420 } } }}
      >
        <Box sx={{ px: 2, py: 1 }}>
          <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>
            Notifications
          </Typography>
          <Typography variant="caption" color="text.secondary">
            {previewQuery.isPending ? 'Loading…' : `${unread} unread`}
          </Typography>
        </Box>
        <Divider />
        {preview.length === 0 ? (
          <Box sx={{ px: 2, py: 3, textAlign: 'center' }}>
            <Typography variant="body2" color="text.secondary">
              {previewQuery.isPending ? 'Loading preview…' : 'You are all caught up.'}
            </Typography>
          </Box>
        ) : (
          <List dense disablePadding>
            {preview.map((notification) => (
              <ListItemButton
                key={notification.id}
                onClick={() => {
                  void markRead(notification.id)
                  setAnchorEl(null)
                  navigate('/notifications')
                }}
                sx={{ px: 2 }}
              >
                <ListItemText
                  primary={
                    <Typography variant="body2" sx={{ fontWeight: notification.read_at === null ? 700 : 500 }}>
                      {notification.title ?? 'Update'}
                    </Typography>
                  }
                  secondary={
                    <Box>
                      <Typography variant="caption" color="text.secondary" sx={{ display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden' }}>
                        {notification.message ?? ''}
                      </Typography>
                      <Typography variant="caption" color="text.secondary">
                        {notification.created_at ? formatDateTime(notification.created_at) : ''}
                      </Typography>
                    </Box>
                  }
                />
              </ListItemButton>
            ))}
          </List>
        )}
        <Divider />
        <Box sx={{ p: 1, display: 'flex', gap: 1, justifyContent: 'flex-end' }}>
          <Button size="small" sx={{ textTransform: 'none' }} onClick={() => void markAll()} disabled={unread === 0}>
            Mark all read
          </Button>
          <Button
            size="small"
            variant="contained"
            sx={{ textTransform: 'none' }}
            onClick={() => {
              setAnchorEl(null)
              navigate('/notifications')
            }}
          >
            View all
          </Button>
        </Box>
        {previewQuery.isError ? (
          <Typography variant="caption" color="error" sx={{ px: 2, pb: 1, display: 'block' }}>
            {getErrorMessage(previewQuery.error)}
          </Typography>
        ) : null}
      </Menu>
    </>
  )
}