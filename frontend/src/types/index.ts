// Shared API types mirroring the Laravel Resources under backend/app/Http/Resources.

export interface PaginationMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ApiEnvelope<T> {
  success: boolean
  message: string
  data: T
  meta?: PaginationMeta
}

/** Laravel-style JSON error body (message + field errors). */
export interface ApiErrorBody {
  message?: string
  errors?: Record<string, string[]>
}

export interface PageResult<T> {
  data: T[]
  meta: PaginationMeta
}

export type UserStatus = 'active' | 'pending' | 'inactive' | 'suspended'

export interface User {
  id: string
  name: string
  email: string
  status: UserStatus
  roles: string[]
  permissions: string[]
  member: Member | null
}

export interface Member {
  id: string
  member_number: string
  profile_photo: string | null
  joined_at: string | null
  status: string
  membership_type: string | null
  /** Present when the API eager-loads the member's user (e.g. guarantor candidates). */
  name?: string | null
}

export type MemberApplicationStatus = 'pending' | 'approved' | 'rejected'

export interface MemberApplication {
  id: string
  application_number: string
  full_name: string
  email: string
  phone: string
  address: string
  date_of_birth: string | null
  occupation: string
  department: string | null
  profile_photo: string | null
  status: MemberApplicationStatus
  submitted_at: string | null
  reviewed_at: string | null
  rejection_reason: string | null
  reviewer: User | null
}

export interface LoanProduct {
  id: string
  name: string
  description: string | null
  minimum_membership_months: number
  minimum_amount_minor: number
  maximum_amount_minor: number | null
  interest_rate_basis_points: number
  interest_method: 'flat' | 'reducing_balance'
  repayment_months: number
  required_guarantors: number
  status: 'active' | 'inactive'
  created_at: string
  updated_at: string
}

export type GuarantorStatus = 'pending' | 'accepted' | 'declined' | 'cancelled'

export interface LoanGuarantor {
  id: string
  loan_application_id: string
  guarantor_member: Member | null
  status: GuarantorStatus
  requested_at: string | null
  responded_at: string | null
  responded_by: string | null
  response_note: string | null
  created_at: string
  updated_at: string
  /** Present in the guarantor self-service list endpoint only. */
  application?: LoanApplication
}

export type InvestigationStatus = 'assigned' | 'in_progress' | 'returned' | 'submitted'

export interface LoanInvestigation {
  id: string
  loan_application_id: string
  assigned_to: string
  /** Present on admin decision detail, where the assignee user is eager-loaded. */
  assignee?: { id: string; name: string } | null
  investigation_date: string | null
  member_findings: string | null
  savings_findings: string | null
  shares_findings: string | null
  existing_loan_findings: string | null
  guarantor_findings: string | null
  committee_comments: string | null
  recommendation: string | null
  status: InvestigationStatus
  submitted_at: string | null
  created_at: string
  updated_at: string
}

export interface LoanDecision {
  id: string
  loan_application_id: string
  decided_by: string
  /** Decision maker name; present on admin endpoints that eager-load the user. */
  decided_by_name: string | null
  decision: 'approved' | 'rejected'
  approved_amount_minor: number | null
  interest_rate_basis_points: number | null
  interest_method: string | null
  repayment_months: number | null
  decision_reason: string | null
  decided_at: string | null
  created_at: string
}

export interface Loan {
  id: string
  loan_application_id: string
  member_id: string
  loan_number: string
  principal_amount_minor: number
  interest_rate_basis_points: number
  interest_method: string
  repayment_months: number
  interest_amount_minor: number | null
  total_payable_minor: number | null
  disbursed_amount_minor: number | null
  disbursed_at: string | null
  start_date: string | null
  maturity_date: string | null
  status: string
  created_at: string
  updated_at: string
}

export type LoanStatus = 'pending_disbursement' | 'disbursed' | 'completed'

export interface LoanDisbursement {
  id: string
  loan_id: string
  amount_minor: number
  payment_method_id: string | null
  financial_account_id: string
  reference: string | null
  status: string
  disbursed_at: string | null
  authorized_by: string
}

export interface LoanInstallment {
  id: string
  loan_id: string
  installment_number: number
  due_date: string | null
  principal_due_minor: number
  interest_due_minor: number
  total_due_minor: number
  principal_paid_minor: number
  interest_paid_minor: number
  total_paid_minor: number
  outstanding_minor: number
  status: string
  paid_at: string | null
}

export interface LoanObligations {
  total_principal_minor: number
  total_interest_minor: number
  total_obligation_minor: number
  principal_paid_minor: number
  interest_paid_minor: number
  total_paid_minor: number
  principal_outstanding_minor: number
  interest_outstanding_minor: number
  total_outstanding_minor: number
  next_due_installment: LoanInstallment | null
}

/** Composed loan view returned by the admin disbursement area and member loan pages. */
export interface LoanOverview {
  id: string
  loan_application_id: string
  loan_number: string
  status: LoanStatus
  principal_amount_minor: number
  interest_rate_basis_points: number
  interest_method: string
  repayment_months: number
  interest_amount_minor: number | null
  total_payable_minor: number | null
  disbursed_amount_minor: number
  disbursed_at: string | null
  start_date: string | null
  maturity_date: string | null
  member: {
    id: string
    member_number: string
    name: string | null
    status: string
    membership_type: string | null
  } | null
  product: { id: string; name: string } | null
  decision: {
    decision: 'approved' | 'rejected'
    approved_amount_minor: number | null
    interest_rate_basis_points: number | null
    interest_method: string | null
    repayment_months: number | null
    decision_reason: string | null
    decided_by: string
    decided_by_name: string | null
    decided_at: string | null
  } | null
  disbursement: LoanDisbursement | null
  installments: LoanInstallment[] | null
  obligations: LoanObligations | null
  created_at: string
  updated_at: string
}

export interface LoanDisbursementResult {
  loan: LoanOverview
  disbursement: LoanDisbursement
  transaction: FinancialTransaction
}

export interface CommitteeMeeting {
  id: string
  meeting_date: string | null
  meeting_type: string
  cutoff_days: number
  status: string
  notes: string | null
  created_by: string
  created_at: string
  updated_at: string
}

export type LoanApplicationStatus =
  | 'draft'
  | 'submitted'
  | 'awaiting_guarantors'
  | 'guarantors_confirmed'
  | 'under_investigation'
  | 'committee_reviewed'
  | 'pending_admin_decision'
  | 'approved'
  | 'rejected'
  | 'cancelled'

export interface LoanApplication {
  id: string
  application_number: string
  member: Member | null
  loan_product: LoanProduct | null
  committee_meeting: CommitteeMeeting | null
  amount_requested_minor: number
  purpose: string
  status: LoanApplicationStatus
  savings_balance_minor: number | null
  shares_balance_minor: number | null
  submitted_at: string | null
  rejection_reason: string | null
  guarantors: LoanGuarantor[]
  investigation: LoanInvestigation | null
  decision: LoanDecision | null
  loan: Loan | null
  created_at: string
  updated_at: string
}

export type EligibilityDecisionStatus = 'pending' | 'eligible' | 'ineligible'

/** Authoritative administrative decision for a member + product pair. */
export interface EligibilityDecision {
  status: EligibilityDecisionStatus
  decided_by: { id: string; name: string } | null
  reason: string | null
  decided_at: string | null
}

export interface EligibilityFactors {
  eligible: boolean
  membership_months: number
  minimum_membership_months: number
  member_active: boolean
  product_active: boolean
  savings_balance_minor: number
  shares_balance_minor: number
  minimum_amount_minor: number
  maximum_amount_minor: number | null
  computed_maximum_amount_minor: number | null
  reasons: string[]
  /** Authoritative admin decision; pending when no decision exists yet. */
  admin_decision: EligibilityDecision
}

/** Member picker row returned by the admin eligibility review endpoints. */
export interface EligibilityMemberSummary extends Member {
  name: string | null
}

/** Full eligibility review payload for an administrator. */
export interface EligibilityAssessment {
  member: EligibilityMemberSummary
  product: LoanProduct
  factors: EligibilityFactors
}

/** Admin final decision detail: the application review surface + eligibility context. */
export interface AdminLoanDecisionDetail {
  application: LoanApplication
  eligibility: {
    decision: EligibilityDecision
    factors: EligibilityFactors
  }
}

export type AccountType = 'contribution' | 'savings' | 'shares' | 'loan'

export interface FinancialAccount {
  id: string
  member_id: string
  account_type: AccountType
  account_number: string
  status: string
  balance_minor?: number
}

export interface FinancialTransaction {
  id: string
  account_id: string
  payment_id: string | null
  reverses_transaction_id: string | null
  transaction_type: string
  direction: 'credit' | 'debit'
  amount_minor: number
  transaction_date: string | null
  reference: string
  description: string | null
  status: string
  created_by: string
  posted_by: string | null
  posted_at: string | null
}

export interface PaymentMethod {
  id: string
  code: string
  name: string
  description: string | null
  is_active: boolean
  display_order: number
}

export type PaymentPurpose = 'contribution' | 'savings' | 'shares' | 'loan_repayment'

export interface Payment {
  id: string
  member_id: string
  loan_id: string | null
  payment_method_id: string
  amount_minor: number
  payment_date: string | null
  reference_number: string
  purpose: PaymentPurpose
  status: string
  recorded_by: string
  verified_by: string | null
  verified_at: string | null
}

export type NoticeStatus = 'draft' | 'published' | 'archived'
export type NoticeVisibility = 'public' | 'members'

/** Public-safe notice shape (homepage, public + member notice APIs). */
export interface Notice {
  id: string
  title: string
  excerpt: string | null
  body: string
  visibility: NoticeVisibility
  publish_at: string | null
  expires_at: string | null
  created_at: string | null
}

/** Admin notice shape: adds lifecycle status and authorship. */
export interface AdminNotice extends Notice {
  status: NoticeStatus
  created_by: string | null
  updated_by: string | null
  updated_at: string | null
}

/** Public executive shape (never exposes internal visibility/author fields). */
export interface Executive {
  id: string
  name: string
  position: string
  biography: string | null
  photo_url: string | null
  display_order: number
}

/** Admin executive shape: adds the visibility flag and authorship. */
export interface AdminExecutive extends Executive {
  is_visible: boolean
  created_by: string | null
  updated_by: string | null
  created_at: string | null
  updated_at: string | null
}

/** Admin-editable homepage configuration served through the public home API. */
export interface OrganizationContent {
  hero_title: string | null
  hero_description: string | null
  hero_image_url: string | null
  introduction: string | null
  updated_at: string | null
}

/** Aggregate payload returned by GET /public/home. */
export interface PublicHome {
  content: OrganizationContent | null
  notices: Notice[]
  executives: Executive[]
}

/** Personal notification inbox entry (scoped to the authenticated user). */
export interface AppNotification {
  id: string
  type: string
  title: string | null
  message: string | null
  read_at: string | null
  created_at: string | null
  data: {
    reference_type: string | null
    reference_id: string | null
    reference: string | null
  }
}