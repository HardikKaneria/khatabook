# AI Tech Debt

Base path: `/Users/hardikkaneria/Local Sites/khatabook/app/public/wp-content`

This file lists only debt that is directly supported by the current codebase.

## 1. Product and UX Debt

### 1.1 Dashboard home still fans out through multiple independent API calls

Evidence:

- `plugins/khatabook/app/src/pages/Home.jsx`
- `plugins/khatabook/app/src/modules/accounts/api.js`
- `plugins/khatabook/app/src/modules/expenses/api.js`
- `plugins/khatabook/app/src/modules/invoices/api.js`
- `plugins/khatabook/app/src/modules/payments/api.js`
- `plugins/khatabook/app/src/modules/reports/api.js`
- `plugins/khatabook/backend/Api/VyRestReports.php`

Observed:

- The home screen now shows real operational data.
- Receivables now come from the live server-side summary endpoint rather than the older capped client approximation.
- `Home.jsx` now uses `useAsyncResource`, but the page still loads invoices, payments, expenses, accounts, billing health, the owner daily brief, and multiple report cards through one large `loadHomeState()` fan-out.
- Home still does not have a dedicated aggregated dashboard endpoint.

Impact:

- Dashboard reliability is better than before, but any future dashboard expansion still requires touching many endpoint calls in one page-level loader.

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

### 1.3 Reports page still orchestrates many report requests with bespoke page-level state

Evidence:

- `plugins/khatabook/app/src/modules/reports/ProfitTaxPage.jsx`
- `plugins/khatabook/app/src/modules/reports/api.js`
- `plugins/khatabook/backend/Api/VyRestReports.php`
- `plugins/khatabook/backend/Helpers/ReportHelper.php`

Observed:

- Receivables and payables reporting are both now live in `ProfitTaxPage.jsx`.
- Billing health and the owner daily brief are now also live in `ProfitTaxPage.jsx`.
- The page still coordinates several separate report endpoints in one bespoke `Promise.all(...)` effect rather than a dedicated reports loader or per-panel shared resource strategy.
- The report UI still mixes older bespoke layout/state handling with newer shared primitives used across other modules.

Impact:

- Reporting is operationally stronger now, but future report growth can still drift into a large page component with tightly coupled loading logic.

### 1.4 Invoice refunds currently support full paid-invoice reversals only

Evidence:

- `plugins/khatabook/backend/Api/VyRestInvoices.php`
- `plugins/khatabook/app/src/modules/invoices/InvoiceRefundForm.jsx`
- `plugins/khatabook/backend/Db/TableManager.php`

Observed:

- The live refund flow now exists and is org-safe.
- `refund_invoice()` only allows invoices in `PAID` state, computes a single refundable amount from the full net paid balance, and voids the invoice after one refund post.
- `InvoiceRefundForm.jsx` exposes a read-only refund amount rather than partial or payment-specific refund controls.

Impact:

- The product can now safely reverse fully paid invoices, but partial refunds, staged refunds, or payment-specific reversals still require future product and accounting direction.

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
- `plugins/khatabook/app/src/modules/reports/ProfitTaxPage.jsx`
- `plugins/khatabook/app/src/modules/payments/PaymentsPage.jsx`

Observed:

- Active module hooks now share `useAsyncResource`, active module APIs now share `buildQuery`, and both `Home.jsx` and `CompanySettings.jsx` now use the shared async-loading primitive.
- `ProfitTaxPage.jsx` is still on a bespoke page-level `Promise.all(...)` loading block outside the shared resource pattern.

Impact:

- Frontend behavior is more consistent than before, but the reports surface can still drift from the normalized async/query pattern.

### 2.3 Shared state primitives now exist, but layout/state treatment still drifts outside the most active screens

Evidence:

- `plugins/khatabook/app/src/components/ui/FeedbackState.jsx`
- `plugins/khatabook/app/src/components/ui/InlineNotice.jsx`
- `plugins/khatabook/app/src/modules/reports/ProfitTaxPage.jsx`

Observed:

- Active accounts/invoices/expenses/contacts/payments/users screens now share loading, empty, error, and inline notice primitives.
- Home and CompanySettings are closer to the shared async/state pattern now, but reports still retains more bespoke layout/state treatment than the main business modules.

Impact:

- UX consistency improved materially, but the app still has a split between the normalized business-module screens and the larger reports surface.

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

### 2.7 OCR bill extraction is blocked by missing attachment and parser infrastructure

Evidence:

- `plugins/khatabook/backend/Media/ManagedImageUpload.php`
- `plugins/khatabook/backend/Api/VyRestExpenses.php`
- `plugins/khatabook/composer.json`
- `plugins/khatabook/app/package.json`

Observed:

- Managed uploads currently support image-only org/logo flows, not expense-document ingestion.
- The live expense API has no attachment model for bill images or PDFs.
- No local OCR dependency or parser service exists in the PHP or frontend package manifests.

Impact:

- OCR bill extraction should stay blocked until there is an explicit dependency and product-direction decision rather than being faked through placeholder UI.

### 2.8 Invoice templates now favor direct per-template files over shared HTML composition

Evidence:

- `plugins/khatabook/backend/Helpers/InvoiceTemplateRenderHelper.php`
- `plugins/khatabook/backend/templates/invoices/*.php`

Observed:

- The invoice template architecture now uses direct PHP/HTML template files with per-template CSS, while shared helpers only prepare normalized document data and include the selected file.
- This makes manual template editing much easier than the older shared HTML renderer.
- The tradeoff is intentional duplication: structural invoice-document changes now require touching four template files instead of one shared HTML function.

Impact:

- Manual customization is easier and safer for template-specific work, but future cross-template markup changes need disciplined multi-file updates.

### 2.9 Auth refresh still depends on local storage plus custom same-tab events

Evidence:

- `plugins/khatabook/app/src/App.jsx`
- `plugins/khatabook/app/src/utils/authStorage.js`
- `plugins/khatabook/app/src/utils/authEvents.js`
- `plugins/khatabook/app/src/pages/UsersAdmin.jsx`
- `plugins/khatabook/app/src/pages/CompanySettings.jsx`
- `plugins/khatabook/app/src/modules/settings/invoices/InvoiceSettingsPage.jsx`

Observed:

- The active app shell owns the canonical in-memory auth state.
- Cross-tab updates still rely on the browser `storage` event, while same-tab updates now rely on a shared `kbs-auth-updated` event broadcast from the active org-switch paths.
- The main org-bound screens now subscribe to that event so same-tab workspace changes refresh `UsersAdmin.jsx`, `CompanySettings.jsx`, and `InvoiceSettingsPage.jsx` without a full reload.
- This is production-safe and keeps the manual router intact, but auth synchronization is still distributed across local storage, custom events, and page-level writes.

Impact:

- Future runs can still break auth-state consistency if they add new auth-mutating flows without dispatching the same-tab refresh event or routing those changes through the existing app shell.

## 3. Safety and Reliability Debt

### 3.1 Invite and org-member emails are still best-effort side effects after committed writes

Evidence:

- `plugins/khatabook/backend/Api/OrgUsersController.php`

Observed:

- Org-user invite/member-management writes now have transaction boundaries or compensating cleanup for the highest-risk membership and invite paths.
- Invite reminders, invite sends, and access-granted emails still happen after committed DB writes and are not delivery-tracked beyond mail success/failure at send time.

Impact:

- Data writes are safer than before, but operators can still see successful org-state changes even when the follow-up notification email is not delivered.

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
