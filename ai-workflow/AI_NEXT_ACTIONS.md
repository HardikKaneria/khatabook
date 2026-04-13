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

1. Fix only live issues that can be reproduced in manual QA across organization creation/switching and the current 4-template invoice preview/PDF behavior.
2. Keep AI invoice assistant, OCR, and quotation direction out of active execution unless explicitly approved.
3. Keep broader invoice-template direction changes blocked unless they solve a confirmed preview/PDF/runtime problem in the current 4-template catalog.

---

## 2. Best Next Run

### Primary Task
Run a reproduced QA bug-fix pass only for the latest organization/workspace and 4-template invoice-document flows.

### Why this should go first
- The prior default-safe queue is now complete, and the invoice-template stack was explicitly reset to the current 4-template reference-based catalog with authenticated settings preview loading.
- The known same-tab org-refresh gaps and the known template-settings truthfulness gaps were closed on 2026-04-12.
- The next safe work should be tied to real browser or PDF findings, not speculative feature churn.
- Remaining backlog items are blocked by product or dependency direction rather than missing implementation effort.

### Required reading before coding
Read these workflow files first:
- `ai-workflow/AI_MASTER_BRIEF.md`
- `ai-workflow/AI_SYSTEM_RULES.md`
- `ai-workflow/AI_FEATURE_BACKLOG.md`
- `ai-workflow/AI_TECH_DEBT.md`
- `ai-workflow/AI_IMPLEMENTATION_PLAYBOOK.md`

Then inspect these current code files:
- `plugins/khatabook/app/src/pages/UsersAdmin.jsx`
- `plugins/khatabook/app/src/App.jsx`
- `plugins/khatabook/app/src/layouts/DashboardLayout.jsx`
- `plugins/khatabook/backend/Api/OrgUsersController.php`
- `plugins/khatabook/app/src/modules/settings/invoices/InvoiceTemplatePreview.jsx`
- `plugins/khatabook/backend/Api/VyRestInvoicePreview.php`
- `plugins/khatabook/backend/templates/invoices/*`

### Exact target coverage for this run
Only act on confirmed QA findings in the latest completed flows:

- organization creation by company admins
- same-tab auth refresh after creating or switching organizations
- workspace switching visibility in the header and users screen
- invoice template preview output through authenticated `srcDoc` loading and correct HTML decoding
- invoice PDF alignment with the same direct template path
- invoice settings controls such as curated template fonts and safe primary/accent colors staying truthful with preview/PDF output

### Constraints
- Do not invent new product scope now that the unblocked queue is closed.
- Reproduce the bug first before patching.
- Keep blocked AI/OCR/quotation work out of the active queue without explicit approval.

### Done when
- any follow-up change is tied to a reproduced issue
- blocked roadmap items remain blocked
- workflow files are updated after completion
- `AI_CHANGELOG.md`, `AI_FEATURE_BACKLOG.md`, and `AI_NEXT_ACTIONS.md` are updated

If no new reproduced QA issue exists, stop after documenting that the active safe queue remains closed.

---

## 3. Best Task After That

### Next Task
Keep blocked roadmap work blocked until explicit approval exists.

### Why this is the best follow-up after the QA pass
- The remaining backlog items are blocked by missing dependencies or product direction.
- Future runs should not convert blocked ideas into active code work without explicit approval.

### Start here
- `plugins/khatabook/ai-workflow/AI_FEATURE_BACKLOG.md`
- `plugins/khatabook/ai-workflow/AI_TECH_DEBT.md`
- `plugins/khatabook/ai-workflow/AI_CHANGELOG.md`

### Minimum acceptable scope
- document the blocker from code if the user asks to move a blocked item forward
- do not start AI/OCR/quotation work by default
- keep the queue aligned with the inspected repository state

### Done when
- blocked work stays blocked until explicitly approved

---

## 4. Third Task After That

### Next Task
Use the documented fallback tasks only if a reproduced QA issue is not available.

### Why this comes here
- There is no remaining safe feature queue item that is both unblocked and unimplemented.
- Fallback work should stay inside active product surfaces and avoid roadmap churn.

### Start here
- `plugins/khatabook/ai-workflow/AI_TECH_DEBT.md`
- `plugins/khatabook/ai-workflow/AI_FEATURE_BACKLOG.md`
- whichever active module has the reproduced issue

### Minimum acceptable scope
- stay repo-grounded
- avoid blocked roadmap work
- keep any fallback improvement small and production-safe
- prefer fixes inside the current org/workspace shell or the current 4-template invoice stack

### Done when
- the run still produces a useful, code-backed improvement without drifting into blocked scope

---

## 5. Fourth Task After That

### Next Task
Keep AI invoice assistant, OCR, quotations, and single-template reversal work blocked until explicitly approved.

### Why this comes here
- the repo still has no AI provider/client/config path for an invoice assistant
- OCR bill extraction still lacks an attachment model and parser dependency
- quotation direction is still a product decision, not active execution work
- collapsing or re-expanding the current approved 4-template invoice system would reverse explicit product direction and should not be treated as default maintenance work

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

### Invoice template direction
Do not reopen invoice-template architecture churn by default. The current baseline is a 4-template catalog with direct per-template PHP/HTML documents, shared server-side data preparation, aligned preview/PDF rendering, and authenticated settings-preview loading.

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

When work changes coverage against `product_feature_list.md`, also update:
- `PRODUCT_FEATURE_STATUS.md`
- `PRODUCT_FEATURE_PROGRESS_LOG.md`

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

- improve invoice list scanability without changing route/data behavior
- tighten invoice form suggestion or validation behavior
- improve workflow documentation around blocked AI/OCR/template-direction decisions
- clarify legacy-vs-active schema notes where the code is easy to misread
- improve empty/loading/error states on already-active screens
- tighten validation or logging around an already-active live flow
- tighten same-tab org-refresh behavior on an already-active org-bound screen if a reproduced stale-workspace bug remains
- tighten preview/PDF setting truthfulness in the active 4-template catalog if a reproduced mismatch remains

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
