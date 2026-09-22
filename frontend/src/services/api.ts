import axios, { AxiosError } from 'axios'

import type { ApiEnvelope, ApiErrorBody, PageResult } from '../types'

export const TOKEN_STORAGE_KEY = 'cs_api_token'

export const API_BASE_URL = (import.meta.env.VITE_API_URL as string | undefined) ?? 'http://localhost:8000/api/v1'

export class ApiError extends Error {
  status?: number
  fieldErrors?: Record<string, string[]>

  constructor(message: string, status?: number, fieldErrors?: Record<string, string[]>) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.fieldErrors = fieldErrors
  }
}

function extractErrorMessage(body: ApiErrorBody | undefined, fallback: string): string {
  if (body?.message && body.message.length > 0) {
    return body.message
  }
  if (body?.errors) {
    const firstField = Object.values(body.errors)[0]
    if (firstField?.[0]) {
      return firstField[0]
    }
  }
  return fallback
}

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_STORAGE_KEY)
}

export function setToken(token: string | null): void {
  if (token === null) {
    localStorage.removeItem(TOKEN_STORAGE_KEY)
  } else {
    localStorage.setItem(TOKEN_STORAGE_KEY, token)
  }
}

export const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    Accept: 'application/json',
  },
  timeout: 30000,
})

api.interceptors.request.use((config) => {
  const token = getToken()
  if (token) {
    config.headers.set('Authorization', `Bearer ${token}`)
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiErrorBody>) => {
    const status = error.response?.status
    const body = error.response?.data

    if (status === 401) {
      const isLoginCall = error.config?.url?.includes('/auth/login') ?? false
      if (!isLoginCall) {
        setToken(null)
        if (window.location.pathname !== '/login') {
          window.location.href = '/login'
        }
      }
    }

    let message = 'An unexpected error occurred. Please try again.'
    let fieldErrors: Record<string, string[]> | undefined

    if (error.response) {
      if (status === 422) {
        fieldErrors = body?.errors
        message = extractErrorMessage(body, 'Please review the highlighted fields and try again.')
      } else if (status === 401) {
        message = extractErrorMessage(body, 'Your session has expired. Please sign in again.')
      } else if (status === 403) {
        message = extractErrorMessage(body, 'You are not authorised to perform this action.')
      } else if (status === 404) {
        message = body?.message ?? 'The requested record could not be found.'
      } else if (status === 429) {
        message = 'Too many requests. Please wait a moment and try again.'
      } else {
        message = extractErrorMessage(body, message)
      }
    } else if (error.code === 'ECONNABORTED') {
      message = 'The request timed out. Please check your connection and try again.'
    } else if (error.code === 'ERR_NETWORK') {
      message = 'Unable to reach the server. Please check that the backend is running.'
    }

    return Promise.reject(new ApiError(message, status, fieldErrors))
  },
)

/** Unwrap the standard { success, message, data } envelope. */
export async function unwrap<T>(promise: Promise<{ data: ApiEnvelope<T> }>): Promise<T> {
  const response = await promise
  return response.data.data
}

/** Return the raw body for endpoints whose body is the resource itself (e.g. /auth/me). */
export async function unwrapRaw<T>(promise: Promise<{ data: T }>): Promise<T> {
  const response = await promise
  return response.data
}

/** Unwrap a paginated envelope into a PageResult. */
export async function unwrapPage<T>(
  promise: Promise<{ data: ApiEnvelope<T[]> }>,
): Promise<PageResult<T>> {
  const response = await promise
  return {
    data: response.data.data,
    meta: response.data.meta ?? { current_page: 1, per_page: 20, total: response.data.data.length, last_page: 1 },
  }
}