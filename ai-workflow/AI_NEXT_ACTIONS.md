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

1. Build vendor bills / purchase-bill workflow on the current expense/vendor foundations.
2. Replace Home dashboard receivables shortcuts with the server-side receivables summary.
3. Add rollback discipline to org member and invite writes.
4. Add later settlement flow for unpaid expenses and bills.
5. Keep blocked decision work out of active execution unless the user explicitly re-prioritizes it.

---

## 2. Best Next Run

### Primary Task
Build vendor bills / purchase-bill workflow on the current expense/vendor foundations.

### Why this should go first
- Recurring billing, credit/debit notes, and promise-to-pay tracking are now complete on the active invoice model.
- The largest remaining business-control gap is payables maturity: expenses exist, but a true vendor-bill workflow is still missing.
- The contacts, expense, journal, and reporting foundations are now strong enough to extend payables without reopening legacy schema work.

### Required reading before coding
Read these workflow files first:
- `ai-workflow/AI_MASTER_BRIEF.md`
- `ai-workflow/AI_SYSTEM_RULES.md`
- `ai-workflow/AI_FEATURE_BACKLOG.md`
- `ai-workflow/AI_TECH_DEBT.md`
- `ai-workflow/AI_IMPLEMENTATION_PLAYBOOK.md`

Then inspect these current code files:
- `plugins/khatabook/backend/Api/VyRestExpenses.php`
- `plugins/khatabook/backend/Api/VyRestContacts.php`
- `plugins/khatabook/backend/Accounting/VyJournalEngine.php`
- `plugins/khatabook/backend/Helpers/ExpenseEditHelper.php`
- `plugins/khatabook/backend/Db/TableManager.php`
- `plugins/khatabook/backend/Helpers/ReportHelper.php`
- `plugins/khatabook/app/src/modules/expenses/*`
- `plugins/khatabook/app/src/modules/contacts/*`
- current expense, contact, report, and journal tests in `plugins/khatabook/tests/*`

### Exact target coverage for this run
Add vendor bills without redesigning the payables model:

- inspect the current expense model, vendor contact usage, payment timing, and journal rules first
- build on `vy_*` expenses and contacts only
- define the minimum vendor-bill lifecycle that fits current product behavior
- make due-state and payment-state visibility operational without inventing a second disconnected expense system

### Constraints
- Do not move payables work into legacy `kbs_*` business tables.
- Do not bypass current org authorization, vendor contact resolution, or journal/payment locking behavior.
- Do not duplicate the expense system with a disconnected bill model.
- Do not invent deep procurement workflows if the current app only needs the payable bill lifecycle first.

### Done when
- vendor bill setup, due tracking, and payment visibility are grounded in the current expense/journal architecture
- payables behavior is org-safe and journal-safe
- the UI exposes only the current supported payable workflow
- workflow files are updated after completion
- `AI_CHANGELOG.md`, `AI_FEATURE_BACKLOG.md`, and `AI_NEXT_ACTIONS.md` are updated

---

## 3. Best Task After That

### Next Task
Replace Home dashboard receivables shortcuts with the server-side receivables summary.

### Why this is the best follow-up after vendor bills
- the reporting layer already has a server-side receivables summary and aging calculation.
- Home still uses the latest-100-open-invoices shortcut, which is now one of the clearest live accuracy gaps in the product.
- this is a contained reliability improvement after the larger payables expansion.

### Start here
- `plugins/khatabook/app/src/pages/Home.jsx`
- `plugins/khatabook/backend/Api/VyRestReports.php`
- `plugins/khatabook/backend/Helpers/ReportHelper.php`
- `plugins/khatabook/app/src/modules/reports/api.js`

### Minimum acceptable scope
- reuse the existing receivables summary endpoint
- keep the dashboard quick to load and operationally readable
- preserve current quick actions and recent activity sections
- remove client-side receivables math that duplicates server business logic

### Done when
- Home receivables and overdue cards are backed by the server-side summary
- the dashboard no longer undercounts large orgs because of the earlier latest-100 shortcut
- the UX remains consistent with current dashboard behavior

---

## 4. Third Task After That

### Next Task
Add rollback discipline to org member and invite writes.

### Why this comes here
- the core financial flows now have explicit transaction boundaries or compensating cleanup, but org-user flows still lag behind.
- org membership and invite operations are high-impact support workflows and should fail more predictably before more advanced product work resumes.
- this remains a contained reliability task inside the current org-management architecture.

### Start here
- `plugins/khatabook/backend/Api/OrgUsersController.php`
- `plugins/khatabook/backend/Helpers/OrgHelper.php`
- `plugins/khatabook/app/src/pages/UsersAdmin.jsx`
- current org/user tests in `plugins/khatabook/tests/*`

### Minimum acceptable scope
- inspect add/invite/remove/resend flows before editing
- add explicit transaction handling or compensating cleanup where practical
- preserve current permissions and email side effects
- keep the current UsersAdmin UX contract intact

### Done when
- the highest-risk invite/member write flows fail safely without leaving partial org state behind
- current permissions and email outcomes still match live behavior
- the org admin UI remains truthful about success and failure states

---

## 5. Fourth Task After That

### Next Task
Add later settlement flow for unpaid expenses.

### Why this comes here
- once vendor bills exist, unpaid expenses/bills still need a safe later payment path.
- this stays inside the current expense and journal model and closes the remaining payables lifecycle gap documented in tech debt.

### Start here
- `plugins/khatabook/backend/Api/VyRestExpenses.php`
- `plugins/khatabook/backend/Accounting/VyJournalEngine.php`
- `plugins/khatabook/app/src/modules/expenses/*`

### Minimum acceptable scope
- add payment/settlement for existing unpaid expense or bill records
- preserve journal integrity and current edit/archive rules
- avoid creating a second payment subsystem

### Done when
- existing unpaid expenses or bills can be settled safely after initial creation
- journal integrity and current expense restrictions still hold
- the UI does not expose unsupported settlement actions

---

## 6. Decision Checks Before Taking Other Work

### Quotations
Do not build quotation features until there is an explicit decision on whether quotations:
- remain legacy-only
- are rebuilt on `vy_*`
- or are removed from near-term scope

### Company settings breadth
Do not re-expand `CompanySettings.jsx` with hidden placeholder sections unless there is a confirmed live consumer for them.

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

- tighten or remove non-essential production console logging
- extract duplicated frontend async/query helpers without changing behavior
- improve workflow documentation around blocked decisions
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
