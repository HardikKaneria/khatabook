# AI File Map

Base path for all paths below: `/Users/hardikkaneria/Local Sites/khatabook/app/public/wp-content`

## 1. Real Project Shape

The live application is a single WordPress plugin at:

- `plugins/khatabook/main.php`

It contains:

- WordPress bootstrap and SPA boot logic
- PHP REST APIs under `backend/Api`
- org/auth/onboarding logic under `backend/Auth`, `backend/Helpers`, and `backend/Api/OrgUsersController.php`
- custom-table creation under `backend/Db/TableManager.php`
- invoice template rendering + PDF generation under `backend/Helpers`, `backend/templates/invoices`, and `backend/Invoices/VyInvoicePdf.php`
- React/Vite frontend under `plugins/khatabook/app/src`
- plugin admin screens under `backend/Admin`
- lightweight PHP tests under `plugins/khatabook/tests`
- route-level frontend chunks now resolve through lazy-loaded page boundaries in `app/src/App.jsx`

## 2. Backend Architecture Map

### 2.1 Bootstrap and plugin lifecycle

- `plugins/khatabook/main.php`
  - Loads Composer autoloading for `KBS\\` classes from `backend/`
  - Manually requires helper files that expose global helper functions
  - Boots `KBS\Core\Plugin`
  - Boots `KBS\Email\EmailManager`
  - Serves the React SPA for non-admin, non-REST, non-AJAX requests
  - Resolves Vite assets from `plugins/khatabook/app/dist/manifest.json` or `plugins/khatabook/app/dist/.vite/manifest.json`

- `plugins/khatabook/backend/Core/Plugin.php`
  - Activation: `KBS\Roles\CustomRoles::add_roles`, `KBS\Db\TableManager::create_all_tables`
  - Deactivation: `KBS\Roles\CustomRoles::remove_roles`
  - Runtime hooks:
    - `init` -> `KBS\Admin\AdminMenu::init`
    - `admin_init` -> `KBS\Core\AdminAccess::restrict_dashboard`
    - `rest_api_init` -> `KBS\Endpoint\EndpointManager::register_endpoints`
    - `init` -> recurring invoice runner bootstrap
    - `kbs_process_recurring_invoices` -> recurring invoice generation job
    - `admin_init` -> `KBS\Admin\PendingUserController::handle_actions`
    - `after_setup_theme` -> `KBS\Core\AdminAccess::hide_admin_bar`
    - `login_redirect` -> `KBS\Core\AdminAccess::redirect_after_login`
  - Calls `KBS\Db\TableManager::maybe_upgrade()` on every boot

### 2.2 REST route registry

- `plugins/khatabook/backend/Endpoint/EndpointManager.php`
  - `kbs-admin/v1`
    - pending-user admin routes
  - `kbs/v1`
    - OTP send/verify
    - registration submit
    - invite lookup + accept
    - logout
    - `/me`
    - `/active-org`
    - generic org settings (`SettingsController`)
    - admin log endpoints
    - org user/invite management
  - `vy/v1`
    - accounts
    - invoices
    - recurring invoice profiles
    - invoice notes
    - invoice promises
    - payments
    - expenses
    - contacts
    - reports
    - invoice template settings
    - invoice preview

### 2.3 Auth, onboarding, and org membership

- `plugins/khatabook/backend/Auth/OtpAuth.php`
  - Public OTP send and verify
  - Separate OTP contexts for `login` and `register`
  - Single-session token issuance
  - Existing-session hydration for `/kbs/v1/me`
  - Logout revocation

- `plugins/khatabook/backend/Auth/RegisterController.php`
  - Registration submission after OTP verification
  - Writes pending registrations to `kbs_pending_users`
  - Does not create WP users before admin approval

- `plugins/khatabook/backend/Admin/PendingUserController.php`
  - Approves/declines pending registrations
  - Creates WP user + org membership on approval
  - Sends approval/decline emails
  - Supports both wp-admin and REST-driven approval paths

- `plugins/khatabook/backend/Api/OrgUsersController.php`
  - Org members + invites management
  - Invite generation, resend, accept, role change, removal
  - Uses `kbs_user_org_roles` as the per-org role source

- `plugins/khatabook/backend/Helpers/OrgHelper.php`
  - Canonical org resolution
  - Active org persistence in user meta
  - Membership checks against `kbs_user_org_roles`

- `plugins/khatabook/backend/Auth/AuthSessionHelper.php`
  - Single-session token validity helpers

### 2.4 Billing and accounting modules

#### Accounts and journals

- `plugins/khatabook/backend/Api/VyRestAccounts.php`
  - `vy/v1/accounts`
  - `vy/v1/accounts/{id}`
  - `vy/v1/accounts/{id}/statement`
  - `vy/v1/transactions/receipt`
  - `vy/v1/transactions/payment`
  - `vy/v1/transactions/transfer`

- `plugins/khatabook/backend/Accounting/VyJournalEngine.php`
  - Balanced journal entry creation
  - Account balance computation
  - Account statement generation

#### Contacts

- `plugins/khatabook/backend/Api/VyRestContacts.php`
  - list/create/get/update/archive for `vy_contacts`
  - contact-scoped customer statements from live invoice/payment data
- `plugins/khatabook/app/src/modules/contacts/*`
  - dedicated contacts list, form, edit, archive, statement modal, and pagination UI

#### Invoices

- `plugins/khatabook/backend/Api/VyRestInvoices.php`
  - list/create/get/update/pay/email/generate PDF
  - recurring profile create/list/get/update/manual-generate
  - invoice-linked credit/debit notes
  - invoice-linked promise-to-pay tracking
  - payment activity list via `GET /vy/v1/payments`
  - next invoice number
  - description suggestions
  - create/update work on `vy_invoices` and `vy_invoice_items`
  - payments write to `vy_invoice_payments`

- `plugins/khatabook/backend/Helpers/InvoiceFinancialHelper.php`
  - applies invoice credit/debit note totals
  - computes adjusted invoice balances used by invoice detail, payments, reports, and statements

- `plugins/khatabook/backend/Helpers/InvoiceEditHelper.php`
  - Central invoice edit eligibility rules

- `plugins/khatabook/backend/Helpers/InvoiceEmailHelper.php`
  - Customer-facing invoice email flow

- `plugins/khatabook/backend/Invoices/VyInvoicePdf.php`
  - PDF generation via mPDF

#### Expenses

- `plugins/khatabook/backend/Api/VyRestExpenses.php`
  - list/create/get/update/archive for `vy_expenses`
  - can create journal entries for paid expenses
- `plugins/khatabook/backend/Helpers/ExpenseEditHelper.php`
  - central journal-safe edit/archive rules
- `plugins/khatabook/app/src/modules/expenses/*`
  - active create/list/detail/edit/archive UI

#### Payments visibility

- `plugins/khatabook/backend/Api/VyRestInvoices.php`
  - `GET /vy/v1/payments`
  - maps `vy_invoice_payments` to linked invoices and receiving accounts

- `plugins/khatabook/app/src/modules/payments/*`
  - dedicated payment activity route with date filters and navigation back to invoices/accounts

- `plugins/khatabook/app/src/pages/Home.jsx`
  - recent payments panel on the operational dashboard

#### Reports

- `plugins/khatabook/backend/Api/VyRestReports.php`
  - profit summary
  - GST summary
  - tax estimate
  - receivables summary
  - monthly trends

- `plugins/khatabook/backend/Helpers/ReportHelper.php`
  - Reads effective org/company/tax settings
  - Computes report aggregates from `vy_*` invoices, payments, expenses, accounts, and journals

### 2.5 Invoice template and rendering system

- `plugins/khatabook/backend/Helpers/InvoiceTemplateHelper.php`
  - Template registry
  - Template defaults
  - Template resolution precedence

- `plugins/khatabook/backend/Helpers/InvoiceRenderHelper.php`
  - Shared rendering helpers
  - tax-breakup logic
  - QR payload/data URI generation
  - synthetic preview invoice generator

- `plugins/khatabook/backend/Helpers/InvoiceTemplateRenderHelper.php`
  - Shared HTML rendering model used across templates

- `plugins/khatabook/backend/templates/invoices/*.php`
  - 10 invoice template wrapper files

- `plugins/khatabook/backend/Api/VyRestInvoicePreview.php`
  - Browser preview HTML response
  - Uses live settings with temporary preview overrides

- `plugins/khatabook/backend/Api/VyRestInvoiceSettings.php`
  - Org-level invoice template settings CRUD
  - logo upload/delete routes

### 2.6 Settings architecture

- `plugins/khatabook/backend/Api/SettingsController.php`
  - Generic category settings in `kbs_settings`
  - categories currently written by the SPA company settings screen:
    - `company`
    - `sales`
    - `tax`
  - company-logo upload/delete for the real `company.logo_url` consumer path
  - older hidden categories may still exist in stored rows from earlier iterations
  - includes optimistic concurrency versions and ETag support

- `plugins/khatabook/backend/Api/VyRestInvoiceSettings.php`
  - Separate invoice-template settings store in `vy_invoice_template_settings`

### 2.7 Email and notifications

- `plugins/khatabook/backend/Email/EmailManager.php`
  - Canonical branded email shell
  - SMTP configuration resolution
  - `wp_mail` transport configuration

- `plugins/khatabook/backend/Notifications/InternalDocumentNotifier.php`
  - Internal invoice/expense creation notifications
  - Recipients resolved from `kbs_user_org_roles`

### 2.8 Admin and observability

- `plugins/khatabook/backend/Admin/AdminMenu.php`
  - Registers wp-admin pages:
    - Pending Users
    - SMTP Settings
    - Registration Logs
    - System Logs
    - OTP Attempts

- `plugins/khatabook/backend/Api/AdminData.php`
  - paginated admin data endpoints for logs and OTP attempts
- `plugins/khatabook/backend/Admin/AdminPage.php`
  - shared wp-admin page shell
  - shared filter and pagination helpers now used by the live admin support pages

- `plugins/khatabook/backend/Core/SystemLogger.php`
  - writes `kbs_system_logs` and `kbs_registration_logs`
  - now serves as the main path for high-value operational failure logging

- `plugins/khatabook/backend/Core/RecordAuditLogger.php`
  - writes record-level history to `vy_record_history`
  - currently used by invoice create/update/pay/email and expense create/update/archive

### 2.9 Build, dependencies, and runtime tooling

- `plugins/khatabook/composer.json`
  - PSR-4 autoload: `KBS\\` -> `backend/`
  - PDF/QR dependencies:
    - `mpdf/mpdf`
    - `mpdf/qrcode`
  - test entry: `@php tests/run.php`
  - current automated coverage includes helper and controller-rule tests under `plugins/khatabook/tests`, including report endpoints, write rollback safety, and operational logger coverage

- `plugins/khatabook/app/package.json`
  - React 18
  - Ant Design 5
  - Vite 7
  - Tailwind/PostCSS available
  - `react-router-dom` installed but not used by the active app shell

- `plugins/khatabook/app/vite.config.js`
  - Vite root: `app/src`
  - build output: `app/dist`
  - manifest generation enabled

## 3. Frontend Architecture Map

### 3.1 SPA boot and routing

- `plugins/khatabook/app/src/main.jsx`
  - mounts the React app into `#root`
- `plugins/khatabook/app/src/App.jsx`
  - custom pathname router
  - active SPA routes now include:
    - `/home`
    - `/accounts`
    - `/contacts`
    - `/invoices`
    - `/expenses`
    - `/payments`
    - `/reports`
    - `/settings/invoices`
    - `/company-settings`
    - `/users`
    - `/accept-invite`
  - wraps with Ant Design `ConfigProvider`

- `plugins/khatabook/app/src/App.jsx`
  - lightweight pathname-based router, not React Router
  - loads persisted auth from local storage
  - configures API client
  - enforces auth guards
  - handles org switching through `/kbs/v1/active-org`
  - routes:
    - `/login`
    - `/accept-invite`
    - `/home`
    - `/accounts`
    - `/accounts/:id`
    - `/invoices`
    - `/invoices/:id`
    - `/expenses`
    - `/expenses/:id`
    - `/payments`
    - `/reports`
    - `/settings/invoices`
    - `/company-settings`
    - `/users`

- `plugins/khatabook/app/src/layouts/DashboardLayout.jsx`
  - authenticated shell
  - navigation
  - org switcher
  - logout action

### 3.2 Shared frontend utilities

- `plugins/khatabook/app/src/lib/apiClient.js`
  - lazy API client wrapper around the current auth state

- `plugins/khatabook/app/src/utils/apiClient.js`
  - fetch wrapper
  - injects `X-KBS-Token` or `X-WP-Nonce`
  - dispatches `kbs-auth-invalid` on token auth failure

- `plugins/khatabook/app/src/utils/buildQuery.js`
  - shared query-string builder for active module API files

- `plugins/khatabook/app/src/utils/authStorage.js`
  - browser auth persistence
  - AES-GCM when Web Crypto is available
  - fallback plain localStorage payload when crypto is unavailable

- `plugins/khatabook/app/src/hooks/useAsyncResource.js`
  - shared async loading hook used by the active module hooks

- `plugins/khatabook/app/src/components/ToastProvider.jsx`
  - toast abstraction used across pages

- `plugins/khatabook/app/src/components/ui/FeedbackState.jsx`
  - shared loading / empty / error surface for active operational screens

- `plugins/khatabook/app/src/components/ui/InlineNotice.jsx`
  - shared inline form/action notice surface

### 3.3 Page-level frontend entry points

- `plugins/khatabook/app/src/pages/Login.jsx`
  - login OTP flow

- `plugins/khatabook/app/src/pages/Registration.jsx`
  - registration + OTP submission flow

- `plugins/khatabook/app/src/pages/AcceptInvite.jsx`
  - invite inspection and acceptance

- `plugins/khatabook/app/src/pages/CompanySettings.jsx`
  - generic org settings editor for `kbs_settings`

- `plugins/khatabook/app/src/pages/UsersAdmin.jsx`
  - org invite and role management frontend
  - separates active members from pending invites for operational clarity

- `plugins/khatabook/app/src/pages/Home.jsx`
  - live operational dashboard using invoices, expenses, accounts, and report APIs

### 3.4 Domain modules

#### Accounts

- `plugins/khatabook/app/src/modules/accounts/api.js`
- `plugins/khatabook/app/src/modules/accounts/hooks.js`
- `plugins/khatabook/app/src/modules/accounts/AccountsPage.jsx`
- `plugins/khatabook/app/src/modules/accounts/AccountDetailPage.jsx`
- `plugins/khatabook/app/src/modules/accounts/AccountForm.jsx`
- `plugins/khatabook/app/src/modules/accounts/MoneyInForm.jsx`
- `plugins/khatabook/app/src/modules/accounts/MoneyOutForm.jsx`
- `plugins/khatabook/app/src/modules/accounts/TransferForm.jsx`

#### Contacts

- `plugins/khatabook/app/src/modules/contacts/api.js`
- `plugins/khatabook/app/src/modules/contacts/ContactSuggestInput.jsx`
- `plugins/khatabook/app/src/modules/contacts/ContactsPage.jsx`
- `plugins/khatabook/app/src/modules/contacts/ContactForm.jsx`
- `plugins/khatabook/app/src/modules/contacts/ContactsList.jsx`

#### Invoices

- `plugins/khatabook/app/src/modules/invoices/api.js`
- `plugins/khatabook/app/src/modules/invoices/hooks.js`
- `plugins/khatabook/app/src/modules/invoices/InvoicesPage.jsx`
- `plugins/khatabook/app/src/modules/invoices/InvoiceForm.jsx`
- `plugins/khatabook/app/src/modules/invoices/InvoiceList.jsx`
- `plugins/khatabook/app/src/modules/invoices/InvoiceDetailPage.jsx`
- `plugins/khatabook/app/src/modules/invoices/InvoiceDetail.jsx`
- `plugins/khatabook/app/src/modules/invoices/InvoicePaymentForm.jsx`
- `plugins/khatabook/app/src/modules/invoices/RecurringProfileForm.jsx`
- `plugins/khatabook/app/src/modules/invoices/RecurringProfilesList.jsx`
- `plugins/khatabook/app/src/modules/invoices/InvoiceNoteForm.jsx`
- `plugins/khatabook/app/src/modules/invoices/InvoicePromiseForm.jsx`

#### Expenses

- `plugins/khatabook/app/src/modules/expenses/api.js`
- `plugins/khatabook/app/src/modules/expenses/hooks.js`
- `plugins/khatabook/app/src/modules/expenses/ExpensesPage.jsx`
- `plugins/khatabook/app/src/modules/expenses/ExpenseForm.jsx`
- `plugins/khatabook/app/src/modules/expenses/ExpensesList.jsx`
- `plugins/khatabook/app/src/modules/expenses/ExpenseDetailPage.jsx`
- `plugins/khatabook/app/src/modules/expenses/ExpenseDetail.jsx`

#### Payments

- `plugins/khatabook/app/src/modules/payments/api.js`
- `plugins/khatabook/app/src/modules/payments/PaymentsPage.jsx`

#### Reports

- `plugins/khatabook/app/src/modules/reports/api.js`
- `plugins/khatabook/app/src/modules/reports/ProfitTaxPage.jsx`

#### Invoice settings and preview

- `plugins/khatabook/app/src/modules/settings/invoices/invoiceSettingsApi.js`
- `plugins/khatabook/app/src/modules/settings/invoices/InvoiceSettingsPage.jsx`
- `plugins/khatabook/app/src/modules/settings/invoices/InvoiceLogoUploader.jsx`
- `plugins/khatabook/app/src/modules/settings/invoices/InvoiceTemplatePreview.jsx`

## 4. Current Coding Style and Reuse Conventions

### Backend conventions actually present

- static controller classes with REST callbacks
- direct `$wpdb` queries inside controllers
- global helper functions for cross-cutting invoice/org/template/auth logic
- minimal repository/service abstraction
- WordPress hooks used directly in the plugin bootstrap

### Frontend conventions actually present

- functional React components only
- no TypeScript in the active app
- auth boot and routing centralized in `App.jsx`
- domain modules usually organized as:
  - `api.js`
  - `hooks.js`
  - page/list/form/detail components
- mix of Ant Design controls and custom class-based UI
- no shared typed contract layer between frontend and backend was found
## 5. Active Data Model Map

### 4.1 Canonical business tables in current product flow

These are the active transaction/business tables confirmed in code:

- `vy_accounts`
- `vy_bank_accounts`
- `vy_contacts`
- `vy_invoices`
- `vy_invoice_items`
- `vy_invoice_payments`
- `vy_invoice_recurring_profiles`
- `vy_invoice_recurring_items`
- `vy_invoice_notes`
- `vy_invoice_promises`
- `vy_expenses`
- `vy_journal_entries`
- `vy_journal_lines`
- `vy_invoice_template_settings`

These tables are created in:

- `plugins/khatabook/backend/Db/TableManager.php`

They are actively used by:

- `VyRestAccounts.php`
- `VyRestContacts.php`
- `VyRestInvoices.php`
- `VyRestExpenses.php`
- `VyRestReports.php`
- `VyRestInvoiceSettings.php`
- `VyInvoicePdf.php`
- `InvoiceFinancialHelper.php`

### 4.2 Support tables still active in the current product

- `kbs_organizations`
- `kbs_user_org_roles`
- `kbs_pending_users`
- `kbs_org_invites`
- `kbs_settings`
- `kbs_registration_logs`
- `kbs_otp_attempts`
- `kbs_system_logs`

These support:

- auth/onboarding
- org membership and roles
- generic org settings
- admin monitoring/logging

### 4.3 Legacy business tables retained but not wired into the active app

- `kbs_invoices`
- `kbs_invoice_items`
- `kbs_quotations`
- `kbs_quotation_items`
- `kbs_accounting_entries`

Evidence:

- `plugins/khatabook/backend/Db/TableManager.php` explicitly creates them
- active REST modules use `vy_*` tables instead
- no active quotation REST routes or frontend quotation pages were found in the current app scan

## 6. Billing-Critical Flow Map

### 5.1 Login and org hydration

1. `Login.jsx` posts to `/wp-json/kbs/v1/send-otp`
2. `Login.jsx` verifies via `/wp-json/kbs/v1/verify-otp`
3. `OtpAuth.php` issues the single active token and org payload
4. `authStorage.js` stores the auth payload
5. `App.jsx` hydrates auth, configures API headers, and routes into the dashboard shell

### 5.2 Registration and approval

1. `Registration.jsx` sends register-context OTP
2. `Registration.jsx` submits to `/wp-json/kbs/v1/submit-registration`
3. `RegisterController.php` writes `kbs_pending_users`
4. `PendingUserController.php` approves in wp-admin or via admin REST
5. approval creates the WP user and `kbs_user_org_roles` membership

### 5.3 Org switching

1. `DashboardLayout.jsx` exposes the org switcher when `user.orgs.length > 1`
2. `App.jsx` posts to `/wp-json/kbs/v1/active-org`
3. `EndpointManager::set_active_org()` returns refreshed auth payload
4. subsequent `vy/v1` and `kbs/v1` calls use the persisted active org

### 5.4 Invoice create/edit/pay/email/PDF

1. `InvoiceForm.jsx` builds invoice payload
2. `app/src/modules/invoices/api.js` calls `POST /vy/v1/invoices` or `PUT /vy/v1/invoices/{id}`
3. `VyRestInvoices.php` writes invoice header/items in `vy_*` tables
4. invoice template comes from `vy_invoice_template_settings`
5. PDF generation goes through `POST /vy/v1/invoices/{id}/generate-pdf` -> `VyInvoicePdf.php`
6. customer email goes through `POST /vy/v1/invoices/{id}/email` -> `InvoiceEmailHelper.php`
7. payment posting goes through `POST /vy/v1/invoices/{id}/pay` and writes `vy_invoice_payments` + journal entries

### 5.5 Expense create

1. `ExpenseForm.jsx` builds payload
2. `app/src/modules/expenses/api.js` posts to `POST /vy/v1/expenses`
3. `VyRestExpenses.php` writes `vy_expenses`
4. optional journal entry is created if a payment account is selected
5. internal email notification is sent after creation

### 5.6 Invoice settings and preview

1. `InvoiceSettingsPage.jsx` loads `/vy/v1/invoice-settings`
2. template/logo/settings changes save back to `/vy/v1/invoice-settings`
3. logo upload/delete go through `/vy/v1/invoice-settings/logo`
4. `InvoiceTemplatePreview.jsx` calls `/vy/v1/invoices/preview`
5. `VyRestInvoicePreview.php` renders HTML using shared invoice rendering helpers
6. final PDF generation uses the same template resolution direction

## 7. Current Reuse Patterns

### Backend patterns actually used

- static controller classes instead of service-container wiring
- direct `$wpdb` access in REST controllers
- helper-function files for shared invoice/org/auth/template logic
- role and org checks via helper methods rather than middleware layers

### Frontend patterns actually used

- each domain module usually has:
  - `api.js`
  - `hooks.js`
  - list/detail/form components
- repeated local `useAsync` hook implementations in each module
- custom modal implementations inside module pages
- auth + API boot handled globally in `App.jsx`

## 8. Important Gaps or Unclear Areas

- Quotations appear only in legacy schema creation inside `plugins/khatabook/backend/Db/TableManager.php`. No active quotation controller, route, or page was found.
- `plugins/khatabook/app/src/pages/Home.jsx` computes receivables from the latest 100 sent invoices and the latest 100 partial invoices, so high-volume orgs can still undercount dashboard receivables until a dedicated summary path exists.
- Audit/version history for invoices, expenses, and payments was not found beyond:
  - generic system logs
  - registration logs
  - OTP attempt logs
  - `kbs_settings.version`
- Shared typed contracts were not found. Payload shapes are implicit across PHP and JS.
- Upload/media handling is implemented for invoice logos in `VyRestInvoiceSettings.php`. A broader media abstraction was not found.

Needs manual verification:

- Whether any third-party or theme code depends on the legacy `kbs_invoices` or `kbs_quotations` tables outside this plugin
- Whether `CompanySettings.jsx` categories beyond `company`, `sales`, and `tax` have external consumers not visible in this plugin scan
