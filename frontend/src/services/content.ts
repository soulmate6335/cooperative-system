import { api, unwrap, unwrapPage } from './api'

import type {
  AdminExecutive,
  AdminNotice,
  Executive,
  Notice,
  OrganizationContent,
  PageResult,
  PublicHome,
} from '../types'

export interface PagedFilters {
  page?: number
  per_page?: number
}

export interface AdminNoticeFilters extends PagedFilters {
  status?: NoticeStatusFilter
  visibility?: 'public' | 'members'
  search?: string
}

type NoticeStatusFilter = 'draft' | 'published' | 'archived'

// ------------------------------------------------------------ public surface

export async function fetchPublicHome(): Promise<PublicHome> {
  return unwrap<PublicHome>(api.get('/public/home'))
}

export async function listPublicNotices(filters: PagedFilters = {}): Promise<PageResult<Notice>> {
  return unwrapPage<Notice>(api.get('/notices', { params: filters }))
}

export async function fetchPublicNotice(id: string): Promise<Notice> {
  return unwrap<Notice>(api.get(`/notices/${id}`))
}

export async function listPublicExecutives(): Promise<Executive[]> {
  return unwrap<Executive[]>(api.get('/executives'))
}

// ------------------------------------------------------- member-facing notices

export async function listMemberNotices(filters: PagedFilters = {}): Promise<PageResult<Notice>> {
  return unwrapPage<Notice>(api.get('/member/notices', { params: filters }))
}

// --------------------------------------------------------------- admin notices

export async function listAdminNotices(filters: AdminNoticeFilters = {}): Promise<PageResult<AdminNotice>> {
  return unwrapPage<AdminNotice>(api.get('/admin/notices', { params: filters }))
}

export interface SaveNoticePayload {
  title: string
  body: string
  excerpt?: string | null
  visibility: 'public' | 'members'
  publish_at?: string | null
  expires_at?: string | null
}

export async function createNotice(payload: SaveNoticePayload): Promise<AdminNotice> {
  return unwrap<AdminNotice>(api.post('/admin/notices', payload))
}

export async function updateNotice(id: string, payload: Partial<SaveNoticePayload>): Promise<AdminNotice> {
  return unwrap<AdminNotice>(api.patch(`/admin/notices/${id}`, payload))
}

export async function publishNotice(id: string, publishAt?: string): Promise<AdminNotice> {
  return unwrap<AdminNotice>(api.post(`/admin/notices/${id}/publish`, publishAt ? { publish_at: publishAt } : {}))
}

export async function archiveNotice(id: string): Promise<AdminNotice> {
  return unwrap<AdminNotice>(api.post(`/admin/notices/${id}/archive`))
}

// ----------------------------------------------------------- admin executives

export async function listAdminExecutives(filters: PagedFilters & { is_visible?: boolean } = {}): Promise<PageResult<AdminExecutive>> {
  return unwrapPage<AdminExecutive>(api.get('/admin/executives', { params: filters }))
}

export interface SaveExecutivePayload {
  name: string
  position: string
  biography?: string | null
  display_order: number
  photo?: File | null
}

export async function createExecutive(payload: SaveExecutivePayload): Promise<AdminExecutive> {
  const form = new FormData()
  form.append('name', payload.name)
  form.append('position', payload.position)
  if (payload.biography) {
    form.append('biography', payload.biography)
  }
  form.append('display_order', String(payload.display_order))
  if (payload.photo) {
    form.append('photo', payload.photo)
  }
  return unwrap<AdminExecutive>(api.post('/admin/executives', form))
}

export async function updateExecutive(id: string, payload: SaveExecutivePayload): Promise<AdminExecutive> {
  const form = new FormData()
  form.append('name', payload.name)
  form.append('position', payload.position)
  if (payload.biography) {
    form.append('biography', payload.biography)
  }
  form.append('display_order', String(payload.display_order))
  // A photo is optional on update; absent means "keep the current one".
  if (payload.photo) {
    form.append('photo', payload.photo)
  }
  // Laravel treats PATCH + FormData as the raw multipart request; the _method
  // spoof is not supported on JSON API routes, so send POST with _method.
  form.append('_method', 'PATCH')
  return unwrap<AdminExecutive>(api.post(`/admin/executives/${id}`, form))
}

export async function setExecutiveVisibility(id: string, isVisible: boolean): Promise<AdminExecutive> {
  return unwrap<AdminExecutive>(api.post(`/admin/executives/${id}/visibility`, { is_visible: isVisible }))
}

// ------------------------------------------------------- homepage content

export async function fetchAdminHomeContent(): Promise<OrganizationContent | null> {
  return unwrap<OrganizationContent | null>(api.get('/admin/home-content'))
}

export interface SaveHomeContentPayload {
  hero_title?: string | null
  hero_description?: string | null
  introduction?: string | null
  hero_image?: File | null
}

export async function updateAdminHomeContent(payload: SaveHomeContentPayload): Promise<OrganizationContent> {
  const form = new FormData()
  if (payload.hero_title !== undefined) {
    form.append('hero_title', payload.hero_title ?? '')
  }
  if (payload.hero_description !== undefined) {
    form.append('hero_description', payload.hero_description ?? '')
  }
  if (payload.introduction !== undefined) {
    form.append('introduction', payload.introduction ?? '')
  }
  if (payload.hero_image) {
    form.append('hero_image', payload.hero_image)
  }
  form.append('_method', 'PATCH')
  return unwrap<OrganizationContent>(api.post('/admin/home-content', form))
}