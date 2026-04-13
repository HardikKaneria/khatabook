# Product Feature Status

Base path: `/Users/hardikkaneria/Local Sites/khatabook/app/public/wp-content`

Reference scope:
- `plugins/khatabook/ai-workflow/product_feature_list.md`

Snapshot basis:
- direct inspection of the current plugin code
- current active backend routes, tables, helpers, templates, and SPA modules
- existing workflow files only where they match live code

## Status Legend

- `DONE` — clearly live in the current product
- `PARTIAL` — active enough to use, but materially narrower than the requested feature list
- `TODO` — not found as a live module in the current repo
- `BLOCKED` — intentionally not active because the codebase lacks a safe dependency or approved direction
- `SUPERSEDED` — the requested item was replaced by an approved current-product direction

## 1. Section Status Summary

| Product Feature List Section | Current Status | Current Repo Reality |
|---|---|---|
| 1.1 Organization and Multi-Tenant Management | `PARTIAL` | Multi-org membership, org switching, org creation, profile/settings, logo upload, tax and numbering fields exist; branch-level depth, financial-year/timezone depth, and branch assignment are not live. |
| 1.2 User and Role Management | `PARTIAL` | Org roles, invite/member management, and server-side org authorization are live; no full permission matrix UI, branch restrictions, or 2FA path exists. |
| 1.3 Customer Management | `PARTIAL` | Customer records, GST fields, addresses, notes, status, and statements are live; documents, tags, credit limits, payment behavior profile, merge, and opening balance are not. |
| 1.4 Vendor Management | `PARTIAL` | Vendors exist through the shared contacts model and vendor-bill/payables flows; categories, document attachments, and duplicate detection are not live. |
| 1.5 Item and Service Management | `TODO` | No item, service, SKU, catalog, or inventory-linked billing module exists in the current repo. |
| 2.1 Quotations / Estimates | `BLOCKED` | Legacy `kbs_quotations` schema exists, but no active route, helper, or SPA module is wired. |
| 2.2 Proforma Invoices | `TODO` | No active proforma route, table family, or SPA flow was found. |
| 2.3 Invoices | `PARTIAL` | Core invoice lifecycle is strong and live, but the broader requested feature set still exceeds the implemented module. |
| 2.4 Credit Notes and Debit Notes | `PARTIAL` | Credit/debit note posting with invoice balance adjustment is live; approval/PDF/email extensions are not. |
| 2.5 Recurring Billing | `PARTIAL` | Recurring profiles, intervals, manual generation, and scheduled runs are live; auto-send, retry logic, and renewal reminders are not. |
| 3.1 Payment Recording | `PARTIAL` | Single-invoice payment posting is live and guarded; advance/overpayment, multi-invoice allocation, receipt flows, and undo are not. |
| 3.2 Online Payment Features | `PARTIAL` | Invoice QR display exists; payment links, gateways, and webhook-driven settlement do not. |
| 3.3 Collections and Reminders | `TODO` | No dedicated reminder engine, reminder logs, or follow-up queue exists in the active product path. |
| 3.4 Promise-to-Pay Tracking | `PARTIAL` | Promise records and kept/broken resolution are live; reliability scoring and queueing are not. |
| 3.5 Collection Copilot | `TODO` | No AI or rule-driven collection-priority assistant exists as a dedicated module. |
| 3.6 Reconciliation | `TODO` | No bank reconciliation workflow or matching engine exists. |
| 4.1 Expense Management | `PARTIAL` | Expense create/edit/archive/settle is live; recurring, approval, attachments, cost-center, and anomaly features are not. |
| 4.2 Vendor Bills / Purchase Bills | `PARTIAL` | Vendor bills run on the expense model with due-state and settlement support; approval, PDF upload, and duplicate detection are not live. |
| 4.3 OCR and Smart Extraction | `BLOCKED` | No expense attachment model, OCR parser, or extraction service exists. |
| 5. Inventory and Product Billing Extensions | `BLOCKED` | No inventory or stock subsystem exists in the live repo path. |
| 6.1 Invoice Settings | `PARTIAL` | Template selection, logo upload, QR/tax-breakup toggles, notes/terms, bank details, and email template settings are live; signature/stamp upload and several deeper config areas are not. |
| 6.2 Notification Settings | `PARTIAL` | Some notification behavior is live in code, but there is no dedicated notification-settings module. |
| 6.3 Approval Settings | `TODO` | No module-wide approval rules engine exists. |
| 6.4 Tax and Compliance Settings | `PARTIAL` | GST/tax settings exist at current-product depth; broader compliance/readiness settings are not fully wired. |
| 7.1 Standard Reports | `PARTIAL` | Sales/invoice/payment/outstanding/overdue/expense/payable/receivable/tax reporting is live; item, branch, and user report families are not. |
| 7.2 Advanced Reports | `PARTIAL` | Aging, trends, billing health, daily brief, revenue leak, and risk are live; approval, reminder, quote, duplicate, and compliance reports are not. |
| 7.3 Dashboards | `PARTIAL` | A strong operational home dashboard exists, but dedicated role dashboards and branch dashboards do not. |
| 8.1 AI Invoice Assistant | `BLOCKED` | No approved AI provider/client/config path exists in the active plugin architecture. |
| 8.2 AI Expense Assistant | `BLOCKED` | OCR and AI expense parsing are blocked by missing attachment and AI infrastructure. |
| 8.3 AI Business Assistant | `PARTIAL` | Deterministic daily-brief and reporting summaries exist; no natural-language assistant exists. |
| 8.4 AI Reminder Generator | `BLOCKED` | No approved AI provider path or reminder engine exists. |
| 8.5 AI Risk and Prediction | `PARTIAL` | Rule-based invoice risk, billing health, and revenue leak detection exist; predictive AI flows do not. |
| 8.6 AI Summaries | `PARTIAL` | Owner daily brief exists; broader AI summary surfaces do not. |
| 9.1 Billing Health Score | `PARTIAL` | Live score with component reasons exists; trend-over-time history is not present. |
| 9.2 Owner Daily Brief | `PARTIAL` | Live operational brief exists; weekly review and deeper alert families are not. |
| 9.3 Invoice Risk Engine | `PARTIAL` | Rule-based risk checks are live; attachment/delivery-proof checks and deeper anomaly detection are not. |
| 9.4 Revenue Leak Detector | `PARTIAL` | Live leak detection exists, but quote/contract/service-delivered checks are not present. |
| 9.5 Next Best Action Engine | `TODO` | No dedicated next-action engine exists. |
| 9.6 Recovery Mode | `TODO` | No dedicated recovery workspace exists. |
| 9.7 Customer Payment Behavior Profile | `TODO` | No customer-level behavior profile model or UI exists. |
| 9.8 Decision Timeline | `PARTIAL` | Record history exists for invoices, expenses, and payments, but not the full cross-customer decision timeline requested. |
| 10. Collaboration and Workflow Features | `TODO` | No internal comments, mentions, assignments, or discussion-thread system exists. |
| 11.1 Audit Trail | `PARTIAL` | Record history and system logs exist, but not full before/after snapshot coverage and export/search tooling across all entities. |
| 11.2 Control Features | `PARTIAL` | Controlled financial edits and journal-safe restrictions exist; soft-recovery, forced reason capture, and sensitive-action alerts are not complete. |
| 11.3 Compliance | `PARTIAL` | Numbering consistency and duplicate protection exist in core flows; export bundles and retention tooling are not present. |
| 12. Notification and Communication Features | `PARTIAL` | Internal invoice/expense creation emails and branded document emails are live; in-app notifications and digest engines are not. |
| 13. Customer Portal | `TODO` | No customer-authenticated portal exists. |
| 14. Integrations and API Readiness | `PARTIAL` | Headless REST architecture and auth/permissions are live; webhook and third-party integration surfaces are not. |
| 15. Headless WordPress Architecture Requirements | `PARTIAL` | Most of the architectural requirements are already met, but there is still no shared typed-contract layer. |
| 16. UX and Product Experience Requirements | `PARTIAL` | Operational UX is strong on active screens, but global search, saved filters, bulk actions, and some keyboard-first workflows are missing. |
| 17. Non-Negotiable Business Rules | `PARTIAL` | Most requested rules are live; “every critical record” history and full notification breadth are still narrower than the requested list. |
| 18. Priority Buckets for Codex | `PARTIAL` | Many P0/P1 foundations are already active; several P2/P3 items remain blocked or future-scope. |

## 2. Confirmed Live Feature Areas

These are clearly implemented in the current repo and should be treated as active product baseline:

- OTP login and registration with approval gating and single-session auth.
  - Evidence: `plugins/khatabook/backend/Auth/OtpAuth.php`, `plugins/khatabook/backend/Auth/RegisterController.php`, `plugins/khatabook/backend/Endpoint/EndpointManager.php`
- Multi-organization membership, active-org switching, company-admin organization creation, and org-safe membership management.
  - Evidence: `plugins/khatabook/backend/Api/OrgUsersController.php`, `plugins/khatabook/backend/Helpers/OrgHelper.php`, `plugins/khatabook/app/src/App.jsx`, `plugins/khatabook/app/src/pages/UsersAdmin.jsx`
- Accounts, money movement, statements, and journal-backed balances.
  - Evidence: `plugins/khatabook/backend/Api/VyRestAccounts.php`, `plugins/khatabook/backend/Accounting/VyJournalEngine.php`, `plugins/khatabook/app/src/modules/accounts/*`
- Customer/vendor contacts with create/edit/archive and customer statements.
  - Evidence: `plugins/khatabook/backend/Api/VyRestContacts.php`, `plugins/khatabook/app/src/modules/contacts/*`
- Invoice create/edit/pay/email/PDF, recurring profiles, credit/debit notes, refund reversal, promise tracking, and invoice risk checks.
  - Evidence: `plugins/khatabook/backend/Api/VyRestInvoices.php`, `plugins/khatabook/backend/Helpers/InvoiceFinancialHelper.php`, `plugins/khatabook/backend/Helpers/InvoiceRiskHelper.php`, `plugins/khatabook/app/src/modules/invoices/*`
- Expense and vendor-bill lifecycle with later settlement and payables visibility.
  - Evidence: `plugins/khatabook/backend/Api/VyRestExpenses.php`, `plugins/khatabook/backend/Helpers/ExpenseEditHelper.php`, `plugins/khatabook/app/src/modules/expenses/*`
- Operational reporting, dashboard summaries, billing health, owner daily brief, and revenue leak reporting.
  - Evidence: `plugins/khatabook/backend/Api/VyRestReports.php`, `plugins/khatabook/backend/Helpers/ReportHelper.php`, `plugins/khatabook/app/src/pages/Home.jsx`, `plugins/khatabook/app/src/modules/reports/*`
- Invoice template settings, 4 direct invoice templates, authenticated preview, PDF generation, and drag-and-drop logo upload.
  - Evidence: `plugins/khatabook/backend/Helpers/InvoiceTemplateHelper.php`, `plugins/khatabook/backend/Helpers/InvoiceTemplateRenderHelper.php`, `plugins/khatabook/backend/Api/VyRestInvoiceSettings.php`, `plugins/khatabook/backend/Api/VyRestInvoicePreview.php`, `plugins/khatabook/backend/Invoices/VyInvoicePdf.php`, `plugins/khatabook/app/src/modules/settings/invoices/*`
- Record history and operational/admin logging.
  - Evidence: `plugins/khatabook/backend/Core/RecordAuditLogger.php`, `plugins/khatabook/backend/Core/SystemLogger.php`, `plugins/khatabook/backend/Api/AdminData.php`
- Branded document email and internal admin/manager notifications for invoice and expense creation.
  - Evidence: `plugins/khatabook/backend/Email/EmailManager.php`, `plugins/khatabook/backend/Helpers/InvoiceEmailHelper.php`, `plugins/khatabook/backend/Notifications/InternalDocumentNotifier.php`

## 3. Important Partial Areas

These areas are active, but they do not yet satisfy the full requested feature list:

### 3.1 Organization, roles, and settings depth

What exists:
- org settings, company logo, GST registration, income-tax rate, base currency, invoice prefix/suffix

What is still missing or thin:
- financial-year settings
- timezone settings as a first-class org setting
- branch-level settings and branch-level restrictions
- explicit permission matrix management UI
- approval-authority mapping

Primary evidence:
- `plugins/khatabook/app/src/pages/CompanySettings.jsx`
- `plugins/khatabook/backend/Api/SettingsController.php`
- `plugins/khatabook/backend/Helpers/OrgHelper.php`

### 3.2 Customer and vendor enrichment

What exists:
- shared contacts table for customers/vendors
- GSTIN, billing/shipping address, notes, status
- customer statement modal
- vendor bills and payables on the expense model

What is still missing or thin:
- customer contact persons as separate sub-records
- document attachments
- tags
- credit limits
- payment terms per contact
- opening balances
- duplicate merge/detection flows
- customer risk/profile surfaces
- vendor categories

Primary evidence:
- `plugins/khatabook/backend/Db/TableManager.php`
- `plugins/khatabook/backend/Api/VyRestContacts.php`
- `plugins/khatabook/app/src/modules/contacts/*`

### 3.3 Invoice breadth vs requested document system

What exists:
- create/edit/pay/refund/email/PDF
- org-driven template selection
- QR toggle
- tax-breakup toggle
- recurring profiles
- credit/debit notes
- promise-to-pay
- record history

What is still missing or thin:
- quotations
- proforma invoices
- attachment uploads
- customer open/view tracking
- reminder engine and reminder tracking
- payment links/gateway settlement
- internal comments/tags
- richer discount/shipping/rounding controls
- the old “10 premium templates” request is superseded by the current approved 4-template catalog

Primary evidence:
- `plugins/khatabook/backend/Api/VyRestInvoices.php`
- `plugins/khatabook/backend/Helpers/InvoiceTemplateHelper.php`
- `plugins/khatabook/backend/templates/invoices/*`
- `plugins/khatabook/app/src/modules/invoices/*`

### 3.4 Payments and collections depth

What exists:
- invoice payment recording
- partial/full payment handling
- promise tracking
- payment activity page
- duplicate-submit protection

What is still missing or thin:
- advance payment balance
- overpayment/credit balance handling
- multi-invoice payment allocation
- receipt generation/PDF/email
- proof-of-payment attachments
- undo payment flow
- reminder scheduling and follow-up history
- collection-owner routing

Primary evidence:
- `plugins/khatabook/backend/Api/VyRestInvoices.php`
- `plugins/khatabook/app/src/modules/payments/PaymentsPage.jsx`

### 3.5 Expenses and payables depth

What exists:
- expense and vendor bill create/edit/archive
- later settlement
- due-state and payables reporting
- audit history

What is still missing or thin:
- receipt/bill attachments
- OCR intake
- recurring expenses
- approval-required expenses
- cost center, department, and branch tags
- unusual expense alerts
- duplicate vendor bill detection

Primary evidence:
- `plugins/khatabook/backend/Api/VyRestExpenses.php`
- `plugins/khatabook/app/src/modules/expenses/*`

### 3.6 Reports and differentiators depth

What exists:
- receivables and payables snapshots
- aging
- trends
- billing health
- owner daily brief
- revenue leak reporting
- invoice risk

What is still missing or thin:
- dedicated item-wise, branch-wise, and user-wise reports
- approval and reminder analytics
- quote analytics
- compliance/export bundles
- trend-over-time persistence for billing health
- customer payment behavior profile
- next best action and recovery workspace

Primary evidence:
- `plugins/khatabook/backend/Helpers/ReportHelper.php`
- `plugins/khatabook/backend/Helpers/InvoiceRiskHelper.php`
- `plugins/khatabook/app/src/pages/Home.jsx`
- `plugins/khatabook/app/src/modules/reports/ProfitTaxPage.jsx`

## 4. Clearly Missing or Still Blocked

No active implementation was found for these feature families:

- quotations / estimates active module
- proforma invoices
- item/service master
- inventory / stock / warehouses
- reconciliation engine
- customer portal
- collaboration features like comments, mentions, tasks, watch/follow, and discussion threads
- in-app notifications center
- webhook framework
- payment gateway handling
- OCR bill extraction
- AI invoice assistant
- AI expense assistant
- AI reminder generator

Blocked-by-repo-state items:

- OCR bill extraction is blocked because the current repo has no expense-file attachment model, no OCR/parser dependency, and no existing extraction service path.
- AI-assisted flows are blocked because the current repo has no approved AI provider/client/config path in the active plugin architecture.
- Quotations remain blocked from active execution because only legacy `kbs_quotations` schema exists; no active `vy_*` quotation module is wired.

## 5. How Agents Must Use This File

- Treat this file as the current implementation-status companion to `product_feature_list.md`.
- Do not mark a feature `DONE` here unless the code path is live and inspectable.
- After meaningful feature work that changes coverage against the product feature list:
  - update this file
  - append an entry to `PRODUCT_FEATURE_PROGRESS_LOG.md`
  - keep `AI_CHANGELOG.md`, `AI_FEATURE_BACKLOG.md`, and `AI_NEXT_ACTIONS.md` aligned
