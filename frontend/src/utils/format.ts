/** Format a kobo/minor-unit integer as Nigerian naira. 10000 -> ₦100.00 */
export function formatNaira(minor: number | null | undefined): string {
  if (minor === null || minor === undefined) {
    return '—'
  }
  const naira = minor / 100
  return new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(naira)
}

export function formatNairaCompact(minor: number | null | undefined): string {
  if (minor === null || minor === undefined) {
    return '—'
  }
  const naira = minor / 100
  if (naira >= 1_000_000) {
    return `₦${(naira / 1_000_000).toLocaleString('en-NG', { maximumFractionDigits: 2 })}m`
  }
  if (naira >= 1_000) {
    return `₦${(naira / 1_000).toLocaleString('en-NG', { maximumFractionDigits: 1 })}k`
  }
  return `₦${naira.toLocaleString('en-NG', { maximumFractionDigits: 2 })}`
}

/** Format an interest rate given in basis points. 1000 -> 10% */
export function formatBasisPoints(basisPoints: number | null | undefined): string {
  if (basisPoints === null || basisPoints === undefined) {
    return '—'
  }
  return `${(basisPoints / 100).toLocaleString('en-NG', { maximumFractionDigits: 2 })}%`
}

function toDate(value: string | null | undefined): Date | null {
  if (!value) {
    return null
  }
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
}

export function formatDate(value: string | null | undefined): string {
  const date = toDate(value)
  if (!date) {
    return '—'
  }
  return new Intl.DateTimeFormat('en-NG', { day: '2-digit', month: 'short', year: 'numeric' }).format(date)
}

export function formatDateTime(value: string | null | undefined): string {
  const date = toDate(value)
  if (!date) {
    return '—'
  }
  return new Intl.DateTimeFormat('en-NG', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}

export function titleCase(value: string | null | undefined): string {
  if (!value) {
    return '—'
  }
  return value
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}