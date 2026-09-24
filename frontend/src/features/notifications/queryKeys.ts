import type { QueryClient } from '@tanstack/react-query'

/** Shared React Query keys for the personal notification inbox. */
export const NOTIFICATION_LIST_KEY = ['notifications', 'list'] as const
export const NOTIFICATION_UNREAD_KEY = ['notifications', 'unread-count'] as const

export function invalidateNotifications(queryClient: QueryClient): void {
  void queryClient.invalidateQueries({ queryKey: ['notifications'] })
}