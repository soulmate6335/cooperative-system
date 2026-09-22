import { api, unwrap, unwrapPage } from './api'

import type { CommitteeMeeting, PageResult } from '../types'

export interface MeetingFilters {
  per_page?: number
}

export async function listCommitteeMeetings(filters: MeetingFilters = {}): Promise<PageResult<CommitteeMeeting>> {
  return unwrapPage<CommitteeMeeting>(api.get('/admin/committee-meetings', { params: filters }))
}

export interface SaveCommitteeMeetingPayload {
  meeting_date: string
  meeting_type: string
  cutoff_days: number
  notes?: string | null
}

export async function createCommitteeMeeting(payload: SaveCommitteeMeetingPayload): Promise<CommitteeMeeting> {
  return unwrap<CommitteeMeeting>(api.post('/admin/committee-meetings', payload))
}

export async function updateCommitteeMeeting(id: string, payload: Partial<SaveCommitteeMeetingPayload>): Promise<CommitteeMeeting> {
  return unwrap<CommitteeMeeting>(api.patch(`/admin/committee-meetings/${id}`, payload))
}