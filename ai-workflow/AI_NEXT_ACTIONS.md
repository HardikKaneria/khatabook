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

1. Expand reports using current `vy_*` data.
2. Consolidate operational logging.
3. Add safer handling for multi-step writes.
4. Tighten operational admin flows.
5. Reduce duplicated frontend async/query helpers.
6. Revisit bundle size and route-level loading only after operational gaps above are closed.

---

## 2. Best Next Run

### Primary Task
Expand reports using current `vy_*` data.

### Why this should go first
- Settings truthfulness, record history, and payment visibility are now in place, so the next highest-value gap is deeper operational reporting.
- The repository already has enough live `vy_*` invoice, expense, payment, and journal data to support better receivables and aging views without changing the data model.
- Stronger reports will make the current dashboard and accounting flows more useful immediately, while staying grounded in the current schema direction.

### Required reading before coding
Read these workflow files first:
- `ai-workflow/AI_MASTER_BRIEF.md`
- `ai-workflow/AI_SYSTEM_RULES.md`
- `ai-workflow/AI_FEATURE_BACKLOG.md`
- `ai-workflow/AI_TECH_DEBT.md`
- `ai-workflow/AI_IMPLEMENTATION_PLAYBOOK.md`

Then inspect these current code files:
- `plugins/khatabook/backend/Api/VyRestReports.php`
- `plugins/khatabook/backend/Helpers/ReportHelper.php`
- `plugins/khatabook/backend/Api/VyRestInvoices.php`
- `plugins/khatabook/backend/Api/VyRestExpenses.php`
- `plugins/khatabook/backend/Accounting/VyJournalEngine.php`
- `plugins/khatabook/app/src/modules/reports/*`
- `plugins/khatabook/app/src/pages/Home.jsx`

### Exact target coverage for this run
Expand reports on top of the current live business data only:

- add at least one receivables-focused report view
- add aging and/or invoice status visibility where the current tables support it
- extend report UI with strong loading, empty, and error states
- reuse current org filters and report helpers where possible
- keep calculations aligned with current journal/document truth

### Constraints
- Do not invent unsupported analytics or fake charts.
- Do not read from legacy `kbs_*` business tables for new report work.
- Do not bypass the current org-safe API path.
- Prefer server-side aggregates over dashboard-only client derivations where practical.

### Done when
- users can open a stronger report view sourced from live `vy_*` data
- report calculations match current invoice, expense, and payment logic
- empty/loading/error states are handled clearly
- workflow files are updated after completion
- `AI_CHANGELOG.md`, `AI_FEATURE_BACKLOG.md`, and `AI_NEXT_ACTIONS.md` are updated

---

## 3. Best Task After That

### Next Task
Consolidate operational logging.

### Why this is the best product expansion after report work
- the app now has both structured record history and older fragmented `error_log()` usage.
- report expansion will make operational trust more important, and logging should be easier to follow before more multi-step flows are hardened.
- this work can reuse the new `RecordAuditLogger` baseline instead of creating more one-off logging paths.

### Start here
- `plugins/khatabook/backend/Core/SystemLogger.php`
- `plugins/khatabook/backend/Core/RecordAuditLogger.php`
- `plugins/khatabook/backend/Api/VyRestInvoices.php`
- `plugins/khatabook/backend/Api/VyRestExpenses.php`
- `plugins/khatabook/backend/Email/EmailManager.php`
- `plugins/khatabook/backend/Api/SettingsController.php`

### Minimum acceptable scope
- identify high-value runtime failures still using raw `error_log()`
- standardize meaningful operational logs without exposing sensitive payloads
- keep DB-backed history for record changes distinct from broader operational logs
- document remaining places where raw PHP logging is still the only practical option

### Done when
- meaningful operational errors are easier to trace across email, settings, invoice, and expense flows
- the logging model is clearer for future work
- no new sensitive logging is introduced

---

## 4. Third Task After That

### Next Task
Add safer handling for multi-step writes.

### Why this comes here
- the live product now has more write-heavy flows, including invoice updates, payment posting, expense creation, approval, and org user/invite actions.
- logging cleanup should happen before changing failure handling so diagnostics remain coherent.
- this is a direct data-integrity improvement that does not require new product surface area.

### Start here
- `plugins/khatabook/backend/Api/VyRestInvoices.php`
- `plugins/khatabook/backend/Api/VyRestExpenses.php`
- `plugins/khatabook/backend/Admin/PendingUserController.php`
- `plugins/khatabook/backend/Api/OrgUsersController.php`
- `plugins/khatabook/backend/Accounting/VyJournalEngine.php`

### Minimum acceptable scope
- identify the highest-risk multi-table or side-effect-heavy flows
- add transaction handling or compensating behavior where the current architecture supports it
- prioritize invoice, expense, approval, and org-user write paths
- preserve current user-visible behavior while reducing partial-write risk

### Do not do
- do not redesign persistence around a new repository or service layer
- do not attempt a whole-plugin transaction abstraction before proving current high-risk paths

---

## 5. Fourth Task After That

### Next Task
Tighten operational admin flows.

### Why this comes here
- pending users, admin log views, and operational support tooling are functional but still uneven.
- this is safer to improve after report work, logging cleanup, and write-safety hardening because it depends less on core financial data changes.
- the app already has both wp-admin and SPA-backed support surfaces, so clarity and consistency work here has immediate operational value.

### Start here
- `plugins/khatabook/backend/Admin/*`
- `plugins/khatabook/backend/Api/AdminData.php`
- `plugins/khatabook/app/src/pages/UsersAdmin.jsx`
- `plugins/khatabook/backend/Core/SystemLogger.php`

### Minimum acceptable scope
- improve pending-user flow clarity
- improve admin log readability and navigation
- remove unsupported or misleading admin-side controls
- keep wp-admin pragmatic rather than redesigning it

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
