# Cooperative System

A digital management system for cooperative organizations to manage members, contributions, savings, shares, loans, guarantors, repayments, notifications, financial records, reports, and administrative operations.

## Status

Implemented and tested. The system covers the full membership lifecycle, loan origination through disbursement and repayment, financial ledger integration, in-app notifications, notices, executive management, and homepage content management.

## Implemented Features

- **Membership registration and admin approval** — public registration with admin review, approval/rejection workflow, and member record creation with role assignment.
- **Admin-controlled loan eligibility** — administrators record eligibility decisions; eligibility is revalidated when the member submits an application.
- **Loan applications** — members submit applications against active loan products; applications enforce product amount rules and the one-open-application rule.
- **Guarantors** — members nominate guarantors; guarantors accept or decline; guarantee exposure is tracked and enforced.
- **Committee investigation / recommendation** — committee officers investigate applications and submit recommendations; the committee can never approve or reject final decisions.
- **Admin final loan decision** — administrators approve (with snapshot terms) or reject applications.
- **Loan disbursement** — approved loans are disbursed against a member financial account; the repayment schedule is generated atomically with the posted ledger transaction.
- **Repayment schedules** — installment schedules generated deterministically with integer minor-unit rounding that sums back exactly to the approved totals.
- **Repayments and financial ledger integration** — every repayment is a Financial Core payment; verification posts the ledger transaction and allocates it to installments.
- **Notifications** — in-app notifications for membership, loans, payments, guarantors, and notice publication; email delivery is optional and configuration-gated.
- **Notices** — organization notices with a draft/published/archived lifecycle and public/member visibility targeting.
- **Executive management** — public executive profiles with photo upload, a visibility toggle, and display ordering.
- **Homepage content management** — hero copy, introduction, and notices/executives aggregation for the public homepage.

### Interest methods

Flat-interest loans are fully supported.

**Reducing-balance loan disbursement is currently blocked** because the organization has not defined the repayment calculation formula. Rather than invent a convention, the system refuses to generate a schedule for reducing-balance loans with an explicit error at disbursement time.

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

## Setup / Deployment

### Prerequisites

- PHP 8.3+ with the extensions required by Laravel
- Composer
- Node.js 20+
- PostgreSQL 14+

### Database

Create the application and test databases in PostgreSQL:

```sql
CREATE DATABASE cooperative_system;
CREATE DATABASE cooperative_system_test;
```

The application reads its connection from the `DB_*` variables in `backend/.env` (see below). The test suite requires the separate `DB_TEST_*` variables.

### Backend

```sh
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Edit `backend/.env` and set at minimum:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=cooperative_system
DB_USERNAME=<database user>
DB_PASSWORD=<database password>
```

Then run migrations and seed:

```sh
php artisan migrate --seed
```

The seeder (`RolePermissionSeeder`) creates the roles and the permission matrix.

Create the public storage symlink so uploaded executive photos and homepage hero images are publicly reachable:

```sh
php artisan storage:link
```

Start the API:

```sh
php artisan serve
```

### Frontend

```sh
cd frontend
npm install
cp .env.example .env
npm run dev
```

`VITE_API_URL` in `frontend/.env` defines the Laravel API base URL. It defaults to `http://localhost:8000/api/v1`; production deployments must replace it with the deployed API URL.

### Mail / notifications

The in-app database notification is always delivered and is the source of truth. Email delivery is enabled only when `MAIL_ENABLED=true` is set in `backend/.env` together with working SMTP settings (`MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`). Otherwise notifications are stored in-app only; a missing mail configuration never breaks the in-app notification.

### Running the backend tests

The test suite runs against the separate testing database and requires the `DB_TEST_*` variables (the test bootstrap fails fast when any is missing). Example:

```sh
cd backend
export DB_TEST_HOST=127.0.0.1
export DB_TEST_PORT=5432
export DB_TEST_DATABASE=cooperative_system_test
export DB_TEST_USERNAME=<test database user>
export DB_TEST_PASSWORD=<test database password>
php artisan test
```

To rebuild the testing database before a run:

```sh
php artisan migrate:fresh --env=testing --seed
```

### Running the frontend validation

```sh
cd frontend
npm run lint
npm run build
```

`npm run build` first runs `tsc -b` (the project type check) and then Vite. There is no separate `typecheck` script; `tsc -b` is the type-checking step and can be run directly with `npx tsc -b`.

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