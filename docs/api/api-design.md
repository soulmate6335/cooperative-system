# Cooperative System API Design ## 1. Purpose This document defines the REST API contract for the Cooperative System. The API is the communication layer between the React frontend and the Laravel backend. The API must: - use RESTful conventions - return JSON responses - enforce authentication and authorization server-side - validate all incoming data - enforce business rules server-side - protect financial records from unauthorized modification - maintain audit trails for sensitive operations - return consistent response structures - use appropriate HTTP status codes - support pagination for large collections - avoid exposing internal database implementation details unnecessarily --- # 2. API Base URL ## Local Development ```text http://localhost:8000/api
Production

The production API URL will be configured through an environment variable.

Example:

https://api.example.com/api

The frontend must never hard-code the production API URL.

3. API Versioning

The API will use URL-based versioning.

Current version:

/api/v1

Example:

GET /api/v1/members

Future versions may use:

/api/v2

Existing versions should remain available while clients migrate.

4. Authentication

Authentication will use Laravel Sanctum.

The frontend will authenticate through the Laravel API.

Authentication endpoints:

POST /api/v1/auth/login
POST /api/v1/auth/logout
GET  /api/v1/auth/me

Authenticated requests must include the appropriate Sanctum authentication credentials.

Unauthenticated requests to protected endpoints must receive:

401 Unauthorized
5. Authorization

Authorization is enforced by Laravel middleware, policies, gates, and permission checks.

Roles include:

Member
Finance Officer
Committee / Loan Officer
Admin
Super Admin

Authorization must never depend only on frontend route protection.

The backend remains the final authority.

Example:

A member may see their own financial information.

A member must not be able to request another member's financial records by changing an ID in the URL.

6. Standard Response Format

Successful single-resource response:

{
    "success": true,
    "message": "Member retrieved successfully.",
    "data": {}
}

Successful collection response:

{
    "success": true,
    "message": "Members retrieved successfully.",
    "data": [],
    "meta": {
        "current_page": 1,
        "per_page": 20,
        "total": 100,
        "last_page": 5
    }
}

Validation error:

{
    "success": false,
    "message": "The given data was invalid.",
    "errors": {
        "email": [
            "The email field is required."
        ]
    }
}

General error:

{
    "success": false,
    "message": "An unexpected error occurred."
}

The API must not expose:

stack traces
SQL queries
database credentials
internal filesystem paths
sensitive configuration
server secrets

in production responses.

7. HTTP Status Codes

The API will use standard HTTP status codes.

Status	Meaning
200	Successful request
201	Resource successfully created
202	Request accepted for processing
204	Successful request with no response body
400	Bad request
401	Unauthenticated
403	Authenticated but unauthorized
404	Resource not found
409	Conflict
422	Validation/business-rule failure
429	Too many requests
500	Internal server error
503	Service unavailable
8. Public API

These endpoints do not require authentication.

8.1 Homepage
GET /api/v1/public/home

Returns:

organization information
active homepage content
published notices
active executives
relevant public information
9. Public Organization Information
9.1 Organization Information
GET /api/v1/public/organization

Returns public organization information.

Possible response:

{
    "success": true,
    "data": {
        "organization_name": "Cooperative Society",
        "logo": "...",
        "address": "...",
        "phone": "...",
        "email": "...",
        "website": "...",
        "about_us": "...",
        "mission": "...",
        "vision": "...",
        "objectives": []
    }
}
10. Public Executives
10.1 List Executives
GET /api/v1/public/executives

Returns active executives ordered by display order.

Only publicly approved information is returned.

11. Public Notices
11.1 Published Notices
GET /api/v1/public/notices

Returns notices currently available to the public.

Expired or unpublished notices must not be returned.

12. Public Contact
12.1 Submit Contact Message
POST /api/v1/public/contact

Request:

{
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "08000000000",
    "subject": "General enquiry",
    "message": "I would like more information."
}

Validation:

name required
email required and valid
phone optional according to policy
subject required
message required
reasonable maximum lengths enforced

Response:

{
    "success": true,
    "message": "Your message has been submitted successfully."
}
13. Authentication API
13.1 Login
POST /api/v1/auth/login

Request:

{
    "email": "member@example.com",
    "password": "password"
}

The authentication mechanism may later support additional identifiers if required.

Response:

{
    "success": true,
    "message": "Login successful.",
    "data": {
        "user": {},
        "roles": []
    }
}

The API must reject:

invalid credentials
inactive accounts
suspended accounts
rejected membership accounts where login is not permitted
14. Logout
POST /api/v1/auth/logout

Authentication required.

Response:

{
    "success": true,
    "message": "Logout successful."
}
15. Current User
GET /api/v1/auth/me

Authentication required.

Returns:

authenticated user
roles
permissions
member profile when applicable

Sensitive information must not be exposed.

16. Membership Registration
16.1 Submit Membership Application
POST /api/v1/membership/applications

Public endpoint.

Registration data includes:

{
    "full_name": "John Doe",
    "email": "john@example.com",
    "phone": "08000000000",
    "address": "Example address",
    "date_of_birth": "1990-01-01",
    "occupation": "Staff",
    "department": "Engineering",
    "profile_photo": "file"
}

Profile photo is required according to the registration requirement.

The backend must validate:

file type
file size
required fields
email uniqueness where applicable
application duplication
business rules

The system generates an application number.

Initial status:

PENDING
17. Membership Application Status

Possible statuses:

PENDING
UNDER_REVIEW
APPROVED
REJECTED

The exact enum names used in implementation must be consistent across database, backend, frontend, and documentation.

18. Admin Membership Management
18.1 List Applications
GET /api/v1/admin/membership/applications

Admin permission required.

Supports:

?page=1
&per_page=20
&status=PENDING
&search=...
18.2 View Application
GET /api/v1/admin/membership/applications/{application}
18.3 Approve Application
POST /api/v1/admin/membership/applications/{application}/approve

Admin permission required.

Approval should:

validate application state
create or activate member
assign membership number
assign appropriate member role
preserve profile photo reference
record approving admin
record approval timestamp
create notification
trigger approval email
create audit log

Approval must be transactional.

18.4 Reject Application
POST /api/v1/admin/membership/applications/{application}/reject

Request:

{
    "reason": "Required information could not be verified."
}

The rejection reason must be recorded.

The applicant should receive:

in-app notification where applicable
email notification
19. Member Profile API
19.1 Get Own Profile
GET /api/v1/member/profile

Returns the authenticated member's profile.

19.2 Update Own Profile
PATCH /api/v1/member/profile

Members may update permitted profile information.

Financial information must never be editable through this endpoint.

19.3 Upload Profile Photo
POST /api/v1/member/profile/photo

Uses multipart form data.

The backend validates:

file type
file size
storage location

Old files must be handled safely.

20. Member Dashboard
GET /api/v1/member/dashboard

Returns summarized information such as:

membership status
savings balance
contribution balance
shares balance
active loan summary
upcoming repayment
unread notifications

Financial figures must be calculated from authoritative financial records.

The dashboard must not maintain a separate editable balance.

21. Financial Accounts
21.1 Own Accounts
GET /api/v1/member/accounts

Returns the authenticated member's financial accounts.

Account types may include:

SAVINGS
CONTRIBUTION
SHARES
OTHER

The exact set is configurable where appropriate.

22. Account Transactions
22.1 Own Transaction History
GET /api/v1/member/accounts/{account}/transactions

Supports pagination and date filtering.

Example:

?page=1
&per_page=20
&from=2026-01-01
&to=2026-12-31

A member may access only their own accounts.

23. Financial Transactions

Financial transactions represent authoritative financial movements.

A transaction must contain:

account
transaction type
amount
direction
transaction date
reference
status
creator
verification information where required

Financial transactions must not be hard-deleted.

24. Payment Methods
24.1 Publicly Available Payment Methods
GET /api/v1/payment-methods

Authentication may be required depending on final business rules.

Payment methods are admin-configurable.

Examples may include:

bank transfer
cash
approved electronic payment channels

The system must not permanently hard-code only a small predefined list.

25. Payment Recording
25.1 Record Payment
POST /api/v1/finance/payments

Finance Officer permission required.

Request example:

{
    "member_id": "uuid",
    "payment_method_id": "uuid",
    "amount": 10000,
    "payment_date": "2026-09-18",
    "reference_number": "PAY-001",
    "purpose": "SAVINGS",
    "notes": "Monthly savings"
}

The backend must:

validate member
validate payment method
validate amount
validate payment date
validate purpose
create payment
create financial transaction
generate receipt where appropriate
create audit event

Financial posting must be transactional.

26. Payment Verification
26.1 Verify Payment
POST /api/v1/finance/payments/{payment}/verify

Finance Officer or authorized Admin permission required.

Verification must record:

verifier
verification time
status

The system must prevent unauthorized users from verifying payments.

27. Payment Reversal
POST /api/v1/finance/payments/{payment}/reverse

Permission required.

A reversal must not delete the original payment.

Instead, the system creates the appropriate reversal/correction transaction.

Reason is required.

Example:

{
    "reason": "Payment was posted to the wrong account."
}
28. Receipts
28.1 View Receipt
GET /api/v1/receipts/{receipt}

Access must be authorized.

28.2 Download Receipt
GET /api/v1/receipts/{receipt}/download

The API must verify access before allowing the file to be downloaded.

29. Savings

Members can view:

GET /api/v1/member/savings

Returns:

current balance
transaction history
relevant summary

Savings must be calculated from financial transactions.

30. Contributions
GET /api/v1/member/contributions

Returns contribution history and balance.

Minimum contribution rule:

amount >= ₦100

Contribution frequency is configurable by the organization.

31. Shares
31.1 Share Summary
GET /api/v1/member/shares

Returns:

shares purchased
amount paid toward shares
current share-related balance/value according to configured policy

The system must not invent a share-price formula that has not been approved by the organization.

32. Loan Products

Loan products define configurable loan policies.

32.1 List Available Loan Products
GET /api/v1/member/loan-products

Returns active products available to the member.

Product configuration may include:

minimum membership period
minimum amount
maximum amount
interest rate
interest method
repayment period
required guarantors
recovery rules
33. Loan Eligibility
33.1 Check Eligibility
GET /api/v1/member/loans/eligibility

The response should explain eligibility factors.

Example:

{
    "success": true,
    "data": {
        "eligible": true,
        "membership_months": 8,
        "minimum_membership_months": 6,
        "savings_balance": 100000,
        "shares_balance": 50000,
        "maximum_amount": null,
        "reasons": []
    }
}

The API must not invent a maximum-loan formula.

If the organization has not configured a precise formula, the system should return the relevant financial information and indicate that the maximum requires configured policy or administrative review.

34. Loan Application
34.1 Start Loan Application
POST /api/v1/member/loans/applications

Request:

{
    "loan_product_id": "uuid",
    "amount_requested": 100000,
    "purpose": "Business support"
}

The backend validates:

membership status
membership duration
requested amount
active loans
existing obligations
applicable policy
application timing
guarantor requirements
35. Loan Application Deadline

The organization's current rule requires loan applications to be submitted at least:

14 days

before the committee meeting.

The committee meeting is currently expected to occur on:

Second Thursday of each month

However, meeting dates must be stored in the database rather than calculated blindly from a fixed calendar rule.

The system should use the actual configured committee meeting record.

36. Submit Loan Application
POST /api/v1/member/loans/applications/{application}/submit

Submission should:

validate application
validate eligibility
validate guarantor requirements
validate deadline
freeze relevant submitted information
assign appropriate committee workflow
create notification
create audit log

Once submitted, important application fields should not be freely editable.

37. Loan Application Statuses

Possible states:

DRAFT
SUBMITTED
AWAITING_GUARANTORS
GUARANTORS_CONFIRMED
UNDER_INVESTIGATION
COMMITTEE_REVIEWED
PENDING_ADMIN_DECISION
APPROVED
REJECTED
CANCELLED

Exact enum naming will be finalized during implementation.

State transitions must be enforced by the backend.

38. Guarantor Requests
38.1 Request Guarantor
POST /api/v1/member/loans/applications/{application}/guarantors

Request:

{
    "guarantor_member_id": "uuid"
}

Rules:

guarantor must be an active member
exactly two guarantors are required unless policy changes
borrower cannot guarantee their own loan
guarantor must satisfy organization rules
guarantor cannot have prohibited existing guarantee exposure
39. Guarantor Response
39.1 View Pending Requests
GET /api/v1/member/guarantor-requests
39.2 Accept
POST /api/v1/member/guarantor-requests/{request}/accept
39.3 Decline
POST /api/v1/member/guarantor-requests/{request}/decline

Request:

{
    "reason": "Unable to guarantee this application."
}
40. Guarantor Restrictions

The backend must enforce:

only active members can guarantee
borrower cannot guarantee own application
required number of guarantors must be satisfied
duplicate guarantor requests must be prevented
prohibited simultaneous guarantee exposure must be prevented
guarantor responses must be auditable
41. Committee / Loan Investigation
41.1 Assigned Applications
GET /api/v1/committee/loan-applications

Returns applications assigned to the authenticated committee/loan officer.

42. View Loan Application
GET /api/v1/committee/loan-applications/{application}

The committee officer can view information necessary for investigation.

43. Loan Investigation
43.1 Submit Investigation
POST /api/v1/committee/loan-applications/{application}/investigation

Request may include:

{
    "member_findings": "...",
    "savings_findings": "...",
    "shares_findings": "...",
    "existing_loan_findings": "...",
    "guarantor_findings": "...",
    "committee_comments": "...",
    "recommendation": "..."
}

The investigation should record:

investigator
investigation date
findings
recommendation
submission time
44. Committee Authority

The committee investigation does not constitute final loan approval.

The committee submits findings and feedback.

The Admin makes the final approval or rejection decision.

45. Admin Loan Review
45.1 List Pending Decisions
GET /api/v1/admin/loans/pending-decision
45.2 View Application
GET /api/v1/admin/loans/applications/{application}

Admin can review:

member information
savings
shares
existing loans
guarantors
investigation findings
requested amount
proposed terms
46. Loan Approval
POST /api/v1/admin/loans/applications/{application}/approve

Request:

{
    "approved_amount": 100000,
    "interest_rate": 5,
    "interest_method": "REDUCING_BALANCE",
    "repayment_months": 11,
    "decision_reason": "Approved following committee investigation."
}

Approval must:

verify application state
verify committee investigation exists
verify guarantor requirements
validate approved amount
validate interest configuration
validate repayment period
create decision record
snapshot approved terms
notify member
create audit log

Approval does not automatically mean the money has been disbursed.

47. Loan Rejection
POST /api/v1/admin/loans/applications/{application}/reject

Request:

{
    "reason": "Application does not satisfy the applicable lending requirements."
}

Rejection reason is required.

Member receives notification and email.

48. Loan Record

A loan record is created after approval according to the final implementation workflow.

The loan must contain a snapshot of approved terms.

This protects historical accuracy if loan-product settings later change.

49. Loan Disbursement
49.1 Authorize/Process Disbursement
POST /api/v1/admin/loans/{loan}/disburse

Request:

{
    "amount": 100000,
    "payment_method_id": "uuid",
    "reference": "DISB-001",
    "notes": "Approved loan disbursement."
}

Disbursement must be separate from approval.

The system must:

verify approval
verify loan state
verify disbursement amount
verify payment method
record authorization
record disbursement
create required financial transaction
generate relevant receipt/document
update loan status
create notification
create audit log

The entire operation should be transactional.

50. Loan Bond
50.1 Generate Loan Bond
POST /api/v1/admin/loans/{loan}/documents/bond

The system generates the organization's loan bond document using the approved loan information.

The bond may contain:

borrower information
approved amount
amount in words
borrower acknowledgement
borrower signature area
two guarantors
witness section
cancellation section

The exact legal wording must be supplied and approved by the organization.

The system must not invent legal wording as an official institutional document.

51. Loan Documents
51.1 List Documents
GET /api/v1/loans/{loan}/documents
51.2 Download Document
GET /api/v1/loans/{loan}/documents/{document}/download

Access must be authorized.

52. Loan Repayment Schedule
52.1 View Schedule
GET /api/v1/member/loans/{loan}/schedule

The schedule contains:

installment number
due date
principal due
interest due
total due
amount paid
remaining amount
status
53. Generate Repayment Schedule

The backend generates the repayment schedule using the approved loan terms.

Supported interest methods must include the organization's configured methods, such as:

FLAT
REDUCING_BALANCE

The calculation logic must be isolated in a dedicated domain/service layer.

The calculation must be thoroughly unit tested.

54. Record Loan Repayment
54.1 Record Repayment
POST /api/v1/finance/loans/{loan}/repayments

Request:

{
    "amount": 10000,
    "payment_method_id": "uuid",
    "payment_date": "2026-09-18",
    "reference": "REP-001",
    "notes": "Monthly repayment"
}

Finance Officer permission required.

The system must:

validate loan
validate loan state
validate payment method
validate amount
post repayment
allocate amount according to configured repayment rules
update installment payment information
create financial transactions
generate receipt
create audit log

No financial history should be destroyed.

55. Repayment Verification

If repayment verification is enabled:

POST /api/v1/finance/loans/{loan}/repayments/{repayment}/verify

The system records verifier and verification timestamp.

56. Loan Completion

A loan becomes completed when its outstanding obligation reaches zero according to the organization's configured accounting rules.

The system should:

mark the loan completed
close the repayment schedule
record completion timestamp
notify the member
create audit event
57. Loan Default

The system may identify default based on configured rules.

Example factors:

overdue installment
overdue amount
configured grace period

Default rules must be configurable.

The system must not automatically invent a grace period.

58. Loan Recovery
58.1 Apply Recovery
POST /api/v1/admin/loans/{loan}/recoveries

Request:

{
    "source_type": "GUARANTOR_SAVINGS",
    "source_member_id": "uuid",
    "amount": 10000,
    "reason": "Recovery authorized under applicable loan policy."
}

Recovery must require authorization.

The system must record:

loan
source
member
amount
reason
authorizer
processor
timestamp

Recovery must generate appropriate financial transactions.

The exact recovery hierarchy is configurable and must not be hard-coded.

59. Notifications
59.1 Own Notifications
GET /api/v1/member/notifications

Supports pagination.

59.2 Mark Notification Read
POST /api/v1/member/notifications/{notification}/read
59.3 Mark All Read
POST /api/v1/member/notifications/read-all
60. Notification Events

Notifications may be generated for:

registration submitted
membership approved
membership rejected
loan application submitted
guarantor request
guarantor response
committee investigation update
loan approved
loan rejected
loan disbursed
repayment recorded
repayment due
repayment overdue
loan completed
administrative notice
61. Email Notifications

Email notifications are generated through Laravel Mail.

Important email events should be logged.

Email logs include:

recipient
subject
template
status
sent time
failure reason
related entity

Email delivery failure should not silently delete the underlying business event.

62. Admin Notices
62.1 List Notices
GET /api/v1/admin/notices
62.2 Create Notice
POST /api/v1/admin/notices

Request:

{
    "title": "Monthly Meeting",
    "content": "The next meeting will hold on...",
    "priority": "NORMAL",
    "audience": "ALL_MEMBERS",
    "published_at": "2026-09-18T10:00:00",
    "expires_at": "2026-09-30T23:59:59"
}
62.3 Update Notice
PATCH /api/v1/admin/notices/{notice}
62.4 Publish Notice
POST /api/v1/admin/notices/{notice}/publish
62.5 Unpublish Notice
POST /api/v1/admin/notices/{notice}/unpublish
63. Executive Management
63.1 List Executives
GET /api/v1/admin/executives
63.2 Create Executive
POST /api/v1/admin/executives

Fields:

name
position
photo
bio
display order
status
63.3 Update Executive
PATCH /api/v1/admin/executives/{executive}
63.4 Remove/Deactivate Executive
POST /api/v1/admin/executives/{executive}/deactivate

Prefer deactivation over hard deletion when historical references exist.

64. Organization Settings
64.1 Get Settings
GET /api/v1/admin/settings/organization
64.2 Update Settings
PATCH /api/v1/admin/settings/organization

Settings may include:

organization name
logo
address
phone
email
website
about us
mission
vision
objectives
65. Loan Policy Settings

Admin must be able to configure applicable loan policies.

Possible settings include:

minimum membership period
minimum savings
minimum contribution
interest rate
interest method
repayment duration
required guarantors
application deadline
recovery rules
payment methods

Configuration changes must be audited.

66. Committee Meetings
66.1 List Meetings
GET /api/v1/admin/committee-meetings
66.2 Create Meeting
POST /api/v1/admin/committee-meetings

Request:

{
    "meeting_date": "2026-10-08",
    "meeting_type": "LOAN_COMMITTEE",
    "notes": "Monthly loan committee meeting."
}

The actual configured meeting date is authoritative for loan application deadlines.

67. Committee Assignments

The system must support assigning loan investigations to authorized committee/loan officers.

Possible endpoint:

POST /api/v1/admin/loans/applications/{application}/assign

Request:

{
    "assigned_to": "uuid"
}

Assignment must be audited.

68. Reports

Reports must be permission-protected.

68.1 Member Report
GET /api/v1/admin/reports/members

Filters may include:

status
membership date
department
membership type
69. Financial Report
GET /api/v1/admin/reports/financial

Possible filters:

from
to
account_type
transaction_type
payment_method
70. Loan Report
GET /api/v1/admin/reports/loans

Possible filters:

status
product
date range
member
committee meeting
71. Audit Logs
71.1 List Audit Logs
GET /api/v1/admin/audit-logs

Super Admin/Admin permission required.

Audit logs should support filtering by:

user
action
entity type
entity ID
date range

Audit records should not be editable by normal application users.

72. RBAC Management
72.1 Roles
GET /api/v1/admin/roles
POST /api/v1/admin/roles
PATCH /api/v1/admin/roles/{role}
73. Permissions
GET /api/v1/admin/permissions

Permission management should be restricted to appropriate administrative users.

74. User Role Assignment
POST /api/v1/admin/users/{user}/roles

Request:

{
    "role_id": "uuid"
}

Changes must be audited.

75. Pagination

Collection endpoints should support:

?page=1
&per_page=20

Default:

per_page=20

Maximum:

per_page=100

The backend must enforce the maximum.

76. Filtering

Where applicable:

?status=ACTIVE

Multiple filters may be combined.

Example:

?page=1
&per_page=20
&status=APPROVED
&search=john

Only documented filters should be accepted.

77. Sorting

Where appropriate:

?sort=created_at
&direction=desc

The backend must whitelist sortable fields.

Clients must not be allowed to inject arbitrary SQL through sorting parameters.

78. Search

Search parameters should use controlled fields.

Example:

?search=COOP-0001

The backend must use parameterized queries.

79. Date Filtering

Dates should use ISO-compatible formats.

Example:

2026-09-18

Date-time values should use:

2026-09-18T14:30:00

Timezone handling must be consistent across the system.

80. File Uploads

File uploads use:

multipart/form-data

Applicable files include:

member profile photos
executive photos
payment evidence
receipts
loan documents
generated documents

The backend must validate:

MIME type
extension
file size
authorization
storage destination

Uploaded files must not be trusted solely because their filename or extension appears valid.

81. File Storage Security

Sensitive files should not be directly exposed through a public directory.

Private files should be accessed through authorized backend endpoints or temporary signed URLs where supported.

Database records store references such as:

storage/loans/loan-document.pdf

Binary file contents should not be stored directly in PostgreSQL.

82. Financial API Security

Financial endpoints require strict authorization.

A member must never be able to:

create arbitrary financial transactions
change their own balance
change another member's balance
approve their own loan
disburse their own loan
alter repayment history
delete financial records
alter audit logs
83. Separation of Duties

The API must enforce the organization's workflow.

Normal loan flow:

Member
  ↓
Loan Application
  ↓
Guarantors
  ↓
Committee Investigation
  ↓
Admin Decision
  ↓
Authorized Disbursement
  ↓
Repayment

The API must not allow a member to bypass committee investigation and directly approve their own loan.

84. Transaction Boundaries

Operations affecting multiple financial or business records must use database transactions.

Examples:

member approval
payment posting
payment reversal
loan approval
loan disbursement
repayment posting
loan recovery

If one critical operation fails, the transaction should roll back where appropriate.

85. Idempotency

Financial mutation endpoints should support idempotency where appropriate.

For example:

POST /api/v1/finance/payments

should be protected against accidental duplicate submission.

An idempotency key may be supplied through:

Idempotency-Key

The backend should prevent duplicate financial posting for the same valid idempotency key.

86. Concurrency

Financial operations must account for concurrent requests.

Examples:

two users attempting to process the same payment
two administrators attempting to approve the same loan
simultaneous guarantor responses
simultaneous recovery transactions

The backend must use appropriate database locking/transaction mechanisms where necessary.

87. Rate Limiting

Rate limiting should be applied to:

login
password reset
public contact
registration
sensitive mutation endpoints

The exact thresholds should be configurable.

88. Validation

All important validation must happen server-side.

Frontend validation improves user experience but does not replace backend validation.

Examples:

amount >= 100

must be enforced by Laravel even if React already validates it.

89. Business Rule Validation

Business rules must be implemented in domain/service classes rather than scattered throughout controllers.

Examples:

LoanEligibilityService
LoanCalculationService
LoanApprovalService
LoanDisbursementService
RepaymentService
RecoveryService
FinancialPostingService
NotificationService

Controllers should remain thin.

90. Financial Posting Architecture

Financial mutations should follow a controlled flow:

Request
   ↓
Authentication
   ↓
Authorization
   ↓
Validation
   ↓
Business Rule Check
   ↓
Database Transaction
   ↓
Financial Posting
   ↓
Receipt/Document
   ↓
Notification
   ↓
Audit Log
91. Error Handling

Production API errors must be safe and consistent.

Validation errors return:

422

Unauthorized requests return:

401

Forbidden actions return:

403

Missing resources return:

404

Business conflicts return:

409

Unexpected failures return:

500
92. API Resources

Laravel API Resources should be used to control JSON output.

Examples:

UserResource
MemberResource
MemberApplicationResource
FinancialAccountResource
FinancialTransactionResource
PaymentResource
ReceiptResource
LoanApplicationResource
LoanGuarantorResource
LoanInvestigationResource
LoanDecisionResource
LoanResource
LoanInstallmentResource
LoanRepaymentResource
NotificationResource
NoticeResource
ExecutiveResource
AuditLogResource

Sensitive fields must never be exposed accidentally through raw model serialization.

93. Controllers

Controllers should be organized by domain.

Suggested structure:

app/Http/Controllers/Api/V1/
├── Auth/
├── Public/
├── Member/
├── Finance/
├── Committee/
├── Admin/
└── SuperAdmin/

Controllers should delegate complex business operations to services/actions.

94. Form Requests

Laravel Form Request classes should be used for request validation.

Examples:

LoginRequest
StoreMembershipApplicationRequest
UpdateMemberProfileRequest
StorePaymentRequest
VerifyPaymentRequest
StoreLoanApplicationRequest
SubmitLoanApplicationRequest
StoreGuarantorRequest
SubmitInvestigationRequest
ApproveLoanRequest
RejectLoanRequest
DisburseLoanRequest
StoreRepaymentRequest
CreateNoticeRequest
UpdateNoticeRequest
CreateExecutiveRequest
UpdateExecutiveRequest
95. Authorization Policies

Policies should protect resource-level access.

Examples:

MemberPolicy
PaymentPolicy
LoanApplicationPolicy
LoanPolicy
LoanRepaymentPolicy
NoticePolicy
ExecutivePolicy
AuditLogPolicy
96. API Route Organization

Routes should be grouped by middleware and domain.

Conceptual structure:

Route::prefix('v1')->group(function () {

    Route::prefix('public')->group(function () {
        // public endpoints
    });

    Route::prefix('auth')->group(function () {
        // authentication
    });

    Route::middleware('auth:sanctum')->group(function () {

        Route::prefix('member')->group(function () {
            // member endpoints
        });

        Route::prefix('finance')->group(function () {
            // finance endpoints
        });

        Route::prefix('committee')->group(function () {
            // committee endpoints
        });

        Route::prefix('admin')->group(function () {
            // admin endpoints
        });
    });
});

Actual route implementation may use additional middleware and permission checks.

97. API Documentation

The final implementation should generate or maintain machine-readable API documentation.

The documentation should cover:

endpoints
HTTP methods
authentication
parameters
request bodies
response structures
validation rules
status codes
authorization requirements

OpenAPI/Swagger may be introduced during implementation if useful.

98. API Testing

Every critical API workflow must have automated tests.

Minimum coverage should include:

Authentication
login success
invalid login
logout
current user
Membership
registration
duplicate registration
admin approval
admin rejection
unauthorized approval
Financial
payment creation
payment verification
payment reversal
unauthorized financial mutation
duplicate payment protection
Loans
eligibility
application
deadline validation
guarantors
investigation
approval
rejection
disbursement
repayment
completion
recovery
Notifications
notification creation
notification retrieval
read state
Authorization
member cannot access admin endpoints
member cannot access another member's data
committee cannot approve loans
finance officer cannot perform unauthorized admin actions
99. API Security Tests

Security testing must verify:

authentication enforcement
authorization enforcement
IDOR prevention
mass-assignment protection
SQL injection resistance
file upload validation
rate limiting
CSRF/session configuration where applicable
sensitive data exposure prevention
audit logging
100. API Design Principles

The API must follow these principles:

Backend is the source of truth.
Frontend validation is not security.
Financial records are immutable history.
Corrections use reversal/correction transactions.
Approval and disbursement are separate.
Roles and permissions are enforced server-side.
Business policies are configurable.
Historical loan terms are snapshotted.
Sensitive operations are audited.
File references are stored in the database, not binary file contents.
Public content is separated from private member data.
Controllers remain thin.
Business logic belongs in services/domain classes.
API responses remain consistent.
Errors must not expose internal implementation details.
Critical financial workflows must be transactional.
APIs must be tested before production deployment.
101. Final API Workflow
Membership
Public Registration
        ↓
Member Application
        ↓
Admin Review
        ↓
Approve / Reject
        ↓
Member Account
        ↓
Notification + Email
Contribution / Savings
Payment
   ↓
Finance Officer
   ↓
Verification
   ↓
Financial Transaction
   ↓
Receipt
   ↓
Balance Calculation
Loan
Member
   ↓
Eligibility
   ↓
Loan Application
   ↓
Two Guarantors
   ↓
Committee Investigation
   ↓
Committee Feedback
   ↓
Admin Decision
   ↓
Loan Approval
   ↓
Disbursement
   ↓
Repayment Schedule
   ↓
Repayments
   ↓
Completion / Default / Recovery
102. Implementation Rule

This API design is the contract between the React frontend and Laravel backend.

When implementation begins:

database design must remain consistent with this document
Laravel routes must follow this structure
frontend services must consume these API contracts
business rules must be enforced server-side
changes to API behavior must be reflected in this document
undocumented financial behavior must not be introduced casually

Any change affecting business rules, financial calculations, authorization, or workflow must first be reviewed against the requirements, business rules, architecture, and database design documents.