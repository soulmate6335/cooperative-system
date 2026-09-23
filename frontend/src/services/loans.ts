import { api, unwrap, unwrapPage } from './api'

import type {
  EligibilityAssessment,
  EligibilityDecision,
  EligibilityFactors,
  EligibilityMemberSummary,
  InvestigationStatus,
  Loan,
  LoanApplication,
  LoanGuarantor,
  LoanInvestigation,
  LoanProduct,
  PageResult,
} from '../types'

export interface LoanListFilters {
  status?: string
  search?: string
  per_page?: number
  /** Server-side page number (Laravel paginator, 1-based). */
  page?: number
}

// ---------------------------------------------------------------- products

export async function listLoanProducts(): Promise<LoanProduct[]> {
  return unwrap<LoanProduct[]>(api.get('/loan-products'))
}

export async function listAdminLoanProducts(filters: { per_page?: number } = {}): Promise<PageResult<LoanProduct>> {
  return unwrapPage<LoanProduct>(api.get('/admin/loan-products', { params: filters }))
}

export interface SaveLoanProductPayload {
  name: string
  description?: string | null
  minimum_membership_months: number
  minimum_amount_minor: number
  maximum_amount_minor?: number | null
  interest_rate_basis_points: number
  interest_method: 'flat' | 'reducing_balance'
  repayment_months: number
  required_guarantors: number
}

export async function createLoanProduct(payload: SaveLoanProductPayload): Promise<LoanProduct> {
  return unwrap<LoanProduct>(api.post('/admin/loan-products', payload))
}

export async function updateLoanProduct(id: string, payload: Partial<SaveLoanProductPayload>): Promise<LoanProduct> {
  return unwrap<LoanProduct>(api.patch(`/admin/loan-products/${id}`, payload))
}

export async function setLoanProductActive(id: string, active: boolean): Promise<LoanProduct> {
  const action = active ? 'activate' : 'deactivate'
  return unwrap<LoanProduct>(api.post(`/admin/loan-products/${id}/${action}`))
}

// -------------------------------------------------------------- eligibility

export async function fetchEligibility(productId: string): Promise<EligibilityFactors> {
  return unwrap<EligibilityFactors>(api.get('/member/loans/eligibility', { params: { product_id: productId } }))
}

// --------------------------------------------------- admin eligibility review

export interface DecideEligibilityPayload {
  member_id: string
  loan_product_id: string
  status: 'eligible' | 'ineligible'
  reason?: string | null
}

export async function listEligibilityMembers(filters: { search?: string; per_page?: number } = {}): Promise<PageResult<EligibilityMemberSummary>> {
  return unwrapPage<EligibilityMemberSummary>(api.get('/admin/loan-eligibility/members', { params: filters }))
}

export async function fetchEligibilityAssessment(memberId: string, productId: string): Promise<EligibilityAssessment> {
  return unwrap<EligibilityAssessment>(
    api.get('/admin/loan-eligibility/assessments', { params: { member_id: memberId, product_id: productId } }),
  )
}

export async function decideEligibility(
  payload: DecideEligibilityPayload,
): Promise<{ member_id: string; loan_product_id: string; admin_decision: EligibilityDecision }> {
  return unwrap(api.post('/admin/loan-eligibility/decisions', payload))
}

export type { EligibilityDecision }

// ------------------------------------------------------- member applications

export interface CreateLoanApplicationPayload {
  loan_product_id: string
  amount_requested_minor: number
  purpose: string
}

export async function listMyLoanApplications(filters: LoanListFilters = {}): Promise<PageResult<LoanApplication>> {
  return unwrapPage<LoanApplication>(api.get('/member/loans/applications', { params: filters }))
}

export async function getMyLoanApplication(id: string): Promise<LoanApplication> {
  return unwrap<LoanApplication>(api.get(`/member/loans/applications/${id}`))
}

export async function createLoanApplication(payload: CreateLoanApplicationPayload): Promise<LoanApplication> {
  return unwrap<LoanApplication>(api.post('/member/loans/applications', payload))
}

export async function updateLoanApplication(id: string, payload: CreateLoanApplicationPayload): Promise<LoanApplication> {
  return unwrap<LoanApplication>(api.patch(`/member/loans/applications/${id}`, payload))
}

export async function submitLoanApplication(id: string): Promise<LoanApplication> {
  return unwrap<LoanApplication>(api.post(`/member/loans/applications/${id}/submit`))
}

export async function cancelLoanApplication(id: string): Promise<LoanApplication> {
  return unwrap<LoanApplication>(api.post(`/member/loans/applications/${id}/cancel`))
}

// ------------------------------------------------------------- guarantors

export async function requestGuarantor(applicationId: string, guarantorMemberId: string): Promise<LoanGuarantor> {
  return unwrap<LoanGuarantor>(api.post(`/member/loans/applications/${applicationId}/guarantors`, {
    guarantor_member_id: guarantorMemberId,
  }))
}

export async function cancelGuarantorRequest(applicationId: string, guarantorId: string): Promise<LoanGuarantor> {
  return unwrap<LoanGuarantor>(api.post(`/member/loans/applications/${applicationId}/guarantors/${guarantorId}/cancel`))
}

export async function listGuarantorRequests(filters: { per_page?: number } = {}): Promise<PageResult<LoanGuarantor>> {
  return unwrapPage<LoanGuarantor>(api.get('/member/guarantor-requests', { params: filters }))
}

export async function respondToGuarantorRequest(
  guarantorId: string,
  action: 'accept' | 'decline',
  note?: string,
): Promise<LoanGuarantor> {
  return unwrap<LoanGuarantor>(api.post(`/member/guarantor-requests/${guarantorId}/${action}`, { response_note: note ?? null }))
}

// ------------------------------------------------------------ investigations

export interface SaveInvestigationPayload {
  member_findings?: string | null
  savings_findings?: string | null
  shares_findings?: string | null
  existing_loan_findings?: string | null
  guarantor_findings?: string | null
  committee_comments?: string | null
  recommendation?: string | null
}

export interface SubmitInvestigationPayload extends SaveInvestigationPayload {
  recommendation: string
}

export async function listCommitteeApplications(filters: LoanListFilters = {}): Promise<PageResult<LoanApplication>> {
  return unwrapPage<LoanApplication>(api.get('/committee/loan-applications', { params: filters }))
}

export async function getCommitteeApplication(id: string): Promise<LoanApplication> {
  return unwrap<LoanApplication>(api.get(`/committee/loan-applications/${id}`))
}

export async function updateInvestigation(applicationId: string, payload: SaveInvestigationPayload): Promise<LoanInvestigation> {
  return unwrap<LoanInvestigation>(api.post(`/committee/loan-applications/${applicationId}/investigation`, payload))
}

export async function submitInvestigation(
  applicationId: string,
  payload: SubmitInvestigationPayload,
): Promise<{ investigation: LoanInvestigation; application: LoanApplication }> {
  return unwrap<{ investigation: LoanInvestigation; application: LoanApplication }>(
    api.post(`/committee/loan-applications/${applicationId}/investigation/submit`, payload),
  )
}

// ------------------------------------------------------------ admin lending

export async function listAdminApplications(filters: LoanListFilters = {}): Promise<PageResult<LoanApplication>> {
  return unwrapPage<LoanApplication>(api.get('/admin/loans/applications', { params: filters }))
}

export async function getAdminApplication(id: string): Promise<LoanApplication> {
  return unwrap<LoanApplication>(api.get(`/admin/loans/applications/${id}`))
}

export async function assignInvestigation(applicationId: string, assignedTo: string): Promise<LoanInvestigation> {
  return unwrap<LoanInvestigation>(api.post(`/admin/loans/applications/${applicationId}/assign`, { assigned_to: assignedTo }))
}

export async function cancelApplicationByAdmin(applicationId: string, reason?: string): Promise<LoanApplication> {
  return unwrap<LoanApplication>(api.post(`/admin/loans/applications/${applicationId}/cancel`, { reason: reason ?? null }))
}

export interface ApproveLoanPayload {
  approved_amount_minor: number
  interest_rate_basis_points: number
  interest_method: 'flat' | 'reducing_balance'
  repayment_months: number
  decision_reason: string
}

export async function approveLoanApplication(applicationId: string, payload: ApproveLoanPayload): Promise<Loan> {
  return unwrap<Loan>(api.post(`/admin/loans/applications/${applicationId}/approve`, payload))
}

export async function rejectLoanApplication(applicationId: string, reason: string): Promise<LoanApplication> {
  return unwrap<LoanApplication>(api.post(`/admin/loans/applications/${applicationId}/reject`, { reason }))
}

export type { InvestigationStatus }