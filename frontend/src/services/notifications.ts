import { api, unwrap, unwrapPage } from './api'

import type { AppNotification, PageResult } from '../types'

export interface NotificationFilters {
  page?: number
  per_page?: number
}

export async function listNotifications(filters: NotificationFilters = {}): Promise<PageResult<AppNotification>> {
  return unwrapPage<AppNotification>(api.get('/notifications', { params: filters }))
}

/** Unread notification count for the authenticated user. */
export async function fetchUnreadCount(): Promise<number> {
  return unwrap<{ unread_count: number }>(api.get('/notifications/unread-count')).then((body) => body.unread_count)
}

export async function markNotificationRead(id: string): Promise<AppNotification> {
  return unwrap<AppNotification>(api.post(`/notifications/${id}/read`))
}

/** Marks every notification read; returns the number marked. */
export async function markAllNotificationsRead(): Promise<number> {
  return unwrap<{ marked_read: number }>(api.post('/notifications/read-all')).then((body) => body.marked_read)
}