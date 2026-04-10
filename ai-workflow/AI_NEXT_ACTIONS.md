# AI Next Actions

Base path: `/Users/hardikkaneria/Local Sites/khatabook/app/public/wp-content`

This file is the execution handoff for the next Codex run.

It must stay short, practical, and action-oriented.

This file is not a brainstorming document.  
It exists to answer:

- what should be done next
- why it should be done next
- what files should be inspected first
- what should happen if the task blocks
- what must be updated after the task is complete

---

## 1. Current Best Execution Order

Unless the user explicitly overrides it, work in this order:

1. Fix the confirmed `ExpensesPage.jsx` runtime crash.
2. Restore safe account lifecycle actions without breaking journal history.
3. Prevent duplicate invoice payment posting, then align payment-action visibility with live fully-paid state.
4. Only after those P0 operational gaps are closed, consider paid-invoice cancel/refund work.
5. Keep AI invoice assistant, OCR, and blocked decision work out of active execution unless the dependency/product direction is explicitly approved.

---

## 2. Best Next Run

### Primary Task
Fix the confirmed Expenses page runtime crash.

### Why this should go first
- `plugins/khatabook/app/src/modules/expenses/ExpensesPage.jsx` still renders summary cards with `formatCurrency(...)` but does not define or import that helper.
- This is a hard runtime failure on an already-live core module, so it outranks lower-severity quality improvements and roadmap work.
- The fix is small, repo-grounded, and should be completed before any broader financial UX work continues.

### Required reading before coding
Read these workflow files first:
- `ai-workflow/AI_MASTER_BRIEF.md`
- `ai-workflow/AI_SYSTEM_RULES.md`
- `ai-workflow/AI_FEATURE_BACKLOG.md`
- `ai-workflow/AI_TECH_DEBT.md`
- `ai-workflow/AI_IMPLEMENTATION_PLAYBOOK.md`

Then inspect these current code files:
- `plugins/khatabook/app/src/modules/expenses/ExpensesPage.jsx`
- `plugins/khatabook/app/src/modules/expenses/ExpensesList.jsx`
- `plugins/khatabook/app/src/modules/expenses/hooks.js`
- `plugins/khatabook/app/src/modules/expenses/api.js`
- `plugins/khatabook/app/src/theme.css`
- current expense-related tests in `plugins/khatabook/tests/*`

### Exact target coverage for this run
Fix the crash without changing expense behavior beyond the runtime repair:

- trace where summary-card currency formatting should come from
- restore a local/shared currency helper safely
- make sure the page still renders live summary totals, filters, and pagination exactly from the current expense APIs
- add or adjust lightweight test coverage only if there is a practical place in the current harness

### Constraints
- Do not redesign the expenses screen.
- Do not change the live expense summary API shape unless the fix requires it.
- Do not mix this runtime repair with larger payables UX rewrites.
- Keep the fix consistent with the shared UI/helper patterns already used in the SPA.

### Done when
- the Expenses page loads without throwing `formatCurrency is not defined`
- summary totals render correctly
- no new drift is introduced in the expense list/detail flow
- workflow files are updated after completion
- `AI_CHANGELOG.md`, `AI_FEATURE_BACKLOG.md`, and `AI_NEXT_ACTIONS.md` are updated

---

## 3. Best Task After That

### Next Task
Restore safe account lifecycle actions.

### Why this is the best follow-up after the Expenses crash fix
- `VyRestAccounts.php` already supports create, read, and journal-aware delete/archive behavior, but the live accounts surface still lacks safe edit/inactive management.
- This is operationally important and still stays inside the current accounts/journal architecture.
- It is safer and more valuable than jumping to AI or OCR roadmap work.

### Start here
- `plugins/khatabook/backend/Api/VyRestAccounts.php`
- `plugins/khatabook/backend/Accounting/VyJournalEngine.php`
- `plugins/khatabook/app/src/modules/accounts/api.js`
- `plugins/khatabook/app/src/modules/accounts/*`

### Minimum acceptable scope
- enable only safe supported account edits
- allow inactive/archive behavior for future transactions without corrupting history
- keep delete rules tied to actual journal usage
- do not invent a second account model or non-journal shortcut path

### Done when
- operators can safely maintain account records without breaking historical journal truth

---

## 4. Third Task After That

### Next Task
Prevent duplicate invoice payment recording, then align paid-state CTA visibility.

### Why this comes here
- `InvoicePaymentForm.jsx` still submits without a loading/locking state.
- `VyRestInvoices::pay_invoice()` currently rejects overpayments and already-paid invoices, but it does not add idempotency-style duplicate-submit protection.
- `InvoiceDetailPage.jsx` still shows the Record Payment CTA whenever the invoice exists, even if `balance_due` is already zero.

### Start here
- `plugins/khatabook/app/src/modules/invoices/InvoicePaymentForm.jsx`
- `plugins/khatabook/app/src/modules/invoices/InvoiceDetailPage.jsx`
- `plugins/khatabook/backend/Api/VyRestInvoices.php`
- `plugins/khatabook/tests/InvoiceControllerTest.php`

### Minimum acceptable scope
- add submit locking and user-visible saving state in the payment modal
- add backend protection against duplicate submissions
- ensure fully paid invoices do not expose a live Record Payment action after refresh
- keep current payment posting, org checks, and journal behavior intact

### Done when
- repeated payment submits cannot create duplicate rows
- paid invoices do not show misleading payment actions

---

## 5. Fourth Task After That

### Next Task
Keep AI invoice assistant, OCR, quotations, and other blocked roadmap work blocked until explicitly approved.

### Why this comes here
- the repo still has no AI provider/client/config path for an invoice assistant
- OCR bill extraction still lacks an attachment model and parser dependency
- quotation direction is still a product decision, not active execution work

### Start here
- `plugins/khatabook/ai-workflow/AI_FEATURE_BACKLOG.md`
- `plugins/khatabook/ai-workflow/AI_TECH_DEBT.md`

### Minimum acceptable scope
- do not pull blocked roadmap items forward without explicit approval
- if one of those items is requested, document the missing dependency or product decision first

### Done when
- the next execution order stays aligned with the real post-payables repository state

---

## 6. Decision Checks Before Taking Other Work

### Quotations
Do not build quotation features until there is an explicit decision on whether quotations:
- remain legacy-only
- are rebuilt on `vy_*`
- or are removed from near-term scope

### Company settings breadth
Do not re-expand `CompanySettings.jsx` with hidden placeholder sections unless there is a confirmed live consumer for them.

### OCR dependency direction
OCR bill extraction is currently blocked. The repo still has no expense-file attachment model, no local OCR library, and no parser service to build on. Do not wire cloud OCR providers or external AI parsing APIs into the default implementation unless a run has explicit approval to add that dependency.

### AI dependency direction
AI invoice assistance is currently blocked. The repo still has no approved AI provider/client/config path in the active plugin architecture. Do not introduce OpenAI or any other external AI dependency by default unless the run explicitly approves that product and operational direction.

### Legacy business tables
Do not add new product work on:
- `kbs_invoices`
- `kbs_invoice_items`
- `kbs_quotations`
- `kbs_quotation_items`
- `kbs_accounting_entries`

unless there is explicit approval after inspection.

---

## 7. Pre-Change Checklist For Every Run

Before editing code:

1. Read the relevant `/ai-workflow` files.
2. Inspect the exact backend and frontend files involved.
3. Trace:
   - frontend caller
   - API route
   - permission path
   - org resolution
   - helper/business-rule path
   - DB tables touched
4. Confirm whether the requested work is:
   - active product work
   - improvement of active behavior
   - blocked decision work
   - roadmap-only work

Do not start coding until this is clear.

---

## 8. Post-Change Workflow Maintenance

Always update after meaningful work:
- `AI_CHANGELOG.md`
- `AI_FEATURE_BACKLOG.md`
- `AI_NEXT_ACTIONS.md`

Update these too if architecture or standards changed:
- `AI_MASTER_BRIEF.md`
- `AI_FILE_MAP.md`
- `AI_TECH_DEBT.md`
- `AI_SYSTEM_RULES.md`
- `AI_UX_DOCTRINE.md`
- `AI_IMPLEMENTATION_PLAYBOOK.md`

---

## 9. Safe Fallback Tasks If The Main Task Blocks

If the main task is blocked by environment, product ambiguity, or missing path clarity, use one of these instead:

- restore another confirmed runtime/UI integrity issue on an already-live module
- tighten invoice payment validation or paid-state CTA truthfulness
- improve workflow documentation around blocked AI/OCR decisions
- clarify legacy-vs-active schema notes where the code is easy to misread
- improve empty/loading/error states on already-active screens
- tighten validation or logging around an already-active live flow

Fallback tasks must still be:
- repo-grounded
- safe
- useful
- non-speculative

---

## 10. Work That Should Wait

Do not prioritize these ahead of the current queue:

- router or state-management rewrites
- new product work on legacy `kbs_*` business tables
- speculative inventory workflows
- speculative webhook framework work
- speculative two-factor-auth work
- large redesign work before core operational screens are complete
- quotation implementation without a clear approved direction
- AI feature work before current product gaps are resolved

---

## 11. One-Line Operating Instruction For The Next Codex Run

Use this if no custom instruction is provided:

Read all files in `/ai-workflow` first. Inspect the current codebase before changing anything. Execute the current best next task from `AI_NEXT_ACTIONS.md` and `AI_FEATURE_BACKLOG.md` using the existing architecture and module patterns. Keep the work production-ready, org-safe, and UX-consistent, then update the workflow files after completion.
