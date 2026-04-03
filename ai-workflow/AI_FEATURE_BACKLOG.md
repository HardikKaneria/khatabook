# AI Feature Backlog

Base path: `/Users/hardikkaneria/Local Sites/khatabook/app/public/wp-content`

This backlog is the execution source of truth for future Codex runs.

It is based on:
- the current inspected repository state
- the active product modules already confirmed in code
- the current workflow rules and UX doctrine
- the broader product vision, but only where it fits the current architecture safely

This file is for execution, not brainstorming.

---

## Status Meanings

- `TODO` — confirmed gap with no complete implementation
- `IMPROVE` — active capability exists but is incomplete, thin, or under-protected
- `IN_PROGRESS` — active work is ongoing
- `BLOCKED` — do not build until a product or architecture decision is made
- `REVIEW` — implementation exists and needs final verification or cleanup
- `DONE` — active baseline capability already exists in the product

---

## Priority Meanings

- `P0` — foundational safety or product-critical work
- `P1` — strong product-quality work with immediate business value
- `P2` — important extensions after current core gaps are closed
- `P3` — future advanced features and differentiators

---

# Current Baseline — Already Active, Do Not Rebuild As New Features

These are active enough to treat as the current working baseline:

- OTP login/register split with approval gate
- Single-session auth
- Org switching
- Org users and invite management
- Accounts and journal posting
- Contacts list/create/edit/archive
- Operational dashboard summaries and quick actions
- Company settings limited to confirmed live categories
- Invoice create/edit/pay/email/PDF
- Payment activity page plus dashboard visibility
- Record-level history for invoices, expenses, and invoice payments
- Expense create/list/detail/update/archive
- Invoice templates, preview, and logo upload

Do not re-add these as brand new TODO features unless the task is specifically to improve or expand them.

---

# P0 — Current Execution Queue

## P0-01 — Strengthen automated coverage for current live flows
- Priority: P0
- Status: DONE
- Area: Testing / Reliability
- Problem:
  The most important live workflows needed executable protection before more product work continued.
- Why:
  The codebase already has active auth, org, invoice, and expense behavior. That behavior should be protected before expanding product scope.
- Scope:
  - auth login approval/rejection logic
  - single-session token invalidation rules
  - org membership allow/deny coverage
  - invoice create/update rule coverage
  - expense create behavior coverage where practical
  - invoice template behavior coverage where relevant
- Constraints:
  - reuse the current lightweight PHP test harness
  - do not introduce a new testing platform
  - keep tests deterministic and local
- Primary Entry Points:
  - `plugins/khatabook/tests/bootstrap.php`
  - `plugins/khatabook/tests/run.php`
  - `plugins/khatabook/tests/*Test.php`
  - `plugins/khatabook/backend/Auth/OtpAuth.php`
  - `plugins/khatabook/backend/Endpoint/EndpointManager.php`
  - `plugins/khatabook/backend/Api/VyRestInvoices.php`
  - `plugins/khatabook/backend/Api/VyRestExpenses.php`
- Acceptance Criteria:
  - highest-value current auth/org/invoice/expense rules have executable coverage
  - `composer test` remains the main test entry point
  - any still-uncovered areas are documented clearly
- Notes:
  Completed on 2026-04-03 with deterministic controller-flow coverage added to the existing PHP harness. Remaining uncovered areas are browser/UI flows and true WordPress REST integration, which should be treated as future quality work rather than a blocker for the next product task.

---

## P0-02 — Build dedicated contacts / customers / vendors module
- Priority: P0
- Status: DONE
- Area: Contacts
- Problem:
  Contacts exist as a backend business entity and are already used in invoice/expense forms, but the SPA has no first-class management surface.
- Why:
  Customers and vendors are core billing entities. This is one of the highest-value product gaps with low architectural risk.
- Scope:
  - contacts page route
  - contacts list page
  - search and filters
  - create contact
  - edit contact
  - archive contact
  - type labeling: `CUSTOMER`, `VENDOR`, `BOTH`
  - contact list empty/loading/error states
  - consistent page header, actions, and filters
- Constraints:
  - use the existing contacts API
  - preserve current frontend module patterns
  - do not invent a second contact model
- Primary Entry Points:
  - `plugins/khatabook/backend/Api/VyRestContacts.php`
  - `plugins/khatabook/app/src/modules/contacts/api.js`
  - `plugins/khatabook/app/src/modules/contacts/ContactSuggestInput.jsx`
  - `plugins/khatabook/app/src/App.jsx`
- Acceptance Criteria:
  - dedicated contacts page exists in the SPA
  - users can list, search, create, edit, and archive contacts
  - customer/vendor/both labeling is clear
  - page follows operational UI standards
- Notes:
  Completed on 2026-04-03. The SPA now has a first-class contacts route with list, filters, create, edit, archive, pagination, and consistent customer/vendor/both labeling on top of the existing org-safe contacts API.

---

## P0-03 — Replace placeholder dashboard with real operational home
- Priority: P0
- Status: DONE
- Area: Dashboard
- Problem:
  The current home screen is a placeholder even though the app already has invoices, expenses, accounts, and reports.
- Why:
  The default landing page should immediately show business status and next actions.
- Scope:
  - receivables summary
  - recent invoices
  - recent expenses
  - account balance summary
  - profit / GST / tax snapshot using real current data
  - overdue summary
  - quick actions
  - recent activity or alerts panel
- Constraints:
  - use real live data only
  - no fake charts or vanity widgets
  - keep the dashboard operational, not decorative
- Primary Entry Points:
  - `plugins/khatabook/app/src/pages/Home.jsx`
  - `plugins/khatabook/backend/Api/VyRestReports.php`
  - `plugins/khatabook/backend/Api/VyRestAccounts.php`
  - `plugins/khatabook/backend/Api/VyRestInvoices.php`
  - `plugins/khatabook/backend/Api/VyRestExpenses.php`
- Acceptance Criteria:
  - dashboard uses real live data
  - users can understand current org status quickly
  - at least one action path is available from each major section
  - loading, empty, and error states exist
- Notes:
  Completed on 2026-04-03. The SPA home screen now shows live receivables, recent invoices, recent expenses, money-account balances, tax/profit snapshots, and quick-create actions. A dedicated summary endpoint still does not exist, so receivables are currently derived from the latest 100 sent invoices and latest 100 partial invoices.

---

## P0-04 — Complete expense lifecycle with update and archive/delete behavior
- Priority: P0
- Status: DONE
- Area: Expenses
- Problem:
  Expenses currently support create/list/detail but not a full maintainable lifecycle.
- Why:
  Real business apps need safe correction and cleanup behavior for expenses.
- Scope:
  - expense update API
  - expense archive or delete behavior
  - journal-aware edit restrictions
  - expense edit UI
  - blocked-state messaging for unsupported edit/delete states
  - action history surface if practical
- Constraints:
  - must preserve journal integrity
  - do not allow unsafe mutation of finalized or journalized records
  - inspect current expense create and detail flow first
- Primary Entry Points:
  - `plugins/khatabook/backend/Api/VyRestExpenses.php`
  - `plugins/khatabook/app/src/modules/expenses/*`
  - `plugins/khatabook/backend/Accounting/VyJournalEngine.php`
- Acceptance Criteria:
  - allowed expenses can be edited safely
  - restricted expenses clearly explain why editing is blocked
  - archive/delete behavior is defined and safe
  - UI does not expose fake unsupported actions
- Notes:
  Completed on 2026-04-03. Expenses now support update and archive flows with journal-safe restrictions, blocked-state messaging, detail-page actions, and controller coverage for the new lifecycle rules.

---

## P0-05 — Reduce CompanySettings mismatch against live consumers
- Priority: P0
- Status: DONE
- Area: Company Settings
- Problem:
  The settings UI exposes more categories and fields than the codebase clearly uses.
- Why:
  The current UI implies product capability that is not fully confirmed in live consumers.
- Scope:
  - inspect each category and field against real backend readers
  - hide inactive sections or clearly label them
  - improve field descriptions and help text
  - separate clearly active settings from future/placeholder ones
  - preserve invoice settings as their own source of truth
- Constraints:
  - do not remove actively used settings
  - do not assume stored fields are live features
- Primary Entry Points:
  - `plugins/khatabook/app/src/pages/CompanySettings.jsx`
  - `plugins/khatabook/backend/Api/SettingsController.php`
  - `plugins/khatabook/backend/Helpers/ReportHelper.php`
- Acceptance Criteria:
  - settings UI reflects actual product capability more honestly
  - inactive or uncertain settings are gated, hidden, or clearly labeled
  - save behavior remains safe and predictable
- Notes:
  Completed on 2026-04-03. `CompanySettings` now renders only the `company`, `sales`, and `tax` sections with confirmed live readers, includes the report-backed `income_tax_rate` field, and no longer rewrites hidden placeholder categories during “Save All”.

---

# P1 — Product Quality and Operational Strengthening

## P1-01 — Add record-level audit trail for invoices, expenses, and payments
- Priority: P1
- Status: DONE
- Area: Auditability
- Problem:
  Record-level change history is missing for the most important business records.
- Why:
  Financial operations need traceability, reason logging, and visible history.
- Scope:
  - change history model
  - who/when/what changed
  - before/after snapshots where appropriate
  - reason capture for sensitive actions
  - invoice history surface
  - expense history surface
  - payment history surface
- Constraints:
  - keep the implementation grounded in current modules
  - do not create an overly abstract enterprise system
- Primary Entry Points:
  - `plugins/khatabook/backend/Core/SystemLogger.php`
  - `plugins/khatabook/backend/Api/VyRestInvoices.php`
  - `plugins/khatabook/backend/Api/VyRestExpenses.php`
  - `plugins/khatabook/app/src/modules/invoices/*`
  - `plugins/khatabook/app/src/modules/expenses/*`
- Acceptance Criteria:
  - key record changes are visible and attributable
  - history is readable in detail views
  - protected actions can record reason metadata
- Notes:
  Completed on 2026-04-03. The product now writes record-level history to `vy_record_history`, surfaces invoice/expense activity in detail views, and includes invoice payment events through related history rows.

---

## P1-02 — Build clearer payment activity surface
- Priority: P1
- Status: DONE
- Area: Payments UX
- Problem:
  Payment posting exists, but payment visibility is too narrow operationally.
- Why:
  Users need to review payment activity without opening individual invoices one by one.
- Scope:
  - payment activity list or panel
  - filter by date/customer/invoice/payment mode
  - summary of recent payment activity
  - improved payment visibility in dashboard and/or dedicated surface
- Constraints:
  - stay grounded in current invoice payment model
  - do not invent a disconnected payments subsystem
- Primary Entry Points:
  - `plugins/khatabook/backend/Api/VyRestInvoices.php`
  - `plugins/khatabook/backend/Api/VyRestAccounts.php`
  - `plugins/khatabook/app/src/modules/invoices/InvoiceDetail.jsx`
  - `plugins/khatabook/app/src/pages/Home.jsx`
- Acceptance Criteria:
  - users can review payment activity in a first-class way
  - filtering and basic operational visibility are supported
- Notes:
  Completed on 2026-04-03. The SPA now has a dedicated `/payments` route backed by `GET /vy/v1/payments`, Home shows recent payments, and each payment entry links back to its invoice and receiving account.

---

## P1-03 — Expand reports using current `vy_*` journal and document data
- Priority: P1
- Status: IMPROVE
- Area: Reports
- Problem:
  Reports exist, but are narrow compared to current business needs.
- Why:
  The app already has enough data to support stronger operational reporting.
- Scope:
  - receivables report
  - payables report where practical
  - aging report
  - monthly revenue trend
  - monthly expense trend
  - customer balances
  - top customer summaries
  - invoice status summaries
- Constraints:
  - use current live data only
  - do not invent unsupported analytics
- Primary Entry Points:
  - `plugins/khatabook/backend/Api/VyRestReports.php`
  - `plugins/khatabook/backend/Helpers/ReportHelper.php`
  - `plugins/khatabook/app/src/modules/reports/*`
- Acceptance Criteria:
  - at least several new reports or report views are added
  - reports remain consistent with current org and accounting logic
  - empty and loading states are handled cleanly

---

## P1-04 — Consolidate operational logging
- Priority: P1
- Status: IMPROVE
- Area: Observability
- Problem:
  Logs are split between structured DB logging and raw `error_log()` usage.
- Why:
  Debugging and operational support are harder than they should be.
- Scope:
  - review invoice, expense, settings, and email flow logging
  - standardize meaningful operational events
  - reduce unnecessary raw logging where practical
  - improve debugging consistency
- Constraints:
  - do not expose sensitive payloads
  - preserve useful troubleshooting information
- Primary Entry Points:
  - `plugins/khatabook/backend/Core/SystemLogger.php`
  - `plugins/khatabook/backend/Email/EmailManager.php`
  - `plugins/khatabook/backend/Api/VyRestInvoices.php`
  - `plugins/khatabook/backend/Api/VyRestExpenses.php`
  - `plugins/khatabook/backend/Api/SettingsController.php`
- Acceptance Criteria:
  - logging is clearer and more consistent
  - common operational failures are easier to trace

---

## P1-05 — Add transactional safety or compensating behavior for multi-step writes
- Priority: P1
- Status: IMPROVE
- Area: Data Integrity
- Problem:
  Multi-table business flows currently lack explicit transaction boundaries.
- Why:
  Partial failures can leave inconsistent state.
- Scope:
  - identify highest-risk multi-step flows
  - add explicit transaction handling or compensating cleanup where feasible
  - prioritize invoice, expense, approval, and org-user flows
- Constraints:
  - keep changes small and targeted
  - do not rewrite persistence architecture broadly
- Primary Entry Points:
  - `plugins/khatabook/backend/Api/VyRestInvoices.php`
  - `plugins/khatabook/backend/Api/VyRestExpenses.php`
  - `plugins/khatabook/backend/Admin/PendingUserController.php`
  - `plugins/khatabook/backend/Api/OrgUsersController.php`
- Acceptance Criteria:
  - highest-risk multi-step writes are safer against partial failure
  - failure behavior is more explicit and documented

---

## P1-06 — Tighten operational admin flows
- Priority: P1
- Status: IMPROVE
- Area: Admin Tooling
- Problem:
  Pending users and logs are functional, but split and somewhat thin operationally.
- Why:
  Admin-facing business support work should be safer and clearer.
- Scope:
  - pending user flow clarity
  - better log readability
  - improved admin-side messaging and consistency
- Constraints:
  - keep admin utility pragmatic
  - do not redesign wp-admin just for visuals
- Primary Entry Points:
  - `plugins/khatabook/backend/Admin/*`
  - `plugins/khatabook/backend/Api/AdminData.php`
  - `plugins/khatabook/app/src/pages/UsersAdmin.jsx`
- Acceptance Criteria:
  - admin support flows are easier to operate
  - no unsupported or misleading controls are exposed

---

# P2 — Structural Cleanup and Controlled Expansion

## P2-01 — Extract duplicated frontend async/query helpers
- Priority: P2
- Status: IMPROVE
- Area: Frontend Foundations
- Problem:
  Repeated `useAsync` and `buildQuery` logic exists across modules.
- Why:
  Duplication increases maintenance cost and behavioral drift.
- Scope:
  - extract shared async helper
  - extract shared query-builder helper
  - update accounts, invoices, expenses, and future contacts usage
- Constraints:
  - preserve current behavior
  - avoid introducing a new state-management system
- Primary Entry Points:
  - `plugins/khatabook/app/src/modules/accounts/hooks.js`
  - `plugins/khatabook/app/src/modules/invoices/hooks.js`
  - `plugins/khatabook/app/src/modules/expenses/hooks.js`
  - `plugins/khatabook/app/src/modules/*/api.js`
- Acceptance Criteria:
  - duplicated helper behavior is reduced
  - modules continue to behave the same from the user’s perspective

---

## P2-02 — Normalize frontend list/detail/form patterns
- Priority: P2
- Status: IMPROVE
- Area: Frontend Consistency
- Problem:
  Business pages mix patterns in ways that reduce consistency.
- Why:
  The product needs stronger operational consistency more than a visual redesign.
- Scope:
  - page header consistency
  - filter/search row consistency
  - table/list consistency
  - detail action bar consistency
  - modal-vs-page discipline
  - empty/loading/error pattern consistency
- Constraints:
  - no broad redesign
  - preserve current UI tone
- Primary Entry Points:
  - `plugins/khatabook/app/src/modules/accounts/*`
  - `plugins/khatabook/app/src/modules/invoices/*`
  - `plugins/khatabook/app/src/modules/expenses/*`
  - `plugins/khatabook/app/src/pages/UsersAdmin.jsx`
  - `plugins/khatabook/app/src/pages/CompanySettings.jsx`
- Acceptance Criteria:
  - major business pages feel more consistent
  - patterns align better with `AI_UX_DOCTRINE.md`

---

## P2-03 — Add company media handling only for real consumers
- Priority: P2
- Status: TODO
- Area: Company Media
- Problem:
  Invoice logo upload exists, but broader company assets are unclear or URL-first.
- Why:
  Media handling should only be improved where there is a confirmed live consumer.
- Scope:
  - inspect signature/seal/document asset usage
  - add upload handling only where current output actually uses those assets
- Constraints:
  - do not add decorative media settings with no real consumer
- Primary Entry Points:
  - `plugins/khatabook/app/src/pages/CompanySettings.jsx`
  - `plugins/khatabook/backend/Api/SettingsController.php`
  - `plugins/khatabook/backend/Api/VyRestInvoiceSettings.php`
- Acceptance Criteria:
  - upload flows exist only for actually-used assets
  - UI does not imply unsupported document rendering features

---

# P2 — Product Expansion After Current Queue

## P2-04 — Customer statements
- Priority: P2
- Status: TODO
- Area: Customer Management / Billing
- Problem:
  The broader product vision expects customer statement visibility, but the current product does not surface this as a first-class workflow.
- Why:
  Statements are a natural next step after contacts, invoices, and payments are solid.
- Scope:
  - customer outstanding summary
  - statement view or export-ready summary
  - links from contacts/customer surfaces
- Constraints:
  - stay grounded in current invoice/payment data
- Acceptance Criteria:
  - users can review a customer statement without manual reconstruction

---

## P2-05 — Recurring billing
- Priority: P2
- Status: TODO
- Area: Billing Extensions
- Problem:
  Recurring billing is part of product vision but not active in the current repo.
- Why:
  Strong business value after the current billing core is stabilized.
- Scope:
  - recurring invoice model
  - generation rules
  - start/end dates
  - next run visibility
  - safety around invoice numbering and edit rules
- Constraints:
  - build only after core current queue is stable
- Acceptance Criteria:
  - recurring invoicing works with current invoice model and org safety

---

## P2-06 — Credit notes and debit notes
- Priority: P2
- Status: TODO
- Area: Billing Extensions
- Problem:
  The product vision includes credit/debit adjustments, but the current live module does not.
- Why:
  Important business completeness feature after core invoice and expense maturity.
- Scope:
  - credit note model
  - debit note model
  - link to invoice
  - auditability
  - adjustment behavior
- Constraints:
  - preserve accounting integrity
- Acceptance Criteria:
  - invoice-linked credit/debit adjustments are safely supported

---

## P2-07 — Promise-to-pay tracking
- Priority: P2
- Status: TODO
- Area: Collections
- Problem:
  Collections workflows in the product vision require operational tracking of customer payment promises.
- Why:
  Strong practical value after payment visibility is improved.
- Scope:
  - promised date
  - promised amount
  - promise notes
  - promise reliability tracking
  - contact/invoice linkage
- Constraints:
  - add only after payment visibility base exists
- Acceptance Criteria:
  - users can track customer payment promises operationally

---

## P2-08 — Vendor bills / purchase-bill workflow
- Priority: P2
- Status: TODO
- Area: Purchases / Payables
- Problem:
  Expenses exist, but vendor-bill style workflows are not yet a first-class product path.
- Why:
  Strong natural extension once expense lifecycle is complete.
- Scope:
  - vendor bill data model direction
  - due tracking
  - payable visibility
  - link with vendors and payments
- Constraints:
  - align with current expense and journal model
- Acceptance Criteria:
  - vendor bill workflows are supported without duplicating the expense system blindly

---

# P3 — Advanced Roadmap and Differentiators

## P3-01 — OCR bill extraction
- Priority: P3
- Status: TODO
- Area: Smart Expense Workflows
- Problem:
  OCR-based bill ingestion is part of product vision but not current active code.
- Why:
  High-value advanced feature after expense base is solid.
- Scope:
  - upload image/PDF
  - field extraction
  - confidence score
  - verification/edit step
  - create expense/vendor bill from result
- Constraints:
  - do not start before core expense workflows are mature
- Acceptance Criteria:
  - OCR ingestion produces a safe user-reviewed draft flow

---

## P3-02 — Billing health score
- Priority: P3
- Status: TODO
- Area: Intelligence / Dashboard
- Problem:
  Product vision calls for a business health layer, but the current dashboard is still an operational baseline rather than an intelligence layer.
- Why:
  Strong differentiator after operational data surfaces exist.
- Scope:
  - define score inputs
  - dashboard widget
  - reasons behind score
  - trend over time
- Constraints:
  - base this only on real tracked data
- Acceptance Criteria:
  - health score is transparent and explainable, not decorative

---

## P3-03 — Owner daily brief
- Priority: P3
- Status: TODO
- Area: Intelligence / Notifications
- Problem:
  Owner-focused daily summaries are part of product vision but require stronger dashboards and data surfaces first.
- Why:
  Good differentiator after operational reporting is mature.
- Scope:
  - collections yesterday
  - invoices created
  - expenses added
  - overdue actions needed
  - predicted cash-in summary
- Constraints:
  - only use confirmed current/live metrics
- Acceptance Criteria:
  - summary is accurate, useful, and operational

---

## P3-04 — Invoice risk engine
- Priority: P3
- Status: TODO
- Area: Billing Intelligence
- Problem:
  Product vision wants proactive invoice checks before send/finalization.
- Why:
  Useful advanced safety layer after audit and reporting maturity.
- Scope:
  - missing detail checks
  - abnormal amount checks
  - discount anomaly checks
  - tax mismatch checks
- Constraints:
  - use explainable rule-based logic first
- Acceptance Criteria:
  - users see useful pre-send risk flags grounded in real data

---

## P3-05 — Revenue leak detector
- Priority: P3
- Status: TODO
- Area: Business Intelligence
- Problem:
  Product vision includes leak detection, but the base operational layer must exist first.
- Why:
  Strong differentiator after dashboard, reports, and customer/payment visibility improve.
- Scope:
  - missed recurring invoicing
  - unadjusted payments
  - discount leakage
  - unpaid/forgotten invoice opportunities
- Constraints:
  - do not introduce fake precision
- Acceptance Criteria:
  - users get grounded, explainable leak warnings

---

## P3-06 — AI invoice assistant
- Priority: P3
- Status: TODO
- Area: AI / Billing
- Problem:
  AI-assisted invoice drafting is aspirational right now, not an immediate current-repo need.
- Why:
  Valuable later once baseline invoice operations are fully stable.
- Scope:
  - prompt-to-draft invoice
  - suggested descriptions
  - suggested terms or due date
- Constraints:
  - only after core operational quality is strong
- Acceptance Criteria:
  - assistant improves speed without weakening data correctness

---

# Blocked Decisions

## B-01 — Quotations direction
- Priority: P0
- Status: BLOCKED
- Area: Quotations
- Problem:
  Quotations are part of the wider product vision, but no active quotation module exists in the inspected repo.
- Why Blocked:
  Only legacy quotation schema was found. No active route or SPA module is currently wired.
- Decision Needed:
  - keep quotations legacy-only
  - rebuild quotations on `vy_*`
  - postpone quotations from near-term scope
- Evidence:
  - `plugins/khatabook/backend/Db/TableManager.php`
- Notes:
  Do not build quotations until this is explicitly decided.

---

## B-02 — Broad CompanySettings direction
- Priority: P0
- Status: BLOCKED
- Area: Settings Scope
- Problem:
  The settings UI is broader than the clearly active product behavior.
- Why Blocked:
  Some categories may be roadmap placeholders rather than live product surfaces.
- Decision Needed:
  - keep broad settings as roadmap surfaces
  - trim them to live capabilities
  - explicitly separate active vs future sections
- Evidence:
  - `plugins/khatabook/app/src/pages/CompanySettings.jsx`
  - `plugins/khatabook/backend/Api/SettingsController.php`

---

# Future Vision Reference — Do Not Treat As Immediate Queue

These are part of the broader platform vision but should not jump ahead of the current execution queue:

- inventory workflows
- webhook/integration framework
- two-factor auth
- customer portal
- reconciliation engine
- collection copilot
- next best action engine
- recovery mode
- full AI business assistant
- advanced anomaly detection
- broad workflow collaboration system
- approval chains across all modules
- branch-level deep analytics

These can be promoted later after:
- testing hardening
- settings cleanup
- audit trail
- payment visibility
- stronger reporting

---

# Recommended Execution Order

1. P0-05 — company settings cleanup
2. P1-01 — audit trail
3. P1-02 — payment activity surface
4. P1-03 — reports expansion
5. P1-04 — logging consolidation
6. P1-05 — transactional safety
11. P1-06 — admin tooling improvement
12. P2 structural cleanup items
13. P2 expansion items
14. P3 advanced intelligence items
15. blocked items only after explicit decisions
