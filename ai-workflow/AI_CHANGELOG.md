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

## 2026-04-12 - Curated Invoice Font Selector

## 2026-04-12 - Workspace Refresh And Template Settings Truthfulness QA Pass

### Summary
- Closed the known same-tab workspace-refresh gaps in the active org shell by broadcasting auth updates from the live org-switch paths and making the affected org-bound screens reload when the active organization changes.
- Fixed the current 4-template invoice settings truthfulness gap by validating safe hex colors server-side and making the direct template files actually consume saved primary/accent colors in preview and PDF output.
- Added a small preview guard so the invoice settings screen shows a readable error instead of a blank iframe when the preview route returns no HTML.

### Files Changed
- `app/src/App.jsx`
- `app/src/utils/authEvents.js`
- `app/src/pages/UsersAdmin.jsx`
- `app/src/pages/CompanySettings.jsx`
- `app/src/layouts/DashboardLayout.jsx`
- `app/src/modules/settings/invoices/InvoiceSettingsPage.jsx`
- `app/src/modules/settings/invoices/InvoiceTemplatePreview.jsx`
- `backend/Helpers/InvoiceTemplateHelper.php`
- `backend/Helpers/InvoiceTemplateRenderHelper.php`
- `backend/Api/VyRestInvoiceSettings.php`
- `backend/Api/VyRestInvoicePreview.php`
- `backend/templates/invoices/modern-clean-blue.php`
- `backend/templates/invoices/corporate-orange.php`
- `backend/templates/invoices/minimal-grey-elegant.php`
- `backend/templates/invoices/yellow-modern-minimal.php`
- `tests/InvoiceTemplateSystemTest.php`
- `ai-workflow/AI_CHANGELOG.md`
- `ai-workflow/AI_FEATURE_BACKLOG.md`
- `ai-workflow/AI_NEXT_ACTIONS.md`
- `ai-workflow/AI_TECH_DEBT.md`
- `ai-workflow/AI_MASTER_BRIEF.md`
- `ai-workflow/AI_FILE_MAP.md`

### Validation
- `php -l` on the changed invoice helper, API, template, and test files
- `composer test` in `plugins/khatabook` with `69 passed, 0 failed`
- `npm run build` in `plugins/khatabook/app`

### Workflow Updates
- Added `P1-14` and `P1-15` as completed backlog items for same-tab workspace refresh and invoice-template settings truthfulness.
- Updated next-actions guidance so future runs treat the current org/template QA pass as complete and only continue on newly reproduced issues.
- Updated tech-debt notes to reflect the shared auth-event helper and the remaining distributed-auth-sync tradeoff.

### Remaining Risks / Follow-Up
- Browser QA is still needed for header org switching while `UsersAdmin.jsx`, `CompanySettings.jsx`, and `InvoiceSettingsPage.jsx` are already open.
- Preview/PDF spot checks are still needed to confirm the chosen primary/accent colors look acceptable across all 4 direct template files in real client documents.

### Summary
- Replaced the free-text invoice font-family input with a curated dropdown of PDF-safe sans-serif font stacks.
- Normalized invoice font saving on the server so preview, stored settings, and PDF rendering all use the same approved set instead of arbitrary user-entered CSS.
- Fixed template rendering consistency by making the selected invoice font apply across all 4 active invoice templates.

### Files Changed
- `backend/Helpers/InvoiceTemplateHelper.php`
- `backend/Api/VyRestInvoiceSettings.php`
- `backend/Helpers/InvoiceTemplateRenderHelper.php`
- `backend/templates/invoices/modern-clean-blue.php`
- `backend/templates/invoices/yellow-modern-minimal.php`
- `app/src/modules/settings/invoices/InvoiceSettingsPage.jsx`
- `tests/InvoiceTemplateSystemTest.php`
- `ai-workflow/AI_CHANGELOG.md`
- `ai-workflow/AI_FEATURE_BACKLOG.md`
- `ai-workflow/AI_NEXT_ACTIONS.md`

### Validation
- `php -l` on the changed invoice helper, API, template, and test files
- `composer test` in `plugins/khatabook`
- `npm run build` in `plugins/khatabook/app`

### Workflow Updates
- Updated the baseline backlog wording so invoice settings now explicitly include curated font selection.
- Updated next-actions notes so future QA passes include truthful invoice font behavior in preview/PDF checks.

### Remaining Risks / Follow-Up
- Browser and PDF spot checks are still needed to decide which curated font stack should become the long-term default from a visual perspective.

## 2026-04-12 - Product Feature List Status Tracking Added

### Summary
- Audited the current repo against `ai-workflow/product_feature_list.md` using direct code inspection of the live plugin modules, tables, routes, and SPA surfaces.
- Added two dedicated agent-maintained feature-list companion files so future runs can track what is implemented, what is partial, and what remains or is blocked without polluting the raw requested feature list itself.
- Tightened repo governance so future feature work must keep those new product-feature tracker files in sync.

### Files Changed
- `plugins/khatabook/AGENTS.md`
- `plugins/khatabook/ai-workflow/PRODUCT_FEATURE_STATUS.md`
- `plugins/khatabook/ai-workflow/PRODUCT_FEATURE_PROGRESS_LOG.md`
- `plugins/khatabook/ai-workflow/AI_SYSTEM_RULES.md`
- `plugins/khatabook/ai-workflow/AI_FEATURE_BACKLOG.md`
- `plugins/khatabook/ai-workflow/AI_NEXT_ACTIONS.md`
- `plugins/khatabook/ai-workflow/AI_CHANGELOG.md`

### Validation
- Repo status was based on direct inspection of the live code paths rather than only prior workflow docs.
- No runtime product code changed in this run.

### Workflow Updates
- `AGENTS.md` now requires future runs to keep `PRODUCT_FEATURE_STATUS.md` and `PRODUCT_FEATURE_PROGRESS_LOG.md` updated whenever feature-list coverage changes.
- `AI_SYSTEM_RULES.md`, `AI_FEATURE_BACKLOG.md`, and `AI_NEXT_ACTIONS.md` now point future runs to the new product-feature tracking files.

### Remaining Risks / Follow-Up
- `PRODUCT_FEATURE_STATUS.md` is intentionally section-level rather than bullet-by-bullet; future runs should deepen it only when a specific feature family is actively being built or audited.
- The raw `product_feature_list.md` is still much broader than the current repo; agents should keep using code evidence before marking items complete.

## 2026-04-12 - Preview Response Decoding Fix

### Summary
- Fixed the invoice settings preview loader so the preview iframe receives real HTML instead of the REST server's JSON-encoded string representation.
- Kept the authenticated preview path intact; the fix stays in the frontend API client path and does not weaken the protected preview endpoint.

### Files Changed
- `app/src/modules/settings/invoices/invoiceSettingsApi.js`
- `ai-workflow/AI_CHANGELOG.md`
- `ai-workflow/AI_FEATURE_BACKLOG.md`
- `ai-workflow/AI_NEXT_ACTIONS.md`

### Validation
- `npm run build` in `plugins/khatabook/app`

### Workflow Updates
- Updated the invoice-preview backlog note so future runs understand that the current authenticated preview loader now decodes the REST JSON-string response correctly.
- Updated `AI_NEXT_ACTIONS.md` to keep the preview area in QA-fix mode rather than architecture churn.

### Remaining Risks / Follow-Up
- Browser QA is still needed against live signed-in preview rendering after template changes, especially for `srcDoc` output and PDF parity.

## 2026-04-12 - Four Template Catalog Reset And Authenticated Preview Fix

### Summary
- Replaced the old 10-template invoice catalog with exactly 4 active templates based on the approved reference designs: `modern-clean-blue`, `corporate-orange`, `minimal-grey-elegant`, and `yellow-modern-minimal`.
- Kept the current server-side invoice render path intact by preserving shared normalized data preparation while moving all live document HTML/CSS into the 4 direct template files.
- Fixed the invoice settings preview failure by stopping the unauthenticated iframe URL load and switching the preview UI to authenticated HTML fetching plus `iframe srcDoc`.

### Files Changed
- `backend/Helpers/InvoiceTemplateHelper.php`
- `backend/Helpers/InvoiceTemplateRenderHelper.php`
- `backend/Helpers/InvoiceRenderHelper.php`
- `backend/Api/VyRestInvoicePreview.php`
- `backend/Invoices/VyInvoicePdf.php`
- `backend/Db/TableManager.php`
- `backend/templates/invoices/modern-clean-blue.php`
- `backend/templates/invoices/corporate-orange.php`
- `backend/templates/invoices/minimal-grey-elegant.php`
- `backend/templates/invoices/yellow-modern-minimal.php`
- removed the previous 10 template files under `backend/templates/invoices/`
- `app/src/utils/apiClient.js`
- `app/src/modules/settings/invoices/invoiceSettingsApi.js`
- `app/src/modules/settings/invoices/InvoiceTemplatePreview.jsx`
- `tests/InvoiceTemplateSystemTest.php`
- `tests/InvoiceHelpersTest.php`
- `tests/InvoiceControllerTest.php`
- `ai-workflow/AI_MASTER_BRIEF.md`
- `ai-workflow/AI_FILE_MAP.md`
- `ai-workflow/AI_FEATURE_BACKLOG.md`
- `ai-workflow/AI_NEXT_ACTIONS.md`
- `ai-workflow/AI_TECH_DEBT.md`
- `ai-workflow/AI_CHANGELOG.md`

### Validation
- `php -l` passed on the changed backend helpers, preview/PDF files, all 4 template files, and the updated test files
- `composer test` in `plugins/khatabook` passed with `66 passed, 0 failed`
- `npm run build` in `plugins/khatabook/app` passed

### Workflow Updates
- Updated the backlog and next-actions files so future runs treat the current invoice baseline as a 4-template catalog with authenticated preview loading.
- Updated the master brief, file map, and tech debt notes so future runs inspect the new template names and preview integration before changing invoice-document behavior.

### Remaining Risks / Follow-Up
- Browser and PDF spot checks are still needed against the 4 reference layouts, especially the orange diagonal header treatment and long-text behavior.
- Legacy invoice/template ids still exist in old stored records, but they now resolve through the compatibility mapper instead of exposing the removed templates in settings.

## 2026-04-12 - Copy Cleanup, Company-Admin Org Creation, And Preview Review Closure

### Summary
- Completed the next three safe queue items in order after re-reading `AGENTS.md`, the workflow docs, and the live org/template code paths: removed the last user-facing billing-path wording, added a company-admin organization creation path on top of the current membership model, and closed the invoice-preview review item based on current code plus direct endpoint coverage instead of speculative template rework.
- Kept the org work inside the existing `kbs_organizations` plus `kbs_user_org_roles` model, reused the existing active-org auth payload shape, and added same-tab auth refresh support instead of introducing a second tenancy or session sync system.
- Added controller and template-preview coverage in the current PHP harness, including the new organization-creation path and the sample-fallback preview endpoint path.

### Files Changed
- `app/src/modules/invoices/InvoicesPage.jsx`
- `backend/Api/OrgUsersController.php`
- `backend/Endpoint/EndpointManager.php`
- `app/src/App.jsx`
- `app/src/layouts/DashboardLayout.jsx`
- `app/src/pages/UsersAdmin.jsx`
- `tests/TestWpEnvironment.php`
- `tests/OrgUsersControllerTest.php`
- `tests/InvoiceTemplateSystemTest.php`
- `ai-workflow/AI_MASTER_BRIEF.md`
- `ai-workflow/AI_FILE_MAP.md`
- `ai-workflow/AI_FEATURE_BACKLOG.md`
- `ai-workflow/AI_NEXT_ACTIONS.md`
- `ai-workflow/AI_TECH_DEBT.md`
- `ai-workflow/AI_CHANGELOG.md`

### Validation
- `php -l` passed on:
  - `backend/Api/OrgUsersController.php`
  - `backend/Endpoint/EndpointManager.php`
  - `tests/TestWpEnvironment.php`
  - `tests/OrgUsersControllerTest.php`
  - `tests/InvoiceTemplateSystemTest.php`
- `composer test` in `plugins/khatabook` passed with `66 passed, 0 failed`
- `npm run build` in `plugins/khatabook/app` passed

### Workflow Updates
- Marked `P1-11`, `P1-12`, and `P1-13` complete in `AI_FEATURE_BACKLOG.md`.
- Rewrote `AI_NEXT_ACTIONS.md` so future runs treat the unblocked queue as complete and only act on reproduced QA bugs or explicit approvals for blocked roadmap items.
- Updated `AI_MASTER_BRIEF.md`, `AI_FILE_MAP.md`, and `AI_TECH_DEBT.md` so future runs treat company-admin org creation and same-tab auth refresh as part of the live baseline and understand the remaining auth-sync tradeoff.

### Remaining Risks / Follow-Up
- Live browser QA is still needed for the new organization card and create/switch flow in `app/src/pages/UsersAdmin.jsx`.
- The app shell now supports same-tab auth refresh, but future auth-mutating flows must use the same event path to stay in sync.
- Invoice preview is now code-backed as a stable path, but browser and PDF spot checks are still the right trigger for any future template fixes.

## 2026-04-11 - Direct Invoice Template Files

### Summary
- Reworked the invoice template architecture after explicit product approval to keep the live multi-template catalog but remove the old shared HTML renderer.
- Shared helpers now prepare normalized invoice-template data, while each file under `backend/templates/invoices` contains its own direct PHP/HTML document and template-specific CSS.
- Kept preview and PDF generation on the same render path by having both include the selected template file with prepared context.

### Files Changed
- `backend/Helpers/InvoiceTemplateRenderHelper.php`
- `backend/Api/VyRestInvoicePreview.php`
- `backend/Invoices/VyInvoicePdf.php`
- `backend/templates/invoices/minimal-clean.php`
- `backend/templates/invoices/bordered-classic.php`
- `backend/templates/invoices/bold-header.php`
- `backend/templates/invoices/accent-panel.php`
- `backend/templates/invoices/compact-grid.php`
- `backend/templates/invoices/elegant-professional.php`
- `backend/templates/invoices/executive-blue.php`
- `backend/templates/invoices/soft-premium.php`
- `backend/templates/invoices/formal-ledger.php`
- `backend/templates/invoices/contemporary-statement.php`
- `tests/InvoiceTemplateSystemTest.php`
- `ai-workflow/AI_MASTER_BRIEF.md`
- `ai-workflow/AI_FILE_MAP.md`
- `ai-workflow/AI_FEATURE_BACKLOG.md`
- `ai-workflow/AI_NEXT_ACTIONS.md`
- `ai-workflow/AI_TECH_DEBT.md`
- `ai-workflow/AI_CHANGELOG.md`

### Validation
- `php -l` passed on the changed backend helpers, preview/PDF files, all 10 template files, and `tests/InvoiceTemplateSystemTest.php`
- `composer test` in `plugins/khatabook` passed with `63 passed, 0 failed`

### Workflow Updates
- Marked `P1-10` done in `AI_FEATURE_BACKLOG.md` with the new repo-accurate scope: direct per-template files without collapsing the live catalog.
- Updated `AI_NEXT_ACTIONS.md`, `AI_MASTER_BRIEF.md`, and `AI_FILE_MAP.md` so future runs treat direct template files as the current invoice-document baseline.
- Added a maintainability tradeoff note in `AI_TECH_DEBT.md` documenting the intentional duplication across direct template files.

### Remaining Risks / Follow-Up
- Template rendering now matches the requested direct-file editing model, but cross-template structural changes will require disciplined multi-file edits.
- Manual browser/PDF QA is still needed across the full template catalog after the render-path simplification.

## 2026-04-10 - Paid Invoice Refunds, Invoice List Clarity, And Invoice Form Data Quality

### Summary
- Completed the next three safe execution tasks in order after re-reading `AGENTS.md`, the workflow docs, and the live invoice/detail/form code paths: added a paid-invoice cancel/refund workflow, improved invoice list scanability, and tightened invoice-form customer selection plus phone validation behavior.
- Kept the refund implementation on the active `vy_*` invoice/journal path by adding a dedicated `vy_invoice_refunds` table, reusing the current journal engine, and surfacing refund state in the existing invoice detail page instead of inventing a parallel subsystem.
- Updated the invoice list and invoice form inside the current SPA module structure, then extended the PHP harness to cover the new refund lifecycle and server-side phone validation rules.

### Files Changed
- `backend/Api/VyRestInvoices.php`
- `backend/Db/TableManager.php`
- `backend/Helpers/InvoiceFinancialHelper.php`
- `app/src/modules/invoices/api.js`
- `app/src/modules/invoices/InvoiceDetailPage.jsx`
- `app/src/modules/invoices/InvoiceDetail.jsx`
- `app/src/modules/invoices/InvoiceRefundForm.jsx`
- `app/src/modules/invoices/InvoicesPage.jsx`
- `app/src/modules/invoices/InvoiceList.jsx`
- `app/src/modules/invoices/InvoiceForm.jsx`
- `app/src/modules/contacts/ContactSuggestInput.jsx`
- `app/src/theme.css`
- `tests/InvoiceControllerTest.php`
- `tests/TestWpEnvironment.php`
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
  - `tests/InvoiceControllerTest.php`
  - `tests/TestWpEnvironment.php`
- `composer test` in `plugins/khatabook` passed with `62 passed, 0 failed`
- `npm run build` in `plugins/khatabook/app` passed

### Workflow Updates
- Marked `P0-11`, `P1-07`, `P1-08`, and `P1-09` complete in `AI_FEATURE_BACKLOG.md`.
- Advanced `AI_NEXT_ACTIONS.md` to the next safe queue: user-facing copy cleanup, then multi-organization management expansion, while keeping template-review and blocked AI/OCR/quotation work constrained.
- Updated `AI_MASTER_BRIEF.md`, `AI_FILE_MAP.md`, and `AI_TECH_DEBT.md` so future runs treat refund support, invoice-list clarity, and invoice-form customer validation as current baseline behavior.

### Remaining Risks / Follow-Up
- The new refund workflow intentionally supports full refunds of fully paid invoices only; partial refunds or payment-specific reversal workflows are still out of scope.
- The invoice list improvements still need manual browser QA on smaller screens and live dataset combinations.
- Invoice template preview remains a `REVIEW` item and should only become active code work again if a real mismatch is reproduced.

## 2026-04-10 - Expense Crash Repair, Account Lifecycle, And Invoice Payment Integrity

### Summary
- Completed the next three safe execution tasks in order after re-reading `AGENTS.md`, the workflow docs, and the relevant current code paths: fixed the live Expenses page runtime crash, completed safe account lifecycle actions, and added duplicate-submit protection plus truthful fully-paid CTA behavior for invoice payments.
- Kept the work inside the existing expense, accounts, and invoice modules instead of introducing new state systems, ledger models, or refund platforms.
- Added controller-level coverage for the new account lifecycle rules and invoice duplicate-payment guard, then updated the workflow queue so future runs move to the remaining paid-invoice refund gap instead of already-resolved P0 work.

### Files Changed
- `app/src/modules/expenses/ExpensesPage.jsx`
- `backend/Api/VyRestAccounts.php`
- `app/src/modules/accounts/api.js`
- `app/src/modules/accounts/AccountsPage.jsx`
- `app/src/modules/accounts/AccountDetailPage.jsx`
- `app/src/modules/accounts/AccountForm.jsx`
- `backend/Api/VyRestInvoices.php`
- `app/src/modules/invoices/InvoicePaymentForm.jsx`
- `app/src/modules/invoices/InvoiceDetailPage.jsx`
- `tests/AccountControllerTest.php`
- `tests/InvoiceControllerTest.php`
- `ai-workflow/AI_MASTER_BRIEF.md`
- `ai-workflow/AI_FILE_MAP.md`
- `ai-workflow/AI_FEATURE_BACKLOG.md`
- `ai-workflow/AI_NEXT_ACTIONS.md`
- `ai-workflow/AI_TECH_DEBT.md`
- `ai-workflow/AI_CHANGELOG.md`

### Validation
- `php -l` passed on:
  - `backend/Api/VyRestAccounts.php`
  - `backend/Api/VyRestInvoices.php`
  - `tests/AccountControllerTest.php`
  - `tests/InvoiceControllerTest.php`
- `composer test` in `plugins/khatabook` passed with `59 passed, 0 failed`
- `npm run build` in `plugins/khatabook/app` passed

### Workflow Updates
- Marked `P0-08`, `P0-09`, `P0-10`, and `P0-12` complete in `AI_FEATURE_BACKLOG.md`.
- Promoted `P0-11` to the next default-safe task and moved invoice-list/form quality work directly behind it in `AI_NEXT_ACTIONS.md`.
- Cleared the resolved Expenses crash, account lifecycle, and invoice payment integrity items from `AI_TECH_DEBT.md`.
- Updated the master brief and file map so future runs treat account lifecycle maintenance and duplicate-safe invoice payments as part of the live product baseline.

### Remaining Risks / Follow-Up
- Paid-invoice cancel/refund is now the main remaining P0 billing lifecycle gap.
- Invoice list clarity, customer suggestion dropdown behavior, and customer phone validation remain active P1 product-quality work.
- AI invoice assistant, OCR bill extraction, quotation direction, and any single-template reversal remain blocked until explicitly approved.

## 2026-04-10 - Revenue Leak Detection And Email System Cleanup

### Summary
- Completed three safe tasks in order after re-reading `AGENTS.md`, the workflow docs, and the relevant current code paths: shipped the revenue leak detector, corrected the canonical Vyavhar email/document rendering path, and added a dedicated OTP email template.
- Kept the work inside the existing reporting, email, auth, and invoice architecture instead of introducing a new analytics subsystem, a second mailer path, or an external AI/OCR dependency.
- Reconciled the workflow queue afterward so future runs no longer point at the now-finished leak/email tasks and instead prioritize the still-confirmed Expenses runtime crash and payment/account integrity gaps.

### Files Changed
- `backend/Helpers/ReportHelper.php`
- `backend/Api/VyRestReports.php`
- `app/src/modules/reports/api.js`
- `app/src/modules/reports/RevenueLeakPanel.jsx`
- `app/src/pages/Home.jsx`
- `app/src/modules/reports/ProfitTaxPage.jsx`
- `app/src/theme.css`
- `backend/Email/EmailManager.php`
- `backend/Helpers/InvoiceEmailHelper.php`
- `backend/Auth/OtpAuth.php`
- `backend/Api/OrgUsersController.php`
- `main.php`
- `tests/bootstrap.php`
- `tests/TestWpEnvironment.php`
- `tests/ReportsControllerTest.php`
- `tests/OtpAuthFlowTest.php`
- `tests/EmailManagerTest.php`
- `ai-workflow/AI_MASTER_BRIEF.md`
- `ai-workflow/AI_FILE_MAP.md`
- `ai-workflow/AI_FEATURE_BACKLOG.md`
- `ai-workflow/AI_NEXT_ACTIONS.md`
- `ai-workflow/AI_TECH_DEBT.md`
- `ai-workflow/AI_CHANGELOG.md`

### Validation
- `php -l` passed on the changed PHP files, including the report helpers/controllers, email/auth files, and updated test harness files.
- `composer test` in `plugins/khatabook` passed with `55 passed, 0 failed`.
- `npm run build` in `plugins/khatabook/app` passed after the report/dashboard UI changes.

### Workflow Updates
- Marked `P0-06`, `P0-07`, and `P3-05` done in `AI_FEATURE_BACKLOG.md`.
- Marked `P3-06` blocked until there is explicit AI/provider approval and kept OCR blocked for the existing dependency reasons.
- Reordered `AI_NEXT_ACTIONS.md` so the next default-safe queue is now the confirmed Expenses crash, then account lifecycle, then invoice payment integrity fixes.
- Updated `AI_MASTER_BRIEF.md`, `AI_FILE_MAP.md`, and `AI_TECH_DEBT.md` so future runs treat revenue leak detection plus branded document/OTP email behavior as current baseline capability.

### Remaining Risks / Follow-Up
- `app/src/modules/expenses/ExpensesPage.jsx` still has the confirmed `formatCurrency` runtime failure and should be the next repair.
- Invoice payment posting still lacks duplicate-submit protection and truthful fully-paid CTA gating.
- AI invoice assistant and OCR remain blocked until there is explicit dependency and product-direction approval.

## 2026-04-08 - Expense Settlement, Payables Reporting, And Shared State Cleanup

### Summary
- Completed the next three safe execution tasks in order: added later settlement for unpaid expenses and vendor bills, expanded payables reporting on live `vy_expenses` data, and normalized `Home.jsx` and `CompanySettings.jsx` onto the shared async/state pattern.
- Kept the work inside the existing plugin architecture by extending `VyRestExpenses.php`, `VyRestReports.php`, `ReportHelper.php`, and the existing SPA pages instead of introducing new payment subsystems, new reporting stores, or a frontend state-management rewrite.
- Added focused controller-level coverage for expense settlement and payables reporting, and updated the lightweight test environment to understand the richer live `vy_expenses` report query shape.

### Files Changed
- `backend/Api/VyRestExpenses.php`
- `backend/Helpers/ExpenseEditHelper.php`
- `backend/Api/VyRestReports.php`
- `backend/Helpers/ReportHelper.php`
- `app/src/modules/expenses/api.js`
- `app/src/modules/expenses/ExpenseDetailPage.jsx`
- `app/src/modules/expenses/ExpenseDetail.jsx`
- `app/src/modules/reports/api.js`
- `app/src/modules/reports/ProfitTaxPage.jsx`
- `app/src/hooks/useAsyncResource.js`
- `app/src/pages/Home.jsx`
- `app/src/pages/CompanySettings.jsx`
- `tests/ExpenseControllerTest.php`
- `tests/ReportsControllerTest.php`
- `tests/TestWpEnvironment.php`
- `ai-workflow/AI_MASTER_BRIEF.md`
- `ai-workflow/AI_FILE_MAP.md`
- `ai-workflow/AI_FEATURE_BACKLOG.md`
- `ai-workflow/AI_NEXT_ACTIONS.md`
- `ai-workflow/AI_TECH_DEBT.md`
- `ai-workflow/AI_CHANGELOG.md`

### Validation
- `php -l` passed on:
  - `backend/Helpers/ExpenseEditHelper.php`
  - `backend/Api/VyRestExpenses.php`
  - `backend/Helpers/ReportHelper.php`
  - `backend/Api/VyRestReports.php`
  - `tests/ExpenseControllerTest.php`
  - `tests/ReportsControllerTest.php`
  - `tests/TestWpEnvironment.php`
- `composer test` in `plugins/khatabook` passed with `49 passed, 0 failed`
- `npm run build` in `plugins/khatabook/app` passed

### Workflow Updates
- Marked `P2-11`, `P2-12`, and `P2-13` complete in `AI_FEATURE_BACKLOG.md`.
- Promoted the remaining roadmap-safe queue to `P3-01`, `P3-02`, and `P3-03` in `AI_NEXT_ACTIONS.md`.
- Updated the master brief, file map, and tech debt notes so future runs treat expense later settlement, payables reporting, and shared async normalization as current baseline behavior rather than open gaps.

### Remaining Risks / Follow-Up
- `ProfitTaxPage.jsx` still coordinates several report endpoints through one bespoke page-level loader.
- Home still fans out through multiple dashboard calls because there is no dedicated aggregated dashboard endpoint yet.
- The next default-safe work is now advanced roadmap work, not another unfinished P0-P2 operational gap.

## 2026-04-07 - Vendor Bills, Dashboard Summary Truthfulness, And Org Write Rollback

### Summary
- Completed the next three safe execution tasks in order: made vendor bills first-class on the live `vy_expenses` model, aligned the dashboard receivables copy with the already-live server-side summary, and tightened the remaining high-risk org invite/member rollback paths.
- Kept all work inside the current plugin architecture by extending `VyRestExpenses.php`, `OrgUsersController.php`, and the existing SPA expenses/dashboard screens instead of introducing new tables, routers, or payables subsystems.
- Added focused controller-level coverage for the new expense summary/filter/detail behavior and the org rollback compensation paths in the existing PHP harness.

### Files Changed
- `backend/Api/VyRestExpenses.php`
- `backend/Api/OrgUsersController.php`
- `app/src/modules/expenses/api.js`
- `app/src/modules/expenses/hooks.js`
- `app/src/modules/expenses/ExpensesPage.jsx`
- `app/src/modules/expenses/ExpensesList.jsx`
- `app/src/modules/expenses/ExpenseDetail.jsx`
- `app/src/pages/Home.jsx`
- `app/src/theme.css`
- `tests/ExpenseControllerTest.php`
- `tests/OrgUsersControllerTest.php`
- `tests/TestWpEnvironment.php`
- `ai-workflow/AI_MASTER_BRIEF.md`
- `ai-workflow/AI_FILE_MAP.md`
- `ai-workflow/AI_FEATURE_BACKLOG.md`
- `ai-workflow/AI_NEXT_ACTIONS.md`
- `ai-workflow/AI_TECH_DEBT.md`
- `ai-workflow/AI_CHANGELOG.md`

### Validation
- `php -l` passed on:
  - `backend/Api/VyRestExpenses.php`
  - `backend/Api/OrgUsersController.php`
  - `tests/TestWpEnvironment.php`
  - `tests/ExpenseControllerTest.php`
  - `tests/OrgUsersControllerTest.php`
- `composer test` in `plugins/khatabook` passed with `46 passed, 0 failed`
- `npm run build` in `plugins/khatabook/app` passed

### Workflow Updates
- Marked `P2-08`, `P2-09`, and `P2-10` complete.
- Added `P2-11`, `P2-12`, and `P2-13` as the new next safe queue for payables completion and shared-state normalization.
- Updated the master brief, file map, and tech debt notes so future runs treat vendor-bill visibility and dashboard receivables summary alignment as active baseline behavior rather than open gaps.

### Remaining Risks / Follow-Up
- Unpaid expenses and vendor bills still need a true later settlement flow.
- Payables reporting is still thinner than receivables reporting.
- Home and CompanySettings still keep more bespoke fetch/state handling than the normalized module screens.

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

## 2026-04-10 - Billing Health, Owner Brief, And Invoice Risk Checks

### Summary
- Attempted the next roadmap queue in order and confirmed OCR bill extraction is blocked in the current repository because there is still no expense-file attachment path or OCR/parser dependency to build on safely.
- Completed the next three safe tasks instead: billing health scoring, the owner daily brief, and rule-based invoice risk checks.
- Kept the implementation inside the existing `vy_*` reporting and invoice-detail architecture, then extended the PHP harness to protect the new backend behavior.

### Files Changed

- `plugins/khatabook/backend/Helpers/ReportHelper.php`
- `plugins/khatabook/backend/Helpers/InvoiceRiskHelper.php`
- `plugins/khatabook/backend/Api/VyRestReports.php`
- `plugins/khatabook/backend/Api/VyRestInvoices.php`
- `plugins/khatabook/main.php`
- `plugins/khatabook/app/src/pages/Home.jsx`
- `plugins/khatabook/app/src/modules/reports/api.js`
- `plugins/khatabook/app/src/modules/reports/ProfitTaxPage.jsx`
- `plugins/khatabook/app/src/modules/invoices/InvoiceDetail.jsx`
- `plugins/khatabook/app/src/modules/invoices/InvoiceDetailPage.jsx`
- `plugins/khatabook/app/src/theme.css`
- `plugins/khatabook/tests/bootstrap.php`
- `plugins/khatabook/tests/ReportsControllerTest.php`
- `plugins/khatabook/tests/InvoiceControllerTest.php`
- `plugins/khatabook/ai-workflow/AI_MASTER_BRIEF.md`
- `plugins/khatabook/ai-workflow/AI_FILE_MAP.md`
- `plugins/khatabook/ai-workflow/AI_FEATURE_BACKLOG.md`
- `plugins/khatabook/ai-workflow/AI_NEXT_ACTIONS.md`
- `plugins/khatabook/ai-workflow/AI_TECH_DEBT.md`
- `plugins/khatabook/ai-workflow/AI_CHANGELOG.md`

### Validation

- `php -l` passed on:
  - `plugins/khatabook/backend/Helpers/InvoiceRiskHelper.php`
  - `plugins/khatabook/backend/Helpers/ReportHelper.php`
  - `plugins/khatabook/backend/Api/VyRestReports.php`
  - `plugins/khatabook/backend/Api/VyRestInvoices.php`
- `composer test` in `plugins/khatabook` passed with `51 passed, 0 failed`
- `npm run build` in `plugins/khatabook/app` passed

### Workflow Updates

- Marked OCR bill extraction blocked with code-backed evidence instead of leaving it as the default next task.
- Marked billing health, owner daily brief, and invoice risk engine complete.
- Advanced the default-safe execution queue to the revenue leak detector.

### Remaining Risks / Follow-Up

- OCR bill extraction still needs an explicit attachment/OCR dependency direction before it becomes safe active work.
- Home and reports still fan out through multiple report/dashboard requests rather than a single aggregated backend endpoint.
- The owner daily brief is currently a live dashboard/report surface, not a scheduled outbound digest.
