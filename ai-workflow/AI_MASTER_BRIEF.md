# AI Master Brief

Base path: `/Users/hardikkaneria/Local Sites/khatabook/app/public/wp-content`

## 1. Project Reality

This repository is an existing headless WordPress billing and business-control application implemented inside a single plugin:

- plugin root: `plugins/khatabook`

This is not a generic WordPress site feature set.  
It is a transactional business system using WordPress as the host platform and REST backend.

The current product already supports:

- OTP login and registration with admin approval
- organization-scoped membership and org switching
- accounts and journal-backed money movement
- first-class customer and vendor contact management
- customer statements from live invoice and payment activity
- operational dashboard summaries on the SPA home screen
- company settings limited to confirmed live settings categories plus company-logo upload for the confirmed fallback-render consumer
- invoice creation, editing, payment posting, preview, PDF, and email
- recurring billing profiles on live `vy_*` invoices with manual and scheduled generation
- invoice-linked credit notes and debit notes that adjust receivables server-side
- promise-to-pay tracking tied to invoice collections workflows
- payment activity visibility beyond invoice detail
- record-level financial history for invoices, expenses, and invoice payments
- invoice template settings and logo upload
- expense creation, editing, archive, and detail tracking
- profit, GST, tax, receivables aging, invoice status, top-balance, and monthly trend reporting
- internal business email notifications

---

## 2. Product Direction

Continue building this as:

- a React SPA frontend
- backed by WordPress REST APIs
- using org-scoped permissions
- with custom transactional tables
- with document generation and business-state visibility as core product strengths

This product should continue moving toward:
- operational clarity
- financial trustworthiness
- controlled business workflows
- modular growth
- repo-consistent architecture

It should not drift toward:
- admin-only WordPress tooling
- legacy table expansion for new business work
- decorative SaaS-style dashboarding
- architecture churn without clear product value

---

## 3. Architecture Direction

### 3.1 Backend
- WordPress plugin bootstrap in `plugins/khatabook/main.php`
- static REST controllers under `plugins/khatabook/backend/Api`
- helper-driven shared business logic in `plugins/khatabook/backend/Helpers`
- direct `$wpdb` access is the dominant persistence style
- business logic should remain org-scoped and server-authoritative

### 3.2 Frontend
- React 18 + Vite app in `plugins/khatabook/app`
- custom pathname router in `app/src/App.jsx`
- route-level page boundaries are now lazy-loaded; keep new routes compatible with that loading model
- Ant Design plus repo-specific components/styles
- module shape should remain:
  - `api.js`
  - `hooks.js`
  - page/list/detail/form components
- active modules now also rely on shared frontend foundations:
  - `app/src/hooks/useAsyncResource.js`
  - `app/src/utils/buildQuery.js`
  - `app/src/components/ui/FeedbackState.jsx`
  - `app/src/components/ui/InlineNotice.jsx`

### 3.3 Data direction
Canonical business schema direction:
- `vy_*`

Support/platform tables still active:
- `kbs_organizations`
- `kbs_user_org_roles`
- `kbs_pending_users`
- `kbs_org_invites`
- `kbs_settings`
- `kbs_registration_logs`
- `kbs_otp_attempts`
- `kbs_system_logs`

Legacy business tables exist but are not the active direction for new business work:
- `kbs_invoices`
- `kbs_invoice_items`
- `kbs_quotations`
- `kbs_quotation_items`
- `kbs_accounting_entries`

---

## 4. Product Objectives

The product should help organizations:
- create and manage business records accurately
- track money movement clearly
- generate reliable invoices and documents
- control org-scoped access safely
- reduce operational confusion
- expand toward deeper reporting and business intelligence over time

The product should feel:
- fast
- operational
- accurate
- consistent
- trustworthy

---

## 5. Stack Summary

### Backend
- PHP in a WordPress plugin
- WordPress REST API
- custom MySQL tables via `$wpdb`
- WordPress media APIs for uploads
- mPDF and `mpdf/qrcode` for invoice rendering

### Frontend
- React 18
- Vite 7
- Ant Design 5
- custom CSS and module-level components

### Testing
- lightweight custom PHP harness in `plugins/khatabook/tests`
- helper-level and controller-rule coverage now exists for auth, org access, invoice create/update/payment list, recurring billing generation, invoice credit/debit notes, promise-to-pay state changes, expense create/update/archive, report endpoints, transaction rollback behavior, system logging, and invoice template behavior
- no browser test suite currently active
- no shared typed contract layer currently active

---

## 6. Core Active Modules

### Auth and onboarding
Purpose:
- OTP login
- OTP-backed registration
- approval gate before active access
- single-session token flow

Primary files:
- `backend/Auth/OtpAuth.php`
- `backend/Auth/RegisterController.php`
- `backend/Admin/PendingUserController.php`
- `app/src/pages/Login.jsx`
- `app/src/pages/Registration.jsx`

### Organization and permissions
Purpose:
- org membership resolution
- active org persistence
- invite management
- org user/role management
- org-safe API behavior

Primary files:
- `backend/Helpers/OrgHelper.php`
- `backend/Api/OrgUsersController.php`
- `backend/Endpoint/EndpointManager.php`
- `app/src/App.jsx`
- `app/src/layouts/DashboardLayout.jsx`

### Accounts and journals
Purpose:
- ledger accounts
- balances and statements
- receipt/payment/transfer posting

Primary files:
- `backend/Api/VyRestAccounts.php`
- `backend/Accounting/VyJournalEngine.php`
- `app/src/modules/accounts/*`

### Contacts
Purpose:
- shared customer/vendor records
- billing contacts used across invoices and expenses
- direct list/create/edit/archive management in the SPA

Primary files:
- `backend/Api/VyRestContacts.php`
- `app/src/modules/contacts/*`

### Invoices
Purpose:
- invoice create/list/detail/edit
- recurring billing profile setup and generation
- invoice-linked credit/debit adjustments
- invoice-linked promise-to-pay tracking
- payment posting
- payment activity visibility
- email and PDF generation
- numbering and document rendering

Primary files:
- `backend/Api/VyRestInvoices.php`
- `backend/Helpers/InvoiceEditHelper.php`
- `backend/Helpers/InvoiceFinancialHelper.php`
- `backend/Helpers/InvoiceEmailHelper.php`
- `backend/Invoices/VyInvoicePdf.php`
- `app/src/modules/invoices/*`
- `app/src/modules/payments/*`

### Expenses
Purpose:
- expense create/list/detail/update/archive
- optional journal-backed payment posting

Primary files:
- `backend/Api/VyRestExpenses.php`
- `app/src/modules/expenses/*`

Current product note:
- edit and archive are intentionally blocked once a payment journal exists
- detail views now include record-level activity history

### Reports
Purpose:
- profit summary
- GST summary
- tax estimate
- receivables snapshot
- receivables aging
- invoice status mix
- top customer balances
- monthly invoice and expense trend

Primary files:
- `backend/Api/VyRestReports.php`
- `backend/Helpers/ReportHelper.php`
- `app/src/modules/reports/ProfitTaxPage.jsx`

### Invoice templates and document settings
Purpose:
- org-level template selection
- invoice preview
- logo upload
- email template settings
- PDF/html rendering consistency

Primary files:
- `backend/Api/VyRestInvoiceSettings.php`
- `backend/Api/VyRestInvoicePreview.php`
- `backend/Helpers/InvoiceTemplateHelper.php`
- `backend/Helpers/InvoiceRenderHelper.php`
- `backend/Helpers/InvoiceTemplateRenderHelper.php`
- `backend/templates/invoices/*`
- `app/src/modules/settings/invoices/*`

### Company settings
Purpose:
- generic org settings in `kbs_settings`

Primary files:
- `backend/Api/SettingsController.php`
- `app/src/pages/CompanySettings.jsx`

Current product note:
- the active UI is intentionally narrowed to company identity, invoice defaults, and tax settings with confirmed live readers

### Operational dashboard
Purpose:
- expose live receivables, expenses, balances, and tax state on the SPA landing page
- provide fast navigation into invoice, payment, expense, contact, account, and report workflows

Primary files:
- `app/src/pages/Home.jsx`
- `app/src/modules/invoices/api.js`
- `app/src/modules/expenses/api.js`
- `app/src/modules/accounts/api.js`
- `app/src/modules/reports/api.js`

### Admin and monitoring
Purpose:
- pending user approval
- SMTP settings
- system/OTP/registration log visibility

Primary files:
- `backend/Admin/*`
- `backend/Api/AdminData.php`
- `backend/Core/SystemLogger.php`

---

## 7. UX Doctrine Summary

The UX direction of this product is:

- operational, not decorative
- document- and business-oriented
- calm, readable, and trustworthy
- action-focused
- consistent across modules
- grounded in real backend capability

Future UI work must follow:
- `ai-workflow/AI_UX_DOCTRINE.md`

---

## 8. Non-Negotiable Business Rules

### 8.1 Data and schema
- new business/accounting work must use `vy_*`
- do not extend legacy `kbs_*` business tables for new product work

### 8.2 Org safety
- org access is enforced server-side
- client org values are not authorization proof
- active org must be validated against actual membership

### 8.3 Authorization
- org roles come from `kbs_user_org_roles`
- global WordPress roles are not the business authorization source

### 8.4 Auth
- login OTP and registration OTP remain separate flows
- public verification must not create approved users automatically
- auth remains intentionally single-session unless explicitly redesigned

### 8.5 Invoices
- template selection is org-level
- numbering integrity must be preserved
- preview, HTML render, email, and PDF must stay aligned
- edit locking rules must remain enforceable server-side
- recurring invoices must generate through the live invoice creation path so numbering, org safety, and document behavior stay consistent
- credit/debit notes must adjust invoice balance due on the server, and payments must validate against the adjusted balance
- promise-to-pay tracking is operational only; it must not be treated as payment settlement until a real payment is posted

### 8.6 Settings
- generic org settings source: `kbs_settings`
- invoice document/email/logo settings source: `vy_invoice_template_settings`

---

## 9. Quality Standards

### 9.1 Product quality
Work must be:
- repo-consistent
- production-ready
- validation-safe
- org-safe
- state-aware
- user-complete

### 9.2 UX quality
A screen is not complete unless:
- users can understand it quickly
- state is visible
- actions are clear
- blocked actions are explained
- loading/empty/error states are handled
- the UI matches real backend behavior

### 9.3 Code quality
Changes should:
- reuse existing patterns
- avoid duplicate business logic
- remain small and coherent
- prefer stability over speculative architecture changes

---

## 10. Performance and Security Expectations

### Performance
- keep list/admin endpoints paginated or bounded
- avoid expensive repeated query logic where helpers already exist
- keep invoice preview and PDF rendering deterministic
- avoid unnecessary frontend/runtime complexity

### Security
- every write path must validate input
- every org-scoped path must enforce org access server-side
- use WordPress-safe APIs for uploads and email
- do not store new secrets or sensitive auth artifacts in plaintext
- do not leak sensitive payloads into browser console or logs

---

## 11. Definition of Done

A task is only done when:
- exact current code path was inspected first
- active architecture was preserved
- reusable logic was reused where possible
- auth, org safety, and validation are correct
- empty/error/forbidden/blocked states are handled
- frontend and backend expectations match
- tests were added when practical or manual verification is documented
- workflow files were updated where relevant

---

## 12. Workflow Control Files

Future runs should keep these in sync with real repository state:

- `ai-workflow/AI_MASTER_BRIEF.md`
- `ai-workflow/AI_SYSTEM_RULES.md`
- `ai-workflow/AI_FILE_MAP.md`
- `ai-workflow/AI_TECH_DEBT.md`
- `ai-workflow/AI_FEATURE_BACKLOG.md`
- `ai-workflow/AI_NEXT_ACTIONS.md`
- `ai-workflow/AI_UX_DOCTRINE.md`
- `ai-workflow/AI_IMPLEMENTATION_PLAYBOOK.md`
- `ai-workflow/AI_CHANGELOG.md`
