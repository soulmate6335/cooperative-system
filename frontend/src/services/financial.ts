import { api, unwrap } from './api'

import type {
  FinancialAccount,
  FinancialTransaction,
  Payment,
  PaymentMethod,
} from '../types'

// ------------------------------------------------------------------ methods

export interface ListPaymentMethodsFilters {
  active_only?: boolean
}

/** Public list of payment methods (used to render the "pay by" selector). */
export async function listPaymentMethods(filters: ListPaymentMethodsFilters = {}): Promise<PaymentMethod[]> {
  return unwrap<PaymentMethod[]>(api.get('/payment-methods', { params: filters }))
}

export interface SavePaymentMethodPayload {
  code: string
  name: string
  description?: string | null
  is_active?: boolean
  display_order?: number
}

export async function createPaymentMethod(payload: SavePaymentMethodPayload): Promise<PaymentMethod> {
  return unwrap<PaymentMethod>(api.post('/admin/payment-methods', payload))
}

// ------------------------------------------------------------------ accounts

export async function listMemberAccounts(): Promise<FinancialAccount[]> {
  return unwrap<FinancialAccount[]>(api.get('/member/accounts'))
}

export async function listAccountTransactions(accountId: string, filters: { per_page?: number } = {}): Promise<FinancialTransaction[]> {
  return unwrap<FinancialTransaction[]>(api.get(`/member/accounts/${accountId}/transactions`, { params: filters }))
}

export interface CreateFinancialAccountPayload {
  member_id: string
  account_type: 'contribution' | 'savings' | 'shares'
}

export async function createFinancialAccount(payload: CreateFinancialAccountPayload): Promise<FinancialAccount> {
  return unwrap<FinancialAccount>(api.post('/admin/financial/accounts', payload))
}

// ------------------------------------------------------------------ payments

export type PaymentPurpose = 'contribution' | 'savings' | 'shares'

export interface RecordPaymentPayload {
  member_id: string
  payment_method_id: string
  amount_minor: number
  payment_date?: string | null
  reference_number: string
  purpose: PaymentPurpose
  notes?: string | null
}

export async function recordPayment(payload: RecordPaymentPayload): Promise<Payment> {
  return unwrap<Payment>(api.post('/finance/payments', payload))
}

export async function verifyPayment(paymentId: string): Promise<Payment> {
  return unwrap<Payment>(api.post(`/finance/payments/${paymentId}/verify`))
}

export async function issueReceipt(paymentId: string): Promise<Payment> {
  return unwrap<Payment>(api.post(`/finance/payments/${paymentId}/receipt`))
}

// ----------------------------------------------------------- transactions

export async function reverseTransaction(transactionId: string, reason?: string): Promise<FinancialTransaction> {
  return unwrap<FinancialTransaction>(api.post(`/finance/transactions/${transactionId}/reverse`, { reason: reason ?? null }))
}
