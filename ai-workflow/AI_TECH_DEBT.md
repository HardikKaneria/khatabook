# AI Tech Debt

Base path: `/Users/hardikkaneria/Local Sites/khatabook/app/public/wp-content`

This file lists only debt that is directly supported by the current codebase.

## 1. Product and UX Debt

### 1.1 Dashboard receivables still rely on the latest 100 open invoices per status

Evidence:

- `plugins/khatabook/app/src/pages/Home.jsx`
- `plugins/khatabook/backend/Api/VyRestReports.php`

Observed:

- The home screen now shows real operational data.
- A dedicated server-side receivables summary now exists for the reports module.
- Invoice-side receivables behavior is now richer because recurring invoices, credit/debit adjustments, and promise tracking are active elsewhere in the product.
- Open receivables are derived from:
  - the latest 100 `SENT` invoices
  - the latest 100 `PARTIAL` invoices
- The page explicitly warns when this loaded set may be truncated.

Impact:

- High-volume orgs can still undercount receivables and overdue totals on the dashboard until Home switches to the newer server-side summary path.

### 1.2 Company settings storage still carries broader legacy category support than the active UI

Evidence:

- `plugins/khatabook/app/src/pages/CompanySettings.jsx`
- `plugins/khatabook/backend/Api/SettingsController.php`
- `plugins/khatabook/backend/Helpers/ReportHelper.php`

Observed:

- The UI now intentionally renders only the active `company`, `sales`, and `tax` categories.
- Company logo upload is now live because `company.logo_url` has a confirmed live consumer as the fallback invoice logo.
- `SettingsController.php` and older stored `kbs_settings` rows can still carry broader legacy categories from earlier product iterations.

Impact:

- Future runs can still misread the generic settings backend as proof that hidden categories are live modules.

### 1.3 Payables still stop short of a full vendor-bill and later-settlement workflow

Evidence:

- `plugins/khatabook/backend/Api/VyRestExpenses.php`
- `plugins/khatabook/app/src/modules/expenses/*`

Observed:

- Expenses now support create/list/detail/update/archive.
- Payment journals are created only during the initial create request when `pay_from_account_id` is supplied.
- The repository still has no first-class vendor-bill workflow on top of the expense/vendor foundations.
- No later “record payment for an existing unpaid expense” flow was found.

Impact:

- Expense records can now be corrected safely, but the payables lifecycle is still incomplete for teams that need vendor bills, due tracking, and later settlement after initial creation.

## 2. Architecture and Maintainability Debt

### 2.1 Frontend routing is custom and fully hand-managed

Evidence:

- `plugins/khatabook/app/src/App.jsx`
- `plugins/khatabook/app/package.json`

Observed:

- Route parsing, guards, redirects, and org-switch query handling are implemented manually.
- `react-router-dom` is installed but not used in the active app shell.

Impact:

- New routes and route-state behavior are easier to get wrong.

### 2.2 Shared async/query helpers now exist, but not all live screens use them yet

Evidence:

- `plugins/khatabook/app/src/hooks/useAsyncResource.js`
- `plugins/khatabook/app/src/utils/buildQuery.js`
- `plugins/khatabook/app/src/pages/Home.jsx`
- `plugins/khatabook/app/src/pages/CompanySettings.jsx`
- `plugins/khatabook/app/src/modules/payments/PaymentsPage.jsx`

Observed:

- Active module hooks now share `useAsyncResource`, and active module APIs now share `buildQuery`.
- Some important screens still fetch data ad hoc outside those shared foundations.

Impact:

- Frontend behavior is better than before, but fetch/state handling can still drift between module-driven pages and bespoke dashboard/settings screens.

### 2.3 Shared state primitives now exist, but layout/state treatment still drifts outside the most active screens

Evidence:

- `plugins/khatabook/app/src/components/ui/FeedbackState.jsx`
- `plugins/khatabook/app/src/components/ui/InlineNotice.jsx`
- `plugins/khatabook/app/src/pages/Home.jsx`
- `plugins/khatabook/app/src/pages/CompanySettings.jsx`
- `plugins/khatabook/app/src/modules/reports/ProfitTaxPage.jsx`

Observed:

- Active accounts/invoices/expenses/contacts/payments/users screens now share loading, empty, error, and inline notice primitives.
- Dashboard, settings, and reports still retain more bespoke layout/state patterns.

Impact:

- UX consistency improved materially, but the app still has a split between the normalized business-module screens and older bespoke surfaces.

### 2.4 Shared request/response contracts are implicit only

Evidence:

- `plugins/khatabook/backend/Api/*`
- `plugins/khatabook/app/src/modules/*/api.js`
- `plugins/khatabook/app/src/App.jsx`

Observed:

- No shared typed contract layer or generated client was found.

Impact:

- API drift between frontend expectations and backend responses is easier to introduce.

### 2.5 Settings are split across two storage systems

Evidence:

- `plugins/khatabook/backend/Api/SettingsController.php`
- `plugins/khatabook/backend/Api/VyRestInvoiceSettings.php`

Observed:

- Generic org settings live in `kbs_settings`.
- Invoice document/email/logo settings live in `vy_invoice_template_settings`.

Impact:

- Developers need stronger source-of-truth discipline when implementing settings-related features.

### 2.6 Settings route registration is still split between the endpoint registry and the controller class

Evidence:

- `plugins/khatabook/backend/Endpoint/EndpointManager.php`
- `plugins/khatabook/backend/Api/SettingsController.php`

Observed:

- The active app uses `EndpointManager.php` as the real `kbs/v1/settings` registration path.
- `SettingsController.php` still contains its own `routes()` registration for the same settings endpoints.
- New company-logo upload support had to follow the active `EndpointManager` path rather than the controller-local registry.

Impact:

- Future runs can patch the wrong registration point and create drift between the controller-local route map and the live endpoint registry.

## 3. Safety and Reliability Debt

### 3.1 Some non-financial multi-step org/admin flows still lack the same rollback discipline as the core financial controllers

Evidence:

- `plugins/khatabook/backend/Api/OrgUsersController.php`

Observed:

- Core financial flows now have explicit transaction boundaries or compensating cleanup.
- Org-user invite/member-management flows still perform multi-step membership, role, invite, and email work without the same rollback discipline.

Impact:

- Mid-flow failures can still leave invite/member state and notification side effects less predictable than invoice, expense, and payment flows.

### 3.2 Low-level boot and fallback logging still rely on raw PHP logs intentionally

Evidence:

- `plugins/khatabook/backend/Core/SystemLogger.php`
- `plugins/khatabook/main.php`

Observed:

- High-value operational events now route through `kbs_system_logs`.
- Raw `error_log()` remains in the frontend boot error path and as the fallback when `SystemLogger` itself cannot persist to the database.

Impact:

- Operators still need to check PHP error logs for bootstrap failures or logger-write failures that occur before DB-backed logging can help.

### 3.3 Automated coverage still stops short of full integration and browser flows

Evidence:

- `plugins/khatabook/tests/run.php`
- `plugins/khatabook/tests/*Test.php`

Observed:

- The current PHP harness now covers helper rules and controller-level auth, org-access, invoice create/update, and expense create/update/archive behavior.
- The repository still has no real browser suite or true WordPress REST integration coverage.

Impact:

- UI wiring, route integration, and real WordPress runtime regressions can still slip through automated checks.

### 3.4 Browser auth storage still uses a static client-side pepper and plain fallback

Evidence:

- `plugins/khatabook/app/src/utils/authStorage.js`

Observed:

- `PEPPER = "kbs-static-pepper-v1"` is hardcoded.
- If crypto handling fails, auth falls back to `_plain` localStorage payloads.

Impact:

- This improves convenience more than true secrecy and deserves caution in future auth/security work.

## 4. Legacy and Consistency Debt

### 4.1 Legacy quotation schema remains without an active module direction

Evidence:

- `plugins/khatabook/backend/Db/TableManager.php`
- no active quotation controller or SPA route found during the current scan

Observed:

- `kbs_quotations` and `kbs_quotation_items` exist in schema.
- No active quotation workflow was found in the current product path.

Impact:

- This is easy for future work to misinterpret as an active module.

### 4.2 Global WP role handling still coexists with org-role business authorization

Evidence:

- `plugins/khatabook/backend/Helpers/OrgHelper.php`
- `plugins/khatabook/backend/Api/OrgUsersController.php`
- `plugins/khatabook/backend/Roles/CustomRoles.php`
- `plugins/khatabook/backend/Core/AdminAccess.php`

Observed:

- Current business authorization is org-role driven.
- WordPress roles still influence dashboard/admin access behavior.

Impact:

- The system is correct enough to run, but the mixed model remains easy to misunderstand.

### 4.3 Admin redirect behavior still references a non-SPA path

Evidence:

- `plugins/khatabook/backend/Core/AdminAccess.php`

Observed:

- `restrict_dashboard()` redirects low-privilege users to `home_url('/my-account')`.
- The active app routes are SPA routes such as `/home`, `/accounts`, `/invoices`, and so on.

Impact:

- This is a consistency risk if that redirect path is ever exercised in production.
