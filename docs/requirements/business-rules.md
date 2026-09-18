# Cooperative System — Business Rules

## 1. Membership

- A person must register before becoming a member.
- Registration creates a pending membership application.
- Registration does not automatically activate a member.
- An Admin reviews and approves or rejects the membership application.
- A member receives a notification when the application is approved or rejected.
- A member must have an approved and active membership before using member financial services.
- A member profile may contain a profile photograph.
- Member profile photographs must be stored as files/references, not as binary data inside the database.

## 2. Contributions

- Members may contribute any amount starting from ₦100.
- Contribution frequency is controlled by the organization/Admin.
- Contributions must be recorded as financial transactions.
- Financial balances must be derived from transaction history rather than manually edited.
- Financial transactions must not be hard-deleted.
- Corrections should be handled through appropriate reversal/correction transactions.

## 3. Savings

- Members may save any amount starting from ₦100.
- Savings transactions must be recorded against the member's savings account.
- Savings balance is derived from the financial transaction history.
- Savings history must be available to the member and authorized staff.

## 4. Shares

- Members may purchase/pay for shares.
- Share ownership is based on the organization's configured share rules.
- The exact share price/formula is configurable because the organization has not yet provided a fixed formula.
- Share transactions must be recorded and auditable.
- Share balance is derived from transaction history.

## 5. Payments

- The system must record how a payment was made.
- Payment methods must be configurable by authorized administrators.
- Examples may include cash, bank transfer, POS, salary deduction, cheque, or other organization-approved methods.
- A payment may have a reference number and supporting receipt/evidence.
- Payments may require verification depending on the workflow.
- Financial records must preserve who recorded and/or verified a payment.

## 6. Loan Eligibility

- A member must have been a member for at least 6 months before applying for a loan.
- Loan eligibility must consider the member's savings and shares.
- The organization has not yet provided an exact formula for calculating the maximum loan amount.
- Therefore, the loan eligibility/max-loan calculation must be configurable rather than hard-coded.

## 7. Loan Application

- A member may apply for a loan after satisfying the configured eligibility requirements.
- The loan application must contain the requested amount and purpose.
- A loan application must have two guarantors/sureties.
- The guarantors must be active members.
- A member must not guarantee multiple active loans simultaneously.
- Loan applications must be submitted at least 14 days before the applicable committee meeting.
- The current organizational committee meeting is held on the second Thursday of each month, but the meeting schedule must remain configurable.
- The 14-day rule applies to the actual loan application submission date, not to an earlier notification or intention to apply.

## 8. Guarantors

- Exactly two guarantors are required for a standard loan application unless an authorized loan product specifies otherwise.
- A guarantor must be an active member.
- A guarantor must respond to a guarantor request.
- The system must record the guarantor's response and date.
- A member cannot simultaneously guarantee multiple active loans.

## 9. Loan Investigation

- A submitted loan application is investigated by the assigned Committee/Loan Officer.
- The investigation may examine:
  - Membership status
  - Savings
  - Shares
  - Existing loans
  - Existing obligations
  - Guarantors
  - Other relevant financial information
- Committee members submit findings and comments.
- The Committee does not make the final loan approval decision.
- Committee findings are submitted to the Admin for final review.

## 10. Loan Approval

- The Admin makes the final loan approval or rejection decision after receiving the Committee's investigation.
- Approval and disbursement are separate processes.
- An approved loan must record the approved amount and applicable loan terms.
- Interest rate is determined by the organization/Admin.
- Interest method may be:
  - Flat
  - Reducing balance
- Repayment duration is currently 11 months.
- Loan terms must be stored with the loan so that historical loans retain the terms that were approved for them.

## 11. Loan Disbursement

- An approved loan is not considered disbursed until an authorized disbursement transaction is recorded.
- The disbursed amount must be recorded.
- The disbursement payment method must be recorded.
- The disbursement must have an auditable reference.
- Authorized and processing staff must be recorded.

## 12. Loan Repayment

- Standard repayment duration is currently 11 months.
- A repayment schedule must be generated from the approved loan terms.
- Repayments must record:
  - Amount
  - Date
  - Payment method
  - Reference
  - Recording officer
  - Verification status where applicable
- Partial payments must be supported.
- The system must track outstanding principal and interest.
- A loan becomes completed when all required payable amounts have been settled.

## 13. Loan Default and Recovery

- Default/recovery behavior is determined by the organization and may vary by loan/product.
- Recovery rules must therefore be configurable.
- Where recovery from member or guarantor savings/shares is authorized, the system must record:
  - Recovery source
  - Member
  - Amount
  - Reason
  - Authorization
  - Processing details
- Recovery must be auditable and must not silently alter financial balances.

## 14. Loan Documents

The system should support digital versions of relevant loan documents, including:

- Loan application
- Guarantor/surety information
- Loan bond
- Supporting documents
- Other organization-approved loan documents

Generated documents must retain their version and generation information.

## 15. Notices and Notifications

- Admin users can create notices.
- Admin users can publish notices to members.
- Notices may have publication and expiration dates.
- Notices may be targeted to an audience.
- Important system events should generate in-app notifications.
- Where configured, important notifications should also be sent by email.
- Email delivery should be logged.

## 16. Homepage and Public Content

The public homepage may contain:

- Organization information
- Admin-controlled hero section
- Important notices
- Executives
- Executive photographs
- Executive biographies
- Contact information

Authorized Admin users control this content.

## 17. Executives

- Admin users can add executives.
- Admin users can update executive information.
- Admin users can upload executive photographs.
- Admin users can control display order and publication status.

## 18. Organization Information

Authorized administrators can manage:

- Organization name
- Logo
- Address
- Phone number
- Email
- Website
- About Us
- Mission
- Vision
- Objectives

## 19. Contact Messages

Visitors may submit contact messages.

The system should record:

- Name
- Email
- Phone
- Subject
- Message
- Status
- Handling staff
- Handling date

## 20. Roles and Permissions

The system uses role-based access control.

Core roles:

### Member
Can:
- Manage own profile
- View own financial information
- View statements
- View receipts
- Apply for loans
- Respond to guarantor requests
- View notifications

### Finance Officer
Can:
- Record payments
- Verify payments
- Record financial transactions
- View financial records
- Generate authorized financial reports

### Committee / Loan Officer
Can:
- View assigned loan applications
- Investigate applications
- Review relevant member financial information
- Record findings
- Submit investigation feedback

Cannot:
- Give final loan approval

### Admin
Can:
- Approve/reject members
- Manage members
- Configure organization policies
- Review loan investigations
- Approve/reject loans
- Authorize disbursements
- Manage notices
- Manage executives
- Manage public content
- View reports
- Manage configurable payment methods and loan products

### Super Admin
Can additionally:
- Manage staff roles and permissions
- Manage system/security settings
- Review audit logs
- Perform privileged administrative operations

## 21. Auditability

Sensitive operations must be auditable.

Examples:

- Member approval/rejection
- Loan approval/rejection
- Loan disbursement
- Payment verification
- Financial corrections
- Recovery transactions
- Role/permission changes
- Important configuration changes

Audit records should identify the actor, action, affected entity, and relevant before/after information where appropriate.

## 22. Financial Integrity

The system must prioritize financial integrity.

Rules:

- No hard deletion of financial transactions.
- Financial balances should be derived from transaction records.
- Corrections should preserve the original transaction history.
- Approval, authorization, and execution should be separate where appropriate.
- Every important financial action must be traceable to an authenticated user.
