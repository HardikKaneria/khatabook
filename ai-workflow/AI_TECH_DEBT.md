# AI Tech Debt

Base path: `/Users/hardikkaneria/Local Sites/khatabook/app/public/wp-content`

This file lists only debt that is directly supported by the current codebase.

## 1. Product and UX Debt

### 1.1 Dashboard receivables still rely on the latest 100 open invoices per status

Evidence:

- `plugins/khatabook/app/src/pages/Home.jsx`

Observed:

- The home screen now shows real operational data.
- Open receivables are derived from:
  - the latest 100 `SENT` invoices
  - the latest 100 `PARTIAL` invoices
- The page explicitly warns when this loaded set may be truncated.

Impact:

- High-volume orgs can still undercount receivables and overdue totals on the dashboard until a dedicated summary endpoint or server-side aggregate exists.

### 1.2 Company settings storage still carries broader legacy category support than the active UI

Evidence:

- `plugins/khatabook/app/src/pages/CompanySettings.jsx`
- `plugins/khatabook/backend/Api/SettingsController.php`
- `plugins/khatabook/backend/Helpers/ReportHelper.php`

Observed:

- The UI now intentionally renders only the active `company`, `sales`, and `tax` categories.
- `SettingsController.php` and older stored `kbs_settings` rows can still carry broader legacy categories from earlier product iterations.

Impact:

- Future runs can still misread the generic settings backend as proof that hidden categories are live modules.

### 1.3 Unpaid expenses still have no later settlement flow

Evidence:

- `plugins/khatabook/backend/Api/VyRestExpenses.php`
- `plugins/khatabook/app/src/modules/expenses/*`

Observed:

- Expenses now support create/list/detail/update/archive.
- Payment journals are created only during the initial create request when `pay_from_account_id` is supplied.
- No later “record payment for an existing unpaid expense” flow was found.

Impact:

- Expense records can now be corrected safely, but the lifecycle is still incomplete for teams that need to settle unpaid expenses after initial creation.

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

### 2.2 Repeated async-loading helpers exist across modules

Evidence:

- `plugins/khatabook/app/src/modules/accounts/hooks.js`
- `plugins/khatabook/app/src/modules/invoices/hooks.js`
- `plugins/khatabook/app/src/modules/expenses/hooks.js`

Observed:

- Each module defines a near-identical `useAsync` pattern.

Impact:

- Loading/error/refresh behavior is duplicated and easier to drift.

### 2.3 Repeated query helper logic exists in multiple module API files

Evidence:

- `plugins/khatabook/app/src/modules/accounts/api.js`
- `plugins/khatabook/app/src/modules/invoices/api.js`
- `plugins/khatabook/app/src/modules/expenses/api.js`

Observed:

- `buildQuery()` behavior is duplicated across multiple modules.

Impact:

- Small inconsistencies become more likely over time.

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

### 2.6 Frontend production bundle is still large

Evidence:

- `plugins/khatabook/app/package.json`
- `plugins/khatabook/app/src/App.jsx`
- `plugins/khatabook/app/src/theme.css`
- latest `npm run build` output for `plugins/khatabook/app`

Observed:

- The current production build emits a large main JavaScript bundle (`main-*.js` above 1 MB before gzip).
- The active app shell still loads through one manually managed route tree without route-level code splitting.

Impact:

- Initial load cost is higher than it should be, especially as more operational screens are added.

## 3. Safety and Reliability Debt

### 3.1 Multi-step writes still lack explicit transaction boundaries

Evidence:

- `plugins/khatabook/backend/Api/VyRestInvoices.php`
- `plugins/khatabook/backend/Api/VyRestExpenses.php`
- `plugins/khatabook/backend/Admin/PendingUserController.php`
- `plugins/khatabook/backend/Api/OrgUsersController.php`

Observed:

- Multi-table flows perform several inserts/updates without explicit transaction handling.

Impact:

- Mid-flow failures can leave partial state behind.

### 3.2 Operational logging is split across DB tables and raw PHP logs

Evidence:

- `plugins/khatabook/backend/Core/SystemLogger.php`
- `plugins/khatabook/backend/Email/EmailManager.php`
- `plugins/khatabook/backend/Api/VyRestInvoices.php`
- `plugins/khatabook/backend/Api/VyRestExpenses.php`
- `plugins/khatabook/backend/Api/SettingsController.php`

Observed:

- Some events are written to log tables.
- Other meaningful failures still go directly to `error_log()`.

Impact:

- Operational debugging is fragmented.

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
