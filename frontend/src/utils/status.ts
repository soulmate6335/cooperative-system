import type { SxProps, Theme } from '@mui/material'

export interface StatusStyle {
  label: string
  /** MUI palette color used by Chip/Badge/Alert. */
  color: 'default' | 'primary' | 'secondary' | 'error' | 'info' | 'success' | 'warning'
  /** Custom dot/background tint variant. */
  tone: 'neutral' | 'success' | 'warning' | 'error' | 'info'
}

const STATUS_MAP: Record<string, StatusStyle> = {
  // Membership / users
  pending: { label: 'Pending', color: 'warning', tone: 'warning' },
  active: { label: 'Active', color: 'success', tone: 'success' },
  inactive: { label: 'Inactive', color: 'default', tone: 'neutral' },
  suspended: { label: 'Suspended', color: 'error', tone: 'error' },

  // Decisions
  approved: { label: 'Approved', color: 'success', tone: 'success' },
  rejected: { label: 'Rejected', color: 'error', tone: 'error' },
  cancelled: { label: 'Cancelled', color: 'default', tone: 'neutral' },

  // Loan applications
  draft: { label: 'Draft', color: 'default', tone: 'neutral' },
  submitted: { label: 'Submitted', color: 'info', tone: 'info' },
  awaiting_guarantors: { label: 'Awaiting Guarantors', color: 'warning', tone: 'warning' },
  guarantors_confirmed: { label: 'Guarantors Confirmed', color: 'primary', tone: 'info' },
  under_investigation: { label: 'Under Investigation', color: 'primary', tone: 'info' },
  committee_reviewed: { label: 'Committee Reviewed', color: 'primary', tone: 'info' },
  pending_admin_decision: { label: 'Pending Admin Decision', color: 'warning', tone: 'warning' },

  // Loans
  pending_disbursement: { label: 'Pending Disbursement', color: 'warning', tone: 'warning' },
  disbursed: { label: 'Disbursed', color: 'primary', tone: 'info' },
  overdue: { label: 'Overdue', color: 'error', tone: 'error' },
  defaulted: { label: 'Defaulted', color: 'error', tone: 'error' },
  completed: { label: 'Completed', color: 'success', tone: 'success' },

  // Guarantor responses
  declined: { label: 'Declined', color: 'error', tone: 'error' },
  verified: { label: 'Verified', color: 'success', tone: 'success' },

  // Investigations
  assigned: { label: 'Assigned', color: 'primary', tone: 'info' },

  // Committee meetings
  scheduled: { label: 'Scheduled', color: 'info', tone: 'info' },
  held: { label: 'Held', color: 'success', tone: 'success' },

  // Financial
  posted: { label: 'Posted', color: 'success', tone: 'success' },
  credit: { label: 'Credit', color: 'success', tone: 'success' },
  debit: { label: 'Debit', color: 'error', tone: 'error' },
}

export function statusStyle(status: string | null | undefined): StatusStyle {
  const key = status ?? ''
  return (
    STATUS_MAP[key] ?? {
      label: key
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' '),
      color: 'default',
      tone: 'neutral',
    }
  )
}

const TONE_COLORS: Record<StatusStyle['tone'], string> = {
  neutral: '#64748b',
  success: '#15803d',
  warning: '#b45309',
  error: '#b91c1c',
  info: '#0369a1',
}

const TONE_BACKGROUNDS: Record<StatusStyle['tone'], string> = {
  neutral: '#f1f5f9',
  success: '#dcfce7',
  warning: '#fef3c7',
  error: '#fee2e2',
  info: '#e0f2fe',
}

/** Visual treatment for custom status pills (dot + tinted background). */
export function statusPillSx(status: string): SxProps<Theme> {
  const { tone } = statusStyle(status)
  return {
    display: 'inline-flex',
    alignItems: 'center',
    gap: 0.75,
    px: 1.25,
    py: 0.5,
    borderRadius: 999,
    fontSize: '0.8125rem',
    fontWeight: 600,
    lineHeight: 1,
    color: TONE_COLORS[tone],
    backgroundColor: TONE_BACKGROUNDS[tone],
    '&::before': {
      content: '""',
      width: 7,
      height: 7,
      borderRadius: '50%',
      backgroundColor: 'currentColor',
    },
  }
}