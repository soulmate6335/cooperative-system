import { api, unwrap } from './api'

import type { User } from '../types'

export interface LoginResponse {
  user: User
  token: string
}

export interface LoginPayload {
  email: string
  password: string
}

export async function login(payload: LoginPayload): Promise<LoginResponse> {
  const envelope = await unwrap<LoginResponse>(api.post('/auth/login', payload))
  return envelope
}

export async function logout(): Promise<void> {
  await api.post('/auth/logout')
}

export async function fetchMe(): Promise<User> {
  return unwrap<User>(api.get('/auth/me'))
}