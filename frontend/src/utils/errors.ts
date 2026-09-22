import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'

import { ApiError } from '../services/api'

export interface ErrorLike {
  message?: string
}

/** Best-effort user-facing message from any unknown error. */
export function getErrorMessage(error: unknown, fallback = 'An unexpected error occurred.'): string {
  if (error instanceof ApiError) {
    return error.message
  }
  if (error instanceof Error && error.message) {
    return error.message
  }
  if (typeof error === 'string') {
    return error
  }
  if (error && typeof error === 'object' && 'message' in error && typeof (error as ErrorLike).message === 'string') {
    return (error as ErrorLike).message as string
  }
  return fallback
}

/**
 * Map Laravel validation field errors onto a react-hook-form form.
 * Only touches fields that exist on the form.
 */
export function applyServerErrors<TForm extends FieldValues>(
  fieldErrors: Record<string, string[]> | undefined,
  setError: UseFormSetError<TForm>,
  fields: Path<TForm>[],
): void {
  if (!fieldErrors) {
    return
  }
  for (const [field, messages] of Object.entries(fieldErrors)) {
    if ((fields as string[]).includes(field) && messages[0]) {
      setError(field as Path<TForm>, { type: 'server', message: messages[0] })
    }
  }
}