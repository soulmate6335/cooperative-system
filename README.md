# Cooperative System

A digital management system for cooperative organizations to manage members, contributions, savings, shares, loans, guarantors, repayments, notifications, financial records, reports, and administrative operations.

## Status

Architecture and requirements phase.

## Stack

### Frontend
- React
- TypeScript
- Vite
- React Router
- Material UI
- Axios
- TanStack Query
- Redux Toolkit

### Backend
- Laravel
- PHP
- Laravel Sanctum
- REST API
- Laravel Mail

### Database
- PostgreSQL

## Core Modules

- Public Website
- Authentication
- Membership Management
- Member Approval
- Contributions
- Savings
- Shares
- Payments
- Receipts
- Loan Products
- Loan Applications
- Guarantors
- Committee Investigation
- Loan Approval
- Loan Disbursement
- Loan Repayment
- Loan Default and Recovery
- Notifications
- Email
- Executive Management
- Organization Content
- Reports
- Audit Logs
- Role-Based Access Control

## Roles

- Member
- Finance Officer
- Committee / Loan Officer
- Admin
- Super Admin

## Architecture

React + TypeScript
        |
        | HTTPS / REST API
        v
Laravel API
        |
        v
PostgreSQL

The React application provides the user interface.

Laravel is responsible for authentication, authorization, validation, business rules, financial operations, workflows, notifications, reporting, and auditability.

PostgreSQL provides persistent storage.

## Financial Integrity

Financial balances are derived from transaction records.

Financial transactions must not be hard-deleted.

Corrections must preserve the original transaction history through reversal or correction transactions.

## Loan Workflow

Member
  |
  v
Loan Application
  |
  v
Guarantor Confirmation
  |
  v
Committee Investigation
  |
  v
Committee Feedback
  |
  v
Admin Decision
  |
  +---- Rejected
  |
  +---- Approved
          |
          v
      Disbursement
          |
          v
    Repayment Schedule
          |
          v
       Repayments
          |
          v
       Completion

## Documentation

Requirements:
`docs/requirements/`

Architecture:
`docs/architecture/`

Database:
`docs/database/`

API:
`docs/api/`

## Development Principle

Organization-specific financial rules must not be invented.

Rules such as interest calculation, maximum loan formula, contribution frequency, share calculation, repayment collection, and recovery behavior must be configurable where the organization has not supplied a fixed rule.

## Security

The system must protect authentication credentials, personal information, financial information, uploaded documents, receipts, administrative operations, and audit records.

Sensitive operations must be authorized and auditable.
