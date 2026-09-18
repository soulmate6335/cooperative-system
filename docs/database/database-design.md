# Cooperative System — Database Design ## 1. Database Overview The Cooperative System uses PostgreSQL as its primary relational database. The database is designed to support: - Member registration and approval - User authentication - Role-Based Access Control - Contributions - Savings - Shares - Payments - Receipts - Loan products - Loan applications - Guarantors - Committee investigations - Loan decisions - Loan disbursements - Loan repayment schedules - Loan repayments - Loan recovery - Notifications - Email logging - Organization content - Executives - Notices - Contact messages - Documents - Audit logging The database must preserve financial history and must not depend on manually editable balance fields. --- # 2. Database Principles ## 2.1 Relational Integrity Relationships between records must use foreign keys. Records that depend on other records must not be allowed to reference nonexistent records. --- ## 2.2 UUID Primary Keys Application entities should use UUID primary keys. Examples: - users - members - payments - loans - loan applications - notifications - audit logs Human-readable numbers such as member numbers, receipt numbers, and loan numbers should be stored separately from internal UUIDs. --- ## 2.3 Timestamps Business entities should normally contain: - created_at - updated_at Historical records may also contain business-specific timestamps such as: - approved_at - rejected_at - verified_at - disbursed_at - submitted_at - processed_at --- ## 2.4 Financial Integrity Financial balances must be derived from transactions. The application must not directly modify a member's financial balance. Example: ```text Savings Account Transaction 1: + ₦10,000 Transaction 2: + ₦5,000 Transaction 3: - ₦2,000 Current Balance = ₦13,000
2.5 No Hard Deletion of Financial Records

Financial records must not be hard-deleted after they become part of the financial history.

Incorrect transactions should be corrected through:

Reversal transactions
Correction transactions
Proper authorization
Audit logging
3. Entity Groups

The database is organized into the following logical groups.

Identity and Access
users
roles
permissions
user_roles
role_permissions
Membership
member_applications
members
Financial
financial_accounts
financial_transactions
payment_methods
payments
receipts
Loans
loan_products
loan_applications
loan_guarantors
loan_investigations
loan_decisions
loans
loan_disbursements
loan_installments
loan_repayments
loan_recovery_rules
loan_recoveries
Communication
notices
notifications
email_logs
Organization
executives
organization_settings
contact_messages
Documents
loan_documents
Audit
audit_logs
4. Users
Table: users

Stores authentication identities.

Fields
id
email
password
status
last_login_at
created_at
updated_at
Notes

The user account is separate from the member profile.

A user may represent:

Member
Finance Officer
Committee / Loan Officer
Admin
Super Admin

Authentication information belongs here.

Member-specific information belongs in the members table.

5. Roles
Table: roles

Stores system roles.

Fields
id
name
description
created_at
updated_at
Suggested Roles
member
finance_officer
committee_officer
admin
super_admin
6. Permissions
Table: permissions

Stores individual system permissions.

Fields
id
name
description
created_at
updated_at
Examples
members.view
members.create
members.approve
members.reject
members.suspend

payments.view
payments.create
payments.verify
payments.correct

loans.view
loans.apply
loans.investigate
loans.approve
loans.reject
loans.disburse
loans.repay
loans.recover

reports.view
audit.view

notices.view
notices.manage

executives.view
executives.manage

settings.view
settings.manage

roles.manage
permissions.manage
7. User Roles
Table: user_roles

Connects users to roles.

Fields
id
user_id
role_id
created_at
Relationships
users 1 ---- * user_roles
roles 1 ---- * user_roles

A user may have one or more roles.

8. Role Permissions
Table: role_permissions

Connects roles to permissions.

Fields
id
role_id
permission_id
created_at
Relationships
roles 1 ---- * role_permissions
permissions 1 ---- * role_permissions

This allows the organization to change permissions without rewriting application code.

9. Member Applications
Table: member_applications

Stores applications submitted by prospective members.

Fields
id
user_id
application_number
full_name
email
phone
address
date_of_birth
occupation
department
profile_photo
status
submitted_at
reviewed_at
reviewed_by
rejection_reason
created_at
updated_at
Status

Suggested states:

pending
approved
rejected
Important Rule

Registration does not automatically activate membership.

The application remains pending until an authorized administrator reviews it.

10. Members
Table: members

Stores approved member profiles.

Fields
id
user_id
member_number
profile_photo
joined_at
status
membership_type
approved_by
approved_at
created_at
updated_at
Suggested Status
active
suspended
inactive
Relationship
users 1 ---- 0..1 members

A member must be associated with a user account.

11. Member Number

The member number is separate from the internal UUID.

Example:

MEM-000001

The exact numbering format can be changed by the organization later.

The database must enforce uniqueness.

12. Financial Accounts
Table: financial_accounts

Represents a member's financial accounts.

Fields
id
member_id
account_type
account_number
status
created_at
updated_at
Account Types
CONTRIBUTION
SAVINGS
SHARES
OTHER

The exact enum implementation may be replaced with configuration if the organization later requires custom account types.

13. Financial Transactions
Table: financial_transactions

This is the core financial ledger.

Fields
id
account_id
transaction_type
amount
direction
transaction_date
reference
description
payment_id
status
created_by
verified_by
verified_at
created_at
updated_at
Transaction Direction
credit
debit
Examples
Contribution payment
Savings deposit
Savings withdrawal
Share purchase
Financial correction
Financial reversal
14. Financial Transaction Rules

Every financial transaction must:

Belong to an account.
Have a positive transaction amount.
Specify a direction.
Have a transaction date.
Have an identifiable creator.
Be auditable.
Preserve historical information.

A transaction amount should never be stored as a negative number.

The direction determines whether it increases or decreases the account balance.

15. Payment Methods
Table: payment_methods

Payment methods are configurable by administrators.

Fields
id
name
description
status
created_at
updated_at
Possible Examples
Cash
Bank Transfer
POS
Salary Deduction
Cheque
Other

These are examples only.

The organization can add or deactivate payment methods.

16. Payments
Table: payments

Records money received from members before financial allocation/verification.

Fields
id
member_id
payment_method_id
amount
payment_date
reference_number
purpose
status
receipt_id
recorded_by
verified_by
verified_at
notes
created_at
updated_at
Suggested Status
pending
verified
rejected
reversed
17. Payment Verification

A payment may be recorded by one staff member and verified by another authorized staff member.

This supports separation of duties.

Example:

Finance Officer
      |
      v
Records Payment
      |
      v
Pending Verification
      |
      v
Authorized Verifier
      |
      v
Verified Payment
18. Receipts
Table: receipts

Stores receipt records.

Fields
id
payment_id
receipt_number
file_path
issued_at
issued_by
created_at
updated_at

The receipt number must be unique.

Receipts may be generated digitally and stored as files.

19. Loan Products
Table: loan_products

Defines configurable loan types and their rules.

Fields
id
name
description
minimum_membership_months
minimum_amount
maximum_amount
interest_rate
interest_method
repayment_months
required_guarantors
status
created_at
updated_at
Interest Methods

Possible values:

flat
reducing_balance

The final method is determined by the organization.

20. Loan Product Rules

Loan products allow administrators to configure policies.

Possible configuration includes:

Minimum membership duration
Minimum loan amount
Maximum loan amount
Interest rate
Interest method
Repayment period
Required guarantors

The system must not hard-code a loan formula when the organization has not supplied one.

21. Loan Applications
Table: loan_applications

Stores member loan applications.

Fields
id
application_number
member_id
loan_product_id
amount_requested
purpose
submitted_at
committee_meeting_id
status
rejection_reason
created_at
updated_at
Suggested Statuses
draft
submitted
awaiting_guarantors
under_investigation
pending_admin_decision
approved
rejected
cancelled
22. Loan Application Deadline

The organization currently requires loan applications to be submitted at least 14 days before the relevant committee meeting.

The application deadline must be calculated from:

committee meeting date - 14 days

The exact meeting schedule should not be permanently hard-coded.

23. Committee Meetings
Table: committee_meetings

Stores committee meeting information.

Fields
id
meeting_date
meeting_type
status
notes
created_by
created_at
updated_at
Example

The organization currently holds loan committee meetings on the second Thursday of the month.

This should be treated as an organizational scheduling rule rather than a permanent database assumption.

24. Loan Guarantors
Table: loan_guarantors

Stores guarantor requests and responses.

Fields
id
loan_application_id
guarantor_member_id
status
requested_at
responded_at
response
response_note
created_at
updated_at
Suggested Status
pending
accepted
declined
cancelled
Business Rules
Each loan application requires 2 guarantors unless the loan product says otherwise.
A guarantor must be an active member.
A member cannot provide simultaneous active guarantees for multiple loans.
Guarantor acceptance must be recorded.
25. Guarantor Eligibility

Before accepting a guarantor:

The backend must verify:

Member exists
        |
        v
Member is active
        |
        v
Member is not already carrying an active guarantee obligation
        |
        v
Guarantor can accept request

The frontend must not be trusted to enforce these rules.

26. Loan Investigations
Table: loan_investigations

Stores Committee investigation findings.

Fields
id
loan_application_id
assigned_to
investigation_date
member_findings
savings_findings
shares_findings
existing_loan_findings
guarantor_findings
committee_comments
recommendation
status
submitted_at
created_at
updated_at
Suggested Status
assigned
in_progress
submitted
returned
27. Committee Investigation

The Committee may investigate:

Membership status
Savings
Shares
Existing loans
Existing obligations
Guarantors
Other relevant financial information

The Committee submits findings to the Admin.

The Committee does not make the final loan approval decision.

28. Loan Decisions
Table: loan_decisions

Stores the final administrative decision.

Fields
id
loan_application_id
decided_by
decision
approved_amount
interest_rate
interest_method
repayment_months
decision_reason
decided_at
created_at
Decision Values
approved
rejected

The approved loan terms are stored here as a historical snapshot.

29. Loan Terms Snapshot

The loan decision stores the terms that were actually approved.

This is important because loan product settings may change later.

Example:

Loan Product

Interest: 10%
Repayment: 11 months

Existing Loan

Interest: 10%
Repayment: 11 months

Changing the product later must not change the existing loan.

30. Loans
Table: loans

Represents an approved and/or active loan.

Fields
id
loan_application_id
member_id
loan_number
principal_amount
interest_amount
total_payable
interest_method
repayment_months
disbursed_amount
disbursed_at
start_date
maturity_date
status
created_at
updated_at
Suggested Statuses
approved
pending_disbursement
active
overdue
defaulted
completed
cancelled
31. Loan Financial Calculations

The backend is responsible for calculating:

Interest
Total payable
Installments
Outstanding principal
Outstanding interest
Remaining balance

The frontend only displays values supplied by the API.

32. Interest Calculation

The system supports configurable interest methods.

Flat Interest

Interest may be calculated from the original principal according to the organization's approved formula.

Reducing Balance

Interest may be calculated based on the remaining principal according to the organization's approved formula.

The exact formula must be documented and tested before production use.

33. Loan Disbursements
Table: loan_disbursements

Stores actual loan disbursement transactions.

Fields
id
loan_id
amount
payment_method_id
reference
disbursed_at
authorized_by
processed_by
receipt_id
notes
created_at

Approval does not automatically mean the loan has been disbursed.

34. Loan Disbursement Separation

The workflow is:

Loan Application
       |
       v
Committee Investigation
       |
       v
Admin Approval
       |
       v
Pending Disbursement
       |
       v
Authorized Disbursement
       |
       v
Active Loan
35. Loan Installments
Table: loan_installments

Stores scheduled repayment installments.

Fields
id
loan_id
installment_number
due_date
principal_due
interest_due
total_due
principal_paid
interest_paid
amount_paid
status
created_at
updated_at
Suggested Status
pending
partially_paid
paid
overdue
36. Repayment Schedule

The system generates the schedule after loan disbursement.

The repayment period is normally 11 months according to the current organization rule.

However, the final number of installments should come from the approved loan terms.

37. Loan Repayments
Table: loan_repayments

Records actual repayment transactions.

Fields
id
loan_id
member_id
amount
payment_method_id
payment_date
reference
receipt_id
status
recorded_by
verified_by
verified_at
notes
created_at
updated_at
Suggested Status
pending
verified
rejected
reversed
38. Partial Repayments

The system must support partial repayment.

Example:

Installment Due: ₦50,000

Payment 1: ₦20,000
Payment 2: ₦30,000

Installment Status: Paid

The backend determines allocation between principal and interest according to the approved repayment rules.

39. Loan Completion

A loan becomes completed when all required payable amounts have been settled according to the approved loan terms.

Completion must be calculated by the backend.

40. Loan Recovery Rules
Table: loan_recovery_rules

Stores configurable recovery policies.

Fields
id
loan_product_id
name
description
priority
enabled
created_at
updated_at

Recovery rules are configurable because the organization has not supplied one universal recovery formula.

41. Loan Recoveries
Table: loan_recoveries

Stores actual recovery actions.

Fields
id
loan_id
source_type
source_member_id
amount
reason
authorized_by
processed_by
processed_at
notes
created_at
Possible Source Types

Examples:

member_savings
member_shares
guarantor_savings
guarantor_shares
other

These are configurable concepts and must only be used when authorized by the applicable loan recovery policy.

42. Recovery Authorization

Recovery affecting a member's financial account must require appropriate authorization.

The recovery must generate:

Financial transaction
Recovery record
Audit record
43. Notices
Table: notices

Stores organization-wide announcements.

Fields
id
title
content
image
priority
audience
published_at
expires_at
status
created_by
created_at
updated_at
Suggested Status
draft
published
archived
44. Notice Audience

Possible audience values:

public
members
staff
all

The final audience model can be expanded later if necessary.

45. Notifications
Table: notifications

Stores in-app notifications.

Fields
id
user_id
type
title
message
reference_type
reference_id
read_at
created_at

Examples:

Membership Approved
Loan Application Submitted
Guarantor Request
Loan Approved
Loan Rejected
Payment Verified
Organization Notice
46. Email Logs
Table: email_logs

Stores email delivery history.

Fields
id
user_id
recipient
subject
template
status
sent_at
failure_reason
reference_type
reference_id
created_at
Suggested Status
pending
sent
failed

Email failures should not destroy the associated business transaction.

47. Executives
Table: executives

Stores organization executive information.

Fields
id
name
position
photo
bio
display_order
status
created_by
updated_by
created_at
updated_at

Executives are displayed on the public homepage.

Administrators control:

Photo
Name
Position
Biography
Display order
Active/inactive status
48. Organization Settings
Table: organization_settings

Stores organization-wide information.

Fields
id
organization_name
logo
address
phone
email
website
about_us
mission
vision
objectives
created_at
updated_at

This supports the public:

About Us
Contact
Organization information
49. Contact Messages
Table: contact_messages

Stores messages submitted through the public contact form.

Fields
id
name
email
phone
subject
message
status
handled_by
handled_at
created_at
updated_at
Suggested Status
new
in_progress
resolved
archived
50. Loan Documents
Table: loan_documents

Stores metadata for loan-related documents.

Fields
id
loan_id
document_type
file_path
version
generated_at
generated_by
created_at
Possible Document Types
loan_application
loan_bond
approval_letter
repayment_schedule
supporting_document
other

Files are stored outside the database.

51. Profile Photographs

Member profile photographs must be stored as files.

The database stores:

profile_photo

as a file path/reference.

The database should not store the image binary directly.

52. File Security

Private files must not be directly exposed through public URLs.

Access should be authorized through the backend.

Examples:

Member documents
Loan bonds
Financial receipts
Supporting loan documents

Public executive photographs may be stored separately because they are intentionally displayed publicly.

53. Audit Logs
Table: audit_logs

Stores security and business audit information.

Fields
id
user_id
action
entity_type
entity_id
old_values
new_values
ip_address
user_agent
created_at
54. Auditable Operations

Important operations should create audit records.

Examples:

Member Approval
Member Rejection
Member Suspension

Payment Creation
Payment Verification
Payment Reversal
Financial Correction

Loan Approval
Loan Rejection
Loan Disbursement
Loan Recovery

Role Assignment
Permission Change

Organization Setting Change
Loan Policy Change
55. JSON Audit Values

The following fields may use PostgreSQL JSON/JSONB:

old_values
new_values

This allows the audit system to preserve relevant before-and-after information.

56. Relationships

The major relationships are:

users
  |
  +---- member_applications
  |
  +---- members
  |
  +---- user_roles
  |
  +---- notifications
  |
  +---- audit_logs

members
  |
  +---- financial_accounts
  |
  +---- payments
  |
  +---- loan_applications
  |
  +---- loans
  |
  +---- loan_guarantors
  |
  +---- loan_repayments

financial_accounts
  |
  +---- financial_transactions

payments
  |
  +---- receipts
  |
  +---- financial_transactions

loan_products
  |
  +---- loan_applications
  |
  +---- loan_recovery_rules

loan_applications
  |
  +---- loan_guarantors
  |
  +---- loan_investigations
  |
  +---- loan_decisions
  |
  +---- loans

loans
  |
  +---- loan_disbursements
  +---- loan_installments
  +---- loan_repayments
  +---- loan_recoveries
  +---- loan_documents
57. Key Foreign Keys

The database should enforce relationships such as:

member_applications.user_id
members.user_id

user_roles.user_id
user_roles.role_id

role_permissions.role_id
role_permissions.permission_id

financial_accounts.member_id
financial_transactions.account_id
financial_transactions.payment_id

payments.member_id
payments.payment_method_id
payments.receipt_id

receipts.payment_id

loan_applications.member_id
loan_applications.loan_product_id
loan_applications.committee_meeting_id

loan_guarantors.loan_application_id
loan_guarantors.guarantor_member_id

loan_investigations.loan_application_id
loan_investigations.assigned_to

loan_decisions.loan_application_id
loan_decisions.decided_by

loans.loan_application_id
loans.member_id

loan_disbursements.loan_id
loan_disbursements.payment_method_id

loan_installments.loan_id

loan_repayments.loan_id
loan_repayments.member_id
loan_repayments.payment_method_id

loan_recoveries.loan_id
loan_recoveries.source_member_id

notifications.user_id

email_logs.user_id

audit_logs.user_id
58. Unique Constraints

The database should enforce uniqueness where required.

Examples:

users.email
member_applications.application_number
members.member_number
financial_accounts.account_number
payments.reference_number
receipts.receipt_number
loan_applications.application_number
loans.loan_number

The exact uniqueness rules for optional references should be reviewed during migration design.

59. Check Constraints

The database should enforce basic financial constraints.

Examples:

amount > 0
interest_rate >= 0
repayment_months > 0
required_guarantors >= 0
minimum_amount >= 0
maximum_amount >= minimum_amount

Application-level validation remains necessary.

Database constraints provide an additional safety layer.

60. Indexing

Indexes should be created for frequently queried fields.

Examples:

users.email
members.member_number
members.status

payments.member_id
payments.payment_date
payments.status

financial_transactions.account_id
financial_transactions.transaction_date

loan_applications.member_id
loan_applications.status
loan_applications.submitted_at

loan_guarantors.guarantor_member_id
loan_guarantors.status

loans.member_id
loans.status
loans.loan_number

loan_installments.loan_id
loan_installments.due_date

loan_repayments.loan_id
loan_repayments.payment_date

notifications.user_id
notifications.read_at

audit_logs.user_id
audit_logs.entity_type
audit_logs.entity_id

Indexes should be introduced based on actual query patterns where appropriate.

61. Financial Balance Calculation

A financial account balance should be calculated from verified financial transactions.

Conceptually:

Credits
   -
Debits
   =
Balance

Only transactions that meet the system's verified/posted status should affect the official balance.

The exact posting state will be implemented in the backend service layer.

62. Contribution Balance

A member's contribution balance is calculated from the contribution account's verified transactions.

Contribution Credits
-
Contribution Debits
=
Contribution Balance
63. Savings Balance

A member's savings balance is calculated from the savings account's verified transactions.

Savings Credits
-
Savings Debits
=
Savings Balance
64. Shares Balance

A member's share position is calculated from the share account's verified transactions.

The exact share price and share calculation formula have not yet been supplied by the organization.

Therefore, the system must support configurable share rules.

65. Minimum Contributions and Savings

The current organizational rule is:

Minimum contribution: ₦100
Minimum savings: ₦100

These should be configurable organization policies rather than hard-coded values throughout the application.

66. Membership Loan Eligibility

The current rule requires at least:

6 months membership

before a member can apply for a loan.

The backend must calculate eligibility from the member's approved membership date and the applicable loan product policy.

67. Maximum Loan Amount

The maximum loan amount depends on factors including:

Member savings
Member shares
Organization policy

No exact formula has been supplied.

Therefore, the formula must not be invented.

The loan eligibility service should provide a configurable calculation mechanism.

68. Interest Rate

The interest rate is determined by the Admin/Committee according to organizational policy.

The system must support configurable interest rates.

The approved interest rate must be stored on the loan decision and loan record as a historical snapshot.

69. Repayment Duration

The current organizational repayment duration is:

11 months

This should be configurable at the loan-product level.

70. Guarantor Requirement

The current organizational requirement is:

2 guarantors

This should be configurable per loan product.

71. Default and Recovery

Default handling must be configurable.

The system must support recovery sources such as:

Borrower's savings
Borrower's shares
Guarantor's savings
Guarantor's shares

However, the exact recovery behavior must come from the approved loan policy.

It must not be assumed universally for every loan.

72. Administrative Fields

Some fields appearing on the organization's paper loan form are official-use fields.

Examples:

Monthly Deduction
Shares
Savings
Loan Outstanding
Amount Required
Essence Balance
Exercise Book Balance
Approved By
Amount Granted

These should not automatically become member-editable fields.

Where required, they should be calculated or entered by authorized staff according to the organization's operational rules.

73. Essence Balance

The organization has not supplied a defined calculation for Essence Balance.

Therefore:

Do not invent a formula.
Do not expose a misleading automatic calculation.
Treat it as an organization-defined administrative field until the official rule is supplied.
74. Exercise Book Balance

Exercise Book Balance is treated as an official-use concept.

It should not automatically become a member-editable financial balance.

If the organization requires it in the digital workflow, the exact meaning and source should be documented before implementation.

75. P/L Number

P/L Number has not been identified as a required core business concept.

It should therefore not be introduced as a central financial field without a confirmed organizational requirement.

76. Data Retention

Financial records, loan records, approvals, payments, repayments, recoveries, and audit logs should be retained according to the organization's retention requirements.

Deletion policies must never compromise financial or audit history.

77. Transactional Operations

Important financial operations should use database transactions.

Examples:

Verify Payment
    |
    +---- Update Payment
    |
    +---- Create Financial Transaction
    |
    +---- Generate Receipt
    |
    +---- Create Audit Log

These operations should succeed or fail as a consistent unit where appropriate.

78. Loan Approval Transaction

Loan approval should ensure that related records remain consistent.

Conceptually:

Admin Decision
      |
      +---- Create Loan Decision
      |
      +---- Store Approved Terms
      |
      +---- Update Loan Application
      |
      +---- Create Loan Record
      |
      +---- Create Audit Log

Actual loan creation timing must follow the final service-layer workflow.

79. Loan Disbursement Transaction

Disbursement should be transactional.

Conceptually:

Authorized Disbursement
       |
       +---- Create Disbursement Record
       |
       +---- Update Loan Status
       |
       +---- Record Financial Movement
       |
       +---- Generate/Attach Receipt
       |
       +---- Create Audit Log
       |
       +---- Send Notification

Notifications should not cause a successful financial transaction to be rolled back merely because an email provider fails.

80. Data Ownership

The backend owns authoritative business data.

The frontend must not be trusted to determine:

Account balances
Loan eligibility
Interest calculations
Maximum loan amount
Approval permissions
Guarantor eligibility
Financial verification
Recovery authorization

These decisions belong to the Laravel backend.

81. Database Security

Database credentials must never be committed to Git.

The following must remain environment-specific:

DB_HOST
DB_PORT
DB_DATABASE
DB_USERNAME
DB_PASSWORD

Production credentials must be stored through deployment environment variables or secret management.

82. Migration Strategy

All schema changes must be represented through Laravel migrations.

The database must not be manually modified in production without a corresponding migration strategy.

Each migration should be:

Reproducible
Reviewable
Version controlled
83. Seed Data

Development seeders may create:

Roles
Permissions
Default admin user
Sample loan products
Sample payment methods
Sample organization settings

Development seed data must never contain real member financial information.

84. Testing Requirements

Database behavior must be covered by automated tests.

Important test areas include:

Member approval
Financial transaction posting
Payment verification
Balance calculation
Loan eligibility
Guarantor eligibility
Loan deadline validation
Loan approval
Loan disbursement
Repayment calculation
Partial repayments
Loan completion
Recovery
Permission enforcement
Audit logging
85. ERD Conceptual Model
                         USERS
                           |
              +------------+-------------+
              |                          |
              v                          v
     MEMBER_APPLICATIONS             MEMBERS
                                         |
                    +--------------------+--------------------+
                    |                    |                    |
                    v                    v                    v
            FINANCIAL_ACCOUNTS        PAYMENTS       LOAN_APPLICATIONS
                    |                    |                    |
                    v                    v                    |
         FINANCIAL_TRANSACTIONS       RECEIPTS               |
                                                             |
                           +---------------------------------+
                           |
             +-------------+-------------+
             |             |             |
             v             v             v
       GUARANTORS   INVESTIGATIONS   DECISIONS
                                           |
                                           v
                                         LOANS
                                           |
                 +-------------------------+-------------------------+
                 |                         |                         |
                 v                         v                         v
          DISBURSEMENTS             INSTALLMENTS               REPAYMENTS
                                           |
                                           v
                                      COMPLETION

LOANS
  |
  +---- RECOVERIES
  |
  +---- DOCUMENTS

USERS
  |
  +---- ROLES
  |
  +---- NOTIFICATIONS
  |
  +---- AUDIT LOGS

ROLES
  |
  +---- PERMISSIONS

ORGANIZATION
  |
  +---- SETTINGS
  +---- EXECUTIVES
  +---- NOTICES
  +---- CONTACT MESSAGES
86. Final Database Design Principle

The database is designed around the following principles:

Financial history is preserved.
Balances are derived from transactions.
Financial records are not hard-deleted.
Authorization is enforced by the backend.
Loan approval is separate from disbursement.
Committee investigation is separate from Admin approval.
Loan terms are snapshotted historically.
Organization policies are configurable.
Uploaded files are stored separately from database records.
Sensitive operations are auditable.
PostgreSQL constraints provide database-level integrity.
Laravel migrations remain the source of schema changes.
The frontend never becomes the authority for financial decisions.
Unknown organizational rules are not invented.
The system must remain extensible as the cooperative defines additional policies.
