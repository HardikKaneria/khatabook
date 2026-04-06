# AI Changelog

Base path: `/Users/hardikkaneria/Local Sites/khatabook/app/public/wp-content`

This file is the running execution history for meaningful Codex work in this repository.

## How To Use

Future Codex runs should append a new dated entry after meaningful completed work.

Each entry should include:

- date
- task summary
- why the change was made
- files changed
- validation performed
- workflow files updated
- remaining risks or follow-up items

## Entry Template

```md
## YYYY-MM-DD - Short Title

### Summary
- ...

### Files Changed
- ...

### Validation
- ...

### Workflow Updates
- ...

### Remaining Risks / Follow-Up
- ...
```

## 2026-04-06 - Recurring Billing, Invoice Adjustments, And Promise Tracking

### Summary
- Completed the next three safe execution tasks in order: recurring billing on live `vy_*` invoices, invoice-linked credit/debit notes, and invoice promise-to-pay tracking.
- Kept all three features inside the existing invoice architecture by extending `VyRestInvoices.php`, adding narrowly scoped `vy_*` support tables, and reusing existing org, audit, reporting, payment, and invoice-rendering paths instead of building parallel subsystems.
- Extended the current SPA invoice and payments surfaces so the new capabilities are usable end-to-end without introducing fake screens or route drift.

### Files Changed
- `backend/Api/VyRestInvoices.php`
- `backend/Db/TableManager.php`
- `backend/Helpers/InvoiceFinancialHelper.php`
- `backend/Helpers/InvoiceEditHelper.php`
- `backend/Helpers/ReportHelper.php`
- `backend/Core/Plugin.php`
- `main.php`
- `app/src/modules/invoices/api.js`
- `app/src/modules/invoices/hooks.js`
- `app/src/modules/invoices/InvoicesPage.jsx`
- `app/src/modules/invoices/InvoiceList.jsx`
- `app/src/modules/invoices/InvoiceDetailPage.jsx`
- `app/src/modules/invoices/InvoiceDetail.jsx`
- `app/src/modules/invoices/InvoicePaymentForm.jsx`
- `app/src/modules/invoices/RecurringProfileForm.jsx`
- `app/src/modules/invoices/RecurringProfilesList.jsx`
- `app/src/modules/invoices/InvoiceNoteForm.jsx`
- `app/src/modules/invoices/InvoicePromiseForm.jsx`
- `app/src/modules/payments/api.js`
- `app/src/modules/payments/PaymentsPage.jsx`
- `app/src/modules/contacts/ContactStatementPanel.jsx`
- `tests/bootstrap.php`
- `tests/TestWpEnvironment.php`
- `tests/InvoiceLifecycleExtensionsTest.php`
- `ai-workflow/AI_MASTER_BRIEF.md`
- `ai-workflow/AI_FILE_MAP.md`
- `ai-workflow/AI_FEATURE_BACKLOG.md`
- `ai-workflow/AI_NEXT_ACTIONS.md`
- `ai-workflow/AI_TECH_DEBT.md`
- `ai-workflow/AI_CHANGELOG.md`

### Validation
- `php -l` passed on:
  - `backend/Api/VyRestInvoices.php`
  - `backend/Db/TableManager.php`
  - `backend/Helpers/InvoiceFinancialHelper.php`
  - `backend/Helpers/ReportHelper.php`
  - `backend/Helpers/InvoiceEditHelper.php`
  - `backend/Core/Plugin.php`
  - `tests/bootstrap.php`
  - `tests/TestWpEnvironment.php`
  - `tests/InvoiceLifecycleExtensionsTest.php`
- `composer test` in `plugins/khatabook` passed with `36 passed, 0 failed`
- `npm run build` in `plugins/khatabook/app` passed

### Workflow Updates
- Marked `P2-05`, `P2-06`, and `P2-07` complete in `AI_FEATURE_BACKLOG.md`.
- Added the next safe follow-up queue: vendor bills, Home receivables summary alignment, and org member/invite rollback discipline.
- Updated the master brief and file map so future runs treat recurring billing, invoice adjustments, and promise tracking as part of the active invoice module rather than future roadmap work.

### Remaining Risks / Follow-Up
- The payables side is still the biggest product gap: vendor bills and later settlement for unpaid expenses are not live yet.
- `Home.jsx` still relies on the latest-100-open-invoices shortcut until the dashboard is switched to the server-side receivables summary.
- `OrgUsersController.php` still does not have the same rollback discipline as the core financial controllers.

## 2026-04-06 - Route-Level Loading, Company Logo Handling, And Customer Statements

### Summary
- Completed the next three safe execution tasks in order: reduced frontend bundle cost with route-level lazy loading and deterministic chunking, added company-logo media handling only for the confirmed live consumer path, and shipped customer statements on live contact/invoice/payment data.
- Kept the implementation inside the current plugin architecture by preserving the manual router, reusing the existing settings system, extending `ReportHelper.php` rather than inventing a new statement model, and using WordPress media APIs for uploads.
- Added controller-level automated coverage for the new customer-statement endpoint and adjusted the lightweight test environment to match the active report queries.

### Files Changed
- `app/src/App.jsx`
- `app/src/components/ui/RouteLoadingState.jsx`
- `app/vite.config.js`
- `app/src/pages/CompanySettings.jsx`
- `app/src/components/settings/CompanyLogoUploader.jsx`
- `app/src/modules/contacts/api.js`
- `app/src/modules/contacts/ContactsList.jsx`
- `app/src/modules/contacts/ContactsPage.jsx`
- `app/src/modules/contacts/ContactStatementPanel.jsx`
- `backend/Api/SettingsController.php`
- `backend/Api/VyRestContacts.php`
- `backend/Api/VyRestInvoiceSettings.php`
- `backend/Endpoint/EndpointManager.php`
- `backend/Helpers/ReportHelper.php`
- `backend/Media/ManagedImageUpload.php`
- `tests/ContactControllerTest.php`
- `tests/TestWpEnvironment.php`
- `ai-workflow/AI_MASTER_BRIEF.md`
- `ai-workflow/AI_FILE_MAP.md`
- `ai-workflow/AI_FEATURE_BACKLOG.md`
- `ai-workflow/AI_NEXT_ACTIONS.md`
- `ai-workflow/AI_TECH_DEBT.md`
- `ai-workflow/AI_CHANGELOG.md`

### Validation
- `php -l` passed on:
  - `backend/Api/SettingsController.php`
  - `backend/Api/VyRestContacts.php`
  - `backend/Api/VyRestInvoiceSettings.php`
  - `backend/Helpers/ReportHelper.php`
  - `backend/Media/ManagedImageUpload.php`
  - `backend/Endpoint/EndpointManager.php`
  - `tests/ContactControllerTest.php`
- `composer test` in `plugins/khatabook` passed with `33 passed, 0 failed`
- `npm run build` in `plugins/khatabook/app` passed
- Verified the frontend production build now emits route/module chunks instead of the previous oversized main-bundle warning

### Workflow Updates
- Marked `P2-02A`, `P2-03`, and `P2-04` complete.
- Promoted recurring billing to the next active execution task, followed by credit/debit notes and promise-to-pay tracking.
- Updated the tech-debt notes to remove the resolved bundle warning and document the still-duplicated settings route registration path.
- Updated the file map and master brief so future runs can find the new route-loading, company-logo, and customer-statement paths quickly.

### Remaining Risks / Follow-Up
- Company-logo upload currently exists only on `CompanySettings.jsx`, with invoice settings still taking precedence for document branding. That precedence is correct but should stay explicit in future settings work.
- Customer statements currently surface as a contacts-modal workflow rather than a printable/export route.
- `SettingsController.php` still contains an internal route-registration map alongside the active `EndpointManager.php` registration path, which remains an avoidable source of drift for future runs.

## 2026-04-06 - Admin Flow Tightening, Shared Frontend Helpers, And UI Pattern Normalization

### Summary
- Completed the next three safe execution tasks in order: tightened operational admin flows, extracted shared frontend async/query helpers, and normalized active list/detail/form state treatment across the current live modules.
- Kept the implementation inside the existing plugin architecture by reusing `PendingUserController`, `AdminData`, existing SPA module boundaries, and shared UI primitives instead of introducing new frameworks or alternate data paths.
- Updated both wp-admin and the SPA users screen so support-oriented operations are clearer without changing the underlying permission model or org safety rules.

### Files Changed

- `backend/Admin/AdminPage.php`
- `backend/Admin/PendingUserController.php`
- `backend/Admin/views/pending-users.php`
- `backend/Admin/views/registration-logs.php`
- `backend/Admin/views/system-logs.php`
- `backend/Admin/views/otp-attempts.php`
- `assets/css/admin-style.css`
- `app/src/hooks/useAsyncResource.js`
- `app/src/utils/buildQuery.js`
- `app/src/components/ui/FeedbackState.jsx`
- `app/src/components/ui/InlineNotice.jsx`
- `app/src/components/ui/RecordHistoryCard.jsx`
- `app/src/pages/UsersAdmin.jsx`
- `app/src/modules/accounts/api.js`
- `app/src/modules/accounts/hooks.js`
- `app/src/modules/accounts/AccountsPage.jsx`
- `app/src/modules/accounts/AccountDetailPage.jsx`
- `app/src/modules/accounts/AccountStatementTable.jsx`
- `app/src/modules/accounts/MoneyAccountsList.jsx`
- `app/src/modules/accounts/TransferForm.jsx`
- `app/src/modules/contacts/api.js`
- `app/src/modules/contacts/hooks.js`
- `app/src/modules/contacts/ContactsList.jsx`
- `app/src/modules/contacts/ContactsPage.jsx`
- `app/src/modules/expenses/api.js`
- `app/src/modules/expenses/hooks.js`
- `app/src/modules/expenses/ExpensesList.jsx`
- `app/src/modules/expenses/ExpensesPage.jsx`
- `app/src/modules/expenses/ExpenseDetailPage.jsx`
- `app/src/modules/invoices/api.js`
- `app/src/modules/invoices/hooks.js`
- `app/src/modules/invoices/InvoiceList.jsx`
- `app/src/modules/invoices/InvoicesPage.jsx`
- `app/src/modules/invoices/InvoiceDetailPage.jsx`
- `app/src/modules/payments/api.js`
- `app/src/modules/payments/PaymentsPage.jsx`
- `app/src/modules/reports/api.js`
- `app/src/theme.css`
- `ai-workflow/AI_MASTER_BRIEF.md`
- `ai-workflow/AI_FILE_MAP.md`
- `ai-workflow/AI_FEATURE_BACKLOG.md`
- `ai-workflow/AI_NEXT_ACTIONS.md`
- `ai-workflow/AI_TECH_DEBT.md`
- `ai-workflow/AI_CHANGELOG.md`

### Validation

- `php -l` passed on:
  - `backend/Admin/AdminPage.php`
  - `backend/Admin/PendingUserController.php`
  - `backend/Admin/views/pending-users.php`
  - `backend/Admin/views/registration-logs.php`
  - `backend/Admin/views/system-logs.php`
  - `backend/Admin/views/otp-attempts.php`
- `composer test` in `plugins/khatabook` passed with `31 passed, 0 failed`
- `npm run build` in `plugins/khatabook/app` passed
- Verified there are no remaining duplicated `const useAsync =` or `const buildQuery =` definitions in the active module API/hook files

### Workflow Updates

- Marked `P1-06`, `P2-01`, and `P2-02` complete.
- Added the large-bundle / route-level loading task as the next structural cleanup item.
- Moved the next active execution order to bundle splitting, then company media handling for real consumers, then customer statements.
- Updated the file map and master brief so future runs can find the new shared frontend foundations and the wp-admin support path more quickly.

### Remaining Risks / Follow-Up

- The frontend production build still emits a large main-bundle warning and should be addressed before more heavy operational screens are added.
- `CompanySettings.jsx`, `Home.jsx`, and `ProfitTaxPage.jsx` still use more bespoke fetch/layout behavior than the now-shared module primitives.
- Org-user invite/member flows in `backend/Api/OrgUsersController.php` still do not have the same rollback discipline as the core financial controllers.

## 2026-04-06 - Reports Expansion, Operational Logging, And Financial Write Safety

### Summary
- Completed the next three safe execution tasks in order: expanded reporting on live `vy_*` data, consolidated operational error logging into `kbs_system_logs`, and hardened the main multi-step financial write paths against partial failure.
- Kept the implementation inside the existing plugin architecture by extending `VyRestReports`, `ReportHelper`, `SystemLogger`, `VyJournalEngine`, and the existing invoice/expense controllers rather than introducing new subsystems.
- Extended the current PHP harness so the new report endpoints, rollback behavior, and logger path are executable and not just code-reviewed.

### Files Changed

- `app/src/modules/reports/ProfitTaxPage.jsx`
- `app/src/modules/reports/api.js`
- `app/src/theme.css`
- `backend/Accounting/VyJournalEngine.php`
- `backend/Admin/PendingUserController.php`
- `backend/Api/SettingsController.php`
- `backend/Api/VyRestExpenses.php`
- `backend/Api/VyRestInvoices.php`
- `backend/Api/VyRestReports.php`
- `backend/Core/SystemLogger.php`
- `backend/Email/EmailManager.php`
- `backend/Helpers/InvoiceRenderHelper.php`
- `backend/Helpers/ReportHelper.php`
- `backend/Notifications/InternalDocumentNotifier.php`
- `tests/bootstrap.php`
- `tests/TestWpEnvironment.php`
- `tests/ExpenseControllerTest.php`
- `tests/InvoiceControllerTest.php`
- `tests/ReportsControllerTest.php`
- `tests/SystemLoggerTest.php`
- `ai-workflow/AI_MASTER_BRIEF.md`
- `ai-workflow/AI_FILE_MAP.md`
- `ai-workflow/AI_FEATURE_BACKLOG.md`
- `ai-workflow/AI_NEXT_ACTIONS.md`
- `ai-workflow/AI_TECH_DEBT.md`
- `ai-workflow/AI_CHANGELOG.md`

### Validation

- `php -l` passed on the changed PHP files, including:
  - `backend/Helpers/ReportHelper.php`
  - `backend/Api/VyRestReports.php`
  - `backend/Core/SystemLogger.php`
  - `backend/Api/VyRestInvoices.php`
  - `backend/Api/VyRestExpenses.php`
  - `backend/Accounting/VyJournalEngine.php`
- `composer test` in `plugins/khatabook` passed with `31 passed, 0 failed`
- `npm run build` in `plugins/khatabook/app` passed

### Workflow Updates

- Marked report expansion complete and moved the new report surfaces into the active baseline.
- Marked operational logging consolidation complete and narrowed remaining raw-log debt to frontend boot and logger fallback paths.
- Marked the highest-risk financial write-safety task complete and narrowed remaining write-safety debt to non-financial org/admin flows.
- Promoted operational admin flows to the next active execution task.

### Remaining Risks / Follow-Up

- `Home.jsx` still computes receivables from the latest 100 `SENT` and `PARTIAL` invoices instead of reusing the new server-side receivables summary.
- The current expense module still does not include a later settlement flow for unpaid expenses.
- Non-financial org/admin multi-step flows in `OrgUsersController.php` still do not have the same rollback discipline as the core invoice/expense/payment paths.
- The frontend build still emits a large-bundle warning and should be handled as a later performance task, not mixed into operational work.

## 2026-04-03 - Settings Truthfulness, Record History, And Payments Visibility

### Summary
- Completed the next three safe execution tasks in order: narrowed `CompanySettings` to the settings categories with confirmed live consumers, added record-level history for invoices/expenses/payments, and shipped a first-class payment activity surface.
- Kept the changes inside the current plugin architecture by reusing `kbs_settings`, `vy_invoice_payments`, `vy_journal_lines`, and the existing SPA shell rather than inventing a new settings or payments subsystem.
- Extended the current PHP harness so the new audit and payment list behavior is covered at the controller layer.

### Files Changed

- `app/src/App.jsx`
- `app/src/layouts/DashboardLayout.jsx`
- `app/src/pages/CompanySettings.jsx`
- `app/src/pages/Home.jsx`
- `app/src/components/ui/RecordHistoryCard.jsx`
- `app/src/modules/expenses/ExpenseDetail.jsx`
- `app/src/modules/invoices/InvoiceDetail.jsx`
- `app/src/modules/payments/api.js`
- `app/src/modules/payments/PaymentsPage.jsx`
- `app/src/theme.css`
- `backend/Api/SettingsController.php`
- `backend/Api/VyRestInvoices.php`
- `backend/Api/VyRestExpenses.php`
- `backend/Core/RecordAuditLogger.php`
- `backend/Db/TableManager.php`
- `tests/TestWpEnvironment.php`
- `tests/InvoiceControllerTest.php`
- `tests/ExpenseControllerTest.php`
- `ai-workflow/AI_MASTER_BRIEF.md`
- `ai-workflow/AI_FILE_MAP.md`
- `ai-workflow/AI_FEATURE_BACKLOG.md`
- `ai-workflow/AI_NEXT_ACTIONS.md`
- `ai-workflow/AI_TECH_DEBT.md`
- `ai-workflow/AI_CHANGELOG.md`

### Validation

- `php -l` passed on:
  - `plugins/khatabook/backend/Core/RecordAuditLogger.php`
  - `plugins/khatabook/backend/Db/TableManager.php`
  - `plugins/khatabook/backend/Api/SettingsController.php`
  - `plugins/khatabook/backend/Api/VyRestInvoices.php`
  - `plugins/khatabook/backend/Api/VyRestExpenses.php`
- `composer test` in `plugins/khatabook` passed with `25 passed, 0 failed`
- `npm run build` in `plugins/khatabook/app` passed

### Workflow Updates

- Marked the `CompanySettings` truthfulness task complete.
- Marked record-level audit history and payment visibility work complete.
- Promoted report expansion to the next active product task.
- Updated architecture and tech-debt docs so the live baseline now includes payment history surfaces and the new `vy_record_history` audit table.

### Remaining Risks / Follow-Up

- Dashboard receivables still rely on the latest 100 `SENT` invoices and latest 100 `PARTIAL` invoices, so high-volume orgs can still undercount on Home.
- Expense records still do not have a later “record payment” flow after initial creation.
- The frontend build still produces a large main bundle warning and should be treated as a separate performance task.

## 2026-04-03 - Workflow Control System Initialized

### Summary
- Inspected the current `plugins/khatabook` codebase and established the first `/ai-workflow` control layer for ongoing autonomous work.
- Documented the active architecture, tech debt, current backlog, and recommended execution order.
- Added the master brief and system rules so future runs can stay aligned with the actual product and code patterns.

### Files Changed

- `ai-workflow/AI_FILE_MAP.md`
- `ai-workflow/AI_TECH_DEBT.md`
- `ai-workflow/AI_FEATURE_BACKLOG.md`
- `ai-workflow/AI_NEXT_ACTIONS.md`
- `ai-workflow/AI_MASTER_BRIEF.md`
- `ai-workflow/AI_SYSTEM_RULES.md`
- `ai-workflow/AI_CHANGELOG.md`

### Validation

- Based on direct inspection of the current plugin code under:
  - `plugins/khatabook/main.php`
  - `plugins/khatabook/backend`
  - `plugins/khatabook/app/src`
  - `plugins/khatabook/tests`
- No product code behavior was changed in this workflow-initialization step.

### Workflow Updates

- Initialized the master project brief.
- Initialized run rules for future Codex execution.
- Refined backlog and next-run recommendations to match the inspected codebase.

### Remaining Risks / Follow-Up

- The workflow docs rely on the current inspected code state and should be updated after major feature work.
- Quotations and several `CompanySettings` categories remain decision-sensitive areas and should not be assumed to be active product modules.

## 2026-04-03 - Workflow System Upgrade

### Summary
- Re-read the current `/ai-workflow` files and re-inspected the live plugin code before tightening the workflow system.
- Upgraded the workflow layer to give future Codex runs stronger product judgment, stricter execution rules, better UX guidance, and clearer implementation standards.
- Added dedicated UX and implementation doctrine files so future work can stay aligned with the real operational product rather than generic SaaS assumptions.

### Files Changed

- `ai-workflow/AI_MASTER_BRIEF.md`
- `ai-workflow/AI_SYSTEM_RULES.md`
- `ai-workflow/AI_FEATURE_BACKLOG.md`
- `ai-workflow/AI_NEXT_ACTIONS.md`
- `ai-workflow/AI_UX_DOCTRINE.md`
- `ai-workflow/AI_IMPLEMENTATION_PLAYBOOK.md`
- `ai-workflow/AI_TECH_DEBT.md`
- `ai-workflow/AI_CHANGELOG.md`

### Validation

- Re-read all existing workflow files in `ai-workflow/`
- Re-inspected current code paths including:
  - `plugins/khatabook/backend/Endpoint/EndpointManager.php`
  - `plugins/khatabook/backend/Db/TableManager.php`
  - `plugins/khatabook/app/src/layouts/DashboardLayout.jsx`
  - `plugins/khatabook/app/src/pages/CompanySettings.jsx`
  - `plugins/khatabook/app/src/pages/UsersAdmin.jsx`
  - `plugins/khatabook/app/src/modules/invoices/InvoiceDetailPage.jsx`
  - `plugins/khatabook/app/src/modules/expenses/ExpensesPage.jsx`
  - `plugins/khatabook/app/src/modules/settings/invoices/InvoiceSettingsPage.jsx`
- No product runtime code was changed in this workflow upgrade.

### Workflow Updates

- Tightened the master brief around real module briefs and non-negotiable rules.
- Reorganized the backlog into execution queue, module improvements, structural cleanup, blocked decisions, and future roadmap.
- Rewrote next actions into a direct next-run playbook.
- Added a dedicated UX doctrine and implementation playbook.
- Refined tech debt to stay code-backed and repository-specific.

### Remaining Risks / Follow-Up

- The workflow layer is only useful if future runs keep it updated after meaningful changes.
- `CompanySettings` scope and quotation direction remain decision-sensitive and should still be treated carefully.

## 2026-04-03 - Repo Root Agents File Added

### Summary
- Added a top-level `AGENTS.md` at the real plugin repo root so future Codex runs have one concise, enforceable governance file inside the Git repository.
- Kept the file aligned with the current `/ai-workflow` standards instead of introducing a separate rule set.

### Files Changed

- `plugins/khatabook/AGENTS.md`
- `ai-workflow/AI_CHANGELOG.md`

### Validation

- Re-read the current governance sources in:
  - `ai-workflow/AI_MASTER_BRIEF.md`
  - `ai-workflow/AI_SYSTEM_RULES.md`
  - `ai-workflow/AI_UX_DOCTRINE.md`
- Reconfirmed the actual repo root and plugin structure from:
  - `plugins/khatabook/main.php`
  - `plugins/khatabook/tests`

### Workflow Updates

- Added a repo-root governance entry point for future runs.
- Kept `/ai-workflow` as the deeper source of truth for architecture, backlog, UX, and implementation guidance.

### Remaining Risks / Follow-Up

- Future runs must keep `AGENTS.md` and `/ai-workflow` aligned if governance rules change.

## 2026-04-03 - Controller-Level Test Hardening For Live Flows

### Summary
- Completed the top execution task by extending the existing PHP harness to cover live controller rules for auth, org access, invoice create/update behavior, and expense creation.
- Kept the testing approach inside the current repository patterns instead of adding a new framework.
- Promoted the contacts module to the next active product task after the coverage baseline was strengthened.

### Files Changed

- `plugins/khatabook/tests/TestWpEnvironment.php`
- `plugins/khatabook/tests/bootstrap.php`
- `plugins/khatabook/tests/run.php`
- `plugins/khatabook/tests/OtpAuthFlowTest.php`
- `plugins/khatabook/tests/OrgAccessControllerTest.php`
- `plugins/khatabook/tests/InvoiceControllerTest.php`
- `plugins/khatabook/tests/ExpenseControllerTest.php`
- `ai-workflow/AI_MASTER_BRIEF.md`
- `ai-workflow/AI_FILE_MAP.md`
- `ai-workflow/AI_FEATURE_BACKLOG.md`
- `ai-workflow/AI_NEXT_ACTIONS.md`
- `ai-workflow/AI_TECH_DEBT.md`
- `ai-workflow/AI_CHANGELOG.md`

### Validation

- `composer test` in `plugins/khatabook` passed with `21 passed, 0 failed`
- `php -l` passed on the new and changed test files

### Workflow Updates

- Marked the automated coverage hardening task complete.
- Moved the contacts module to the top of the active execution order.
- Updated the architecture and tech-debt notes to reflect the stronger test baseline and the remaining browser/integration gap.

### Remaining Risks / Follow-Up

- The automated suite still does not cover browser flows or real WordPress REST runtime integration.
- Future runs should keep the test harness lean and extend it only where it protects real current product behavior.

## 2026-04-03 - Contacts, Dashboard, And Expense Lifecycle Completion

### Summary
- Completed the next three safe execution tasks in order: first-class contacts management, a live operational home dashboard, and the missing expense update/archive lifecycle.
- Reused the current org-safe REST APIs and SPA shell patterns instead of introducing a new routing or state architecture.
- Extended the existing PHP test harness only where backend lifecycle rules changed.

### Files Changed

- `app/src/App.jsx`
- `app/src/layouts/DashboardLayout.jsx`
- `app/src/modules/contacts/api.js`
- `app/src/modules/contacts/hooks.js`
- `app/src/modules/contacts/ContactForm.jsx`
- `app/src/modules/contacts/ContactsList.jsx`
- `app/src/modules/contacts/ContactsPage.jsx`
- `app/src/modules/expenses/api.js`
- `app/src/modules/expenses/ExpenseForm.jsx`
- `app/src/modules/expenses/ExpensesPage.jsx`
- `app/src/modules/expenses/ExpenseDetail.jsx`
- `app/src/modules/expenses/ExpenseDetailPage.jsx`
- `app/src/modules/invoices/InvoicesPage.jsx`
- `app/src/pages/Home.jsx`
- `app/src/theme.css`
- `app/src/utils/locationFlags.js`
- `backend/Api/VyRestExpenses.php`
- `backend/Helpers/ExpenseEditHelper.php`
- `main.php`
- `tests/bootstrap.php`
- `tests/ExpenseControllerTest.php`
- `ai-workflow/AI_MASTER_BRIEF.md`
- `ai-workflow/AI_FILE_MAP.md`
- `ai-workflow/AI_FEATURE_BACKLOG.md`
- `ai-workflow/AI_NEXT_ACTIONS.md`
- `ai-workflow/AI_TECH_DEBT.md`
- `ai-workflow/AI_CHANGELOG.md`

### Validation

- `php -l` passed on:
  - `plugins/khatabook/backend/Helpers/ExpenseEditHelper.php`
  - `plugins/khatabook/backend/Api/VyRestExpenses.php`
  - `plugins/khatabook/main.php`
  - `plugins/khatabook/tests/bootstrap.php`
  - `plugins/khatabook/tests/ExpenseControllerTest.php`
- `composer test` in `plugins/khatabook` passed with `24 passed, 0 failed`
- `npm run build` in `plugins/khatabook/app` passed

### Workflow Updates

- Marked contacts, dashboard, and expense lifecycle backlog items complete.
- Promoted `CompanySettings` cleanup to the next active task.
- Updated master/file-map docs so contacts, dashboard, and expense lifecycle reflect the current live product.
- Replaced the old placeholder-dashboard and missing-contacts debt notes with current code-backed debt.

### Remaining Risks / Follow-Up

- Dashboard receivables are still derived from the latest 100 `SENT` invoices and latest 100 `PARTIAL` invoices, so very high-volume orgs can still undercount totals on the home screen.
- Contacts currently support archive only, not restore.
- Unpaid expenses still do not have a later “record payment” path after initial creation.
