import { api, unwrap, unwrapPage } from './api'

import type { Member, MemberApplication, PageResult } from '../types'

export interface MembershipApplicationFilters {
  status?: string
  search?: string
  per_page?: number
  /** Server-side page number (Laravel paginator, 1-based). */
  page?: number
}

export async function submitMembershipApplication(payload: FormData): Promise<MemberApplication> {
  return unwrap<MemberApplication>(api.post('/membership/applications', payload))
}

export async function listMemberApplications(filters: MembershipApplicationFilters = {}): Promise<PageResult<MemberApplication>> {
  return unwrapPage<MemberApplication>(api.get('/admin/membership/applications', { params: filters }))
}

export async function getMemberApplication(id: string): Promise<MemberApplication> {
  return unwrap<MemberApplication>(api.get(`/admin/membership/applications/${id}`))
}

export async function approveMemberApplication(id: string): Promise<Member> {
  return unwrap<Member>(api.post(`/admin/membership/applications/${id}/approve`))
}

export async function rejectMemberApplication(id: string, reason: string): Promise<MemberApplication> {
  return unwrap<MemberApplication>(api.post(`/admin/membership/applications/${id}/reject`, { reason }))
}