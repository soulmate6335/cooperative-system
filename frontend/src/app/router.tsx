import { createBrowserRouter, type RouteObject } from 'react-router-dom'

import { AppLayout } from '../components/layout/AppLayout'
import { PublicLayout } from '../components/layout/PublicLayout'

import { RequireAuth, RequireRoles } from './routeGuards'

import { HomePage } from '../pages/public/HomePage'
import { AboutPage } from '../pages/public/AboutPage'
import { ContactPage } from '../pages/public/ContactPage'
import { LoginPage } from '../pages/auth/LoginPage'
import { RegisterPage } from '../pages/auth/RegisterPage'
import { UnauthorizedPage } from '../pages/auth/UnauthorizedPage'
import { NotFoundPage } from '../pages/auth/NotFoundPage'

import { MemberDashboardPage } from '../pages/member/MemberDashboardPage'
import { MemberProfilePage } from '../pages/member/MemberProfilePage'
import { LoanEligibilityPage } from '../pages/member/LoanEligibilityPage'
import { MemberLoanProductsPage } from '../pages/member/MemberLoanProductsPage'
import { LoanApplicationsPage } from '../pages/member/LoanApplicationsPage'
import { NewLoanApplicationPage } from '../pages/member/NewLoanApplicationPage'
import { LoanApplicationDetailPage } from '../pages/member/LoanApplicationDetailPage'
import { GuarantorRequestsPage } from '../pages/member/GuarantorRequestsPage'
import { MemberAccountsPage } from '../pages/member/MemberAccountsPage'

import { CommitteeDashboardPage } from '../pages/committee/CommitteeDashboardPage'
import { CommitteeApplicationDetailPage } from '../pages/committee/CommitteeApplicationDetailPage'

import { AdminDashboardPage } from '../pages/admin/AdminDashboardPage'
import { AdminLoanEligibilityPage } from '../pages/admin/AdminLoanEligibilityPage'
import { MembershipApplicationsPage } from '../pages/admin/MembershipApplicationsPage'
import { MembershipApplicationDetailPage } from '../pages/admin/MembershipApplicationDetailPage'
import { AdminLoanApplicationsPage } from '../pages/admin/AdminLoanApplicationsPage'
import { AdminLoanApplicationDetailPage } from '../pages/admin/AdminLoanApplicationDetailPage'
import { LoanProductsAdminPage } from '../pages/admin/LoanProductsAdminPage'
import { CommitteeMeetingsPage } from '../pages/admin/CommitteeMeetingsPage'
import { PaymentsPage } from '../pages/admin/PaymentsPage'
import { ReceiptsPage } from '../pages/admin/ReceiptsPage'
import { PaymentMethodsPage } from '../pages/admin/PaymentMethodsPage'
import { FinancialAccountsPage } from '../pages/admin/FinancialAccountsPage'
import { MembersDirectoryPage } from '../pages/admin/MembersDirectoryPage'

import { FinanceDashboardPage } from '../pages/finance/FinanceDashboardPage'

const ADMIN_ROLES = ['admin', 'super_admin']

const routes: RouteObject[] = [
  {
    element: <PublicLayout />,
    children: [
      { path: '/', element: <HomePage /> },
      { path: '/about', element: <AboutPage /> },
      { path: '/contact', element: <ContactPage /> },
    ],
  },
  { path: '/login', element: <LoginPage /> },
  { path: '/register', element: <RegisterPage /> },
  { path: '/unauthorized', element: <UnauthorizedPage /> },
  {
    element: (
      <RequireAuth>
        <AppLayout />
      </RequireAuth>
    ),
    children: [
      // ------------------------------------------------ member portal
      { path: '/member', element: <RequireRoles roles={['member']}><MemberDashboardPage /></RequireRoles> },
      { path: '/member/profile', element: <RequireRoles roles={['member']}><MemberProfilePage /></RequireRoles> },
      { path: '/member/eligibility', element: <RequireRoles roles={['member']}><LoanEligibilityPage /></RequireRoles> },
      { path: '/member/loans/products', element: <RequireRoles roles={['member']}><MemberLoanProductsPage /></RequireRoles> },
      { path: '/member/loans/applications', element: <RequireRoles roles={['member']}><LoanApplicationsPage /></RequireRoles> },
      { path: '/member/loans/applications/new', element: <RequireRoles roles={['member']}><NewLoanApplicationPage /></RequireRoles> },
      { path: '/member/loans/applications/:id', element: <RequireRoles roles={['member']}><LoanApplicationDetailPage /></RequireRoles> },
      { path: '/member/guarantor-requests', element: <RequireRoles roles={['member']}><GuarantorRequestsPage /></RequireRoles> },
      { path: '/member/accounts', element: <RequireRoles roles={['member']}><MemberAccountsPage /></RequireRoles> },

      // ------------------------------------------------- committee portal
      { path: '/committee', element: <RequireRoles roles={['committee_officer']}><CommitteeDashboardPage /></RequireRoles> },
      { path: '/committee/loan-applications/:id', element: <RequireRoles roles={['committee_officer']}><CommitteeApplicationDetailPage /></RequireRoles> },

      // ------------------------------------------------- admin portal
      { path: '/admin', element: <RequireRoles roles={ADMIN_ROLES}><AdminDashboardPage /></RequireRoles> },
      { path: '/admin/membership-applications', element: <RequireRoles roles={ADMIN_ROLES}><MembershipApplicationsPage /></RequireRoles> },
      { path: '/admin/membership-applications/:id', element: <RequireRoles roles={ADMIN_ROLES}><MembershipApplicationDetailPage /></RequireRoles> },
      { path: '/admin/loans/applications', element: <RequireRoles roles={ADMIN_ROLES}><AdminLoanApplicationsPage /></RequireRoles> },
      { path: '/admin/loans/applications/:id', element: <RequireRoles roles={ADMIN_ROLES}><AdminLoanApplicationDetailPage /></RequireRoles> },
      { path: '/admin/loan-eligibility', element: <RequireRoles roles={ADMIN_ROLES}><AdminLoanEligibilityPage /></RequireRoles> },
      { path: '/admin/loan-products', element: <RequireRoles roles={ADMIN_ROLES}><LoanProductsAdminPage /></RequireRoles> },
      { path: '/admin/committee-meetings', element: <RequireRoles roles={ADMIN_ROLES}><CommitteeMeetingsPage /></RequireRoles> },
      { path: '/admin/members', element: <RequireRoles roles={ADMIN_ROLES}><MembersDirectoryPage /></RequireRoles> },

      // ---------------- admin + finance shared financial pages
      { path: '/admin/payments', element: <RequireRoles roles={[...ADMIN_ROLES, 'finance_officer']}><PaymentsPage /></RequireRoles> },
      { path: '/admin/receipts', element: <RequireRoles roles={[...ADMIN_ROLES, 'finance_officer']}><ReceiptsPage /></RequireRoles> },
      { path: '/admin/payment-methods', element: <RequireRoles roles={ADMIN_ROLES}><PaymentMethodsPage /></RequireRoles> },
      { path: '/admin/financial/accounts', element: <RequireRoles roles={[...ADMIN_ROLES, 'finance_officer']}><FinancialAccountsPage /></RequireRoles> },

      // ------------------------------------------------- finance portal
      { path: '/finance', element: <RequireRoles roles={['finance_officer']}><FinanceDashboardPage /></RequireRoles> },
    ],
  },
  { path: '*', element: <NotFoundPage /> },
]

export const router = createBrowserRouter(routes)

/** Landing path based on the authenticated user's roles. */
export function roleHomePath(roles: string[]): string {
  if (roles.includes('super_admin') || roles.includes('admin')) {
    return '/admin'
  }
  if (roles.includes('committee_officer')) {
    return '/committee'
  }
  if (roles.includes('finance_officer')) {
    return '/finance'
  }
  return '/member'
}