# AI Implementation Playbook

Base path: `/Users/hardikkaneria/Local Sites/khatabook/app/public/wp-content`

This playbook defines how future Codex runs must implement work in this repository.

It is not optional guidance.  
It is the execution standard for building, improving, and verifying this headless WordPress billing and business-control application.

The purpose of this playbook is to ensure future runs produce work that is:
- aligned with the real repository
- safe for org-scoped business data
- UX-consistent
- production-ready
- maintainable over time

---

## 1. Core Implementation Philosophy

### 1.1 Build from reality, not assumption
Every change must be based on:
- the current repository
- the actual active product flows
- the existing route, auth, org, helper, and DB behavior

Do not implement from memory, backlog wording alone, or earlier assumptions when current code can be read directly.

### 1.2 Prefer the smallest complete solution
Do not create wide speculative rewrites.

Prefer:
- small coherent improvements
- full end-to-end slices
- clear validation and permission handling
- stable UX over ambitious but fragile architecture changes

### 1.3 No fake completion
Do not:
- add UI before backend exists
- add settings for features with no real consumer
- expose actions the backend will reject
- leave half-built workflows active in production paths

A feature is either:
- supported
- clearly blocked
- clearly hidden
- clearly marked future

---

## 2. Mandatory Pre-Change Inspection

Before changing code for any feature or fix, inspect the exact current path.

### 2.1 Required inspection order
1. identify the live user-facing entry point
2. identify the frontend caller
3. identify the route registration
4. identify the permission callback
5. identify org resolution and auth behavior
6. identify shared helper/business-rule logic
7. identify exact DB tables read and written
8. identify current UI states:
   - loading
   - empty
   - error
   - forbidden
   - blocked action states

If any of the above is unclear, document the uncertainty before changing behavior.

### 2.2 Files that should usually be read first
Depending on the module, inspect from:
- `plugins/khatabook/app/src/App.jsx`
- `plugins/khatabook/backend/Endpoint/EndpointManager.php`
- relevant `backend/Api/*`
- relevant `backend/Helpers/*`
- relevant `app/src/modules/*`
- `backend/Db/TableManager.php`
- relevant workflow docs in `/ai-workflow`

---

## 3. Backend-First Execution Standard

### 3.1 Backend is the source of truth
Business behavior must be decided server-side.

Frontend must not become the authority for:
- org access
- record editability
- invoice state rules
- expense mutation rules
- template selection rules
- permission decisions

### 3.2 Backend-first sequence
For meaningful product work, prefer this order:
1. inspect current backend
2. add/adjust business rules
3. add/adjust validation
4. add/adjust permission checks
5. add/adjust DB behavior
6. expose safe API behavior
7. wire frontend
8. verify full flow

### 3.3 Never skip state rules
When changing backend behavior, define:
- allowed states
- blocked states
- invalid states
- duplicate actions
- destructive actions
- partial-write failure behavior

---

## 4. How To Add or Change REST Endpoints

Use the current repo patterns.

### 4.1 Endpoint checklist
Every new or changed REST endpoint must have:
- explicit route registration
- permission callback
- request validation
- server-side org resolution
- server-side membership/role enforcement
- safe read/write behavior
- useful success response
- useful error response

### 4.2 Standard endpoint process
1. register the route in:
   - `plugins/khatabook/backend/Endpoint/EndpointManager.php`
   - or the owning controller if that module already uses internal registration
2. reuse or add a permission callback
3. validate request params close to the REST boundary
4. resolve org server-side
5. reuse current helper logic where possible
6. return `WP_REST_Response` or `WP_Error`
7. confirm frontend expectations still match

### 4.3 Never add a write endpoint without
- permission enforcement
- org safety
- validation
- state rules
- explicit error behavior
- clear user-facing consequences

---

## 5. How To Reuse Existing Logic

Before creating new logic, inspect these first where relevant:

- `backend/Helpers/OrgHelper.php`
- `backend/Auth/OtpAuth.php`
- `backend/Auth/AuthSessionHelper.php`
- `backend/Helpers/InvoiceEditHelper.php`
- `backend/Helpers/InvoiceTemplateHelper.php`
- `backend/Helpers/InvoiceRenderHelper.php`
- `backend/Helpers/InvoiceTemplateRenderHelper.php`
- `backend/Helpers/InvoiceEmailHelper.php`
- `backend/Helpers/ReportHelper.php`

### 5.1 Reuse rules
- If a business rule already exists, call it rather than rewriting it.
- If logic appears in 2 or more live places, extract a small helper rather than copy/pasting.
- Do not create parallel logic for:
  - auth
  - org resolution
  - invoice edit rules
  - template resolution
  - current report aggregation
unless there is an explicit migration plan.

### 5.2 Acceptable extraction
Good extraction is:
- small
- clearly named
- repo-consistent
- used in more than one real place

Bad extraction is:
- abstract for its own sake
- framework-like
- too early
- disconnected from current patterns

---

## 6. Data Model and Persistence Rules

### 6.1 Canonical data direction
New business/accounting/product work must use:
- `vy_*`

Use `kbs_*` only where the current product already does:
- organizations
- roles
- invites
- pending users
- settings
- logs

### 6.2 Never do this casually
Do not extend legacy business tables like:
- `kbs_invoices`
- `kbs_invoice_items`
- `kbs_quotations`
- `kbs_quotation_items`
- `kbs_accounting_entries`

unless there is explicit approval after code inspection.

### 6.3 Multi-step write safety
Whenever a flow touches multiple tables, decide:
- can it fail partially?
- what cleanup is needed?
- should a transaction be used?
- should compensating failure behavior be added?

This is especially important for:
- invoice create/update/pay
- expense create/update
- approval/member creation flows
- settings flows with uploads or multiple state changes

---

## 7. Org and Permission Safety

Every org-scoped implementation must answer these explicitly:

- how is org resolved?
- how is membership enforced?
- which role is allowed?
- what happens if the active org is missing?
- what happens if the user loses org access mid-flow?
- what happens if the client sends an invalid org identifier?

### 7.1 Mandatory rules
- never trust client org identifiers as authorization proof
- always enforce org membership server-side
- preserve isolation for invoices, expenses, accounts, reports, and settings
- align with `kbs_user_org_roles` and current org helpers

---

## 8. Frontend Module Implementation Standard

Use the current module pattern:

- `api.js`
- `hooks.js`
- page/list/detail/form components

### 8.1 Preferred frontend sequence
1. confirm backend support exists
2. add or adjust `api.js`
3. add or adjust `hooks.js`
4. build page/list/detail/form UI
5. add route in `app/src/App.jsx` if needed
6. align with `DashboardLayout`
7. verify loading/empty/error/forbidden states
8. verify action-state restrictions

### 8.2 Frontend rules
Do not:
- introduce a second routing system casually
- bypass the current auth/api client path
- invent local business rules that backend should own
- build fake controls for incomplete backend behavior

### 8.3 UI pattern rule
All frontend work must follow:
- `AI_UX_DOCTRINE.md`

---

## 9. UX Completion Standard

A frontend change is not complete until it handles:
- loading state
- empty state
- error state
- not found state
- forbidden state
- blocked-action state
- success feedback
- safe destructive-action behavior

For critical business pages, users should always understand:
- what they are viewing
- current record state
- whether actions are allowed
- what to do next

---

## 10. Validation Standard

### 10.1 Validate at the boundary
Validate inputs:
- near REST entry
- near destructive action triggers
- on frontend where helpful for UX
- on backend as final authority

### 10.2 Validation must cover
- required fields
- type correctness
- org membership
- record ownership/scope
- allowed status transitions
- duplicate action prevention
- editability constraints

### 10.3 Error message quality
Validation errors should be:
- readable
- specific
- user-recoverable where practical

Avoid vague messages when a clear message is possible.

---

## 11. Testing and Verification Standard

### 11.1 Minimum verification
Every meaningful change requires:
- rereading the changed code path
- relevant automated checks when practical
- manual verification notes for user-facing workflows

### 11.2 Preferred verification by layer
- helper changes → targeted PHP tests
- controller changes → request-path or helper-backed tests where practical
- frontend workflow changes → manual browser checklist
- invoice document changes → preview + PDF verification
- settings changes → save/load/preview/reload verification

### 11.3 Never claim completion without path verification
Do not say a workflow is fixed end-to-end unless:
- backend path was checked
- frontend path was checked
- state handling was checked
- user-visible consequences were checked

---

## 12. Blocked Work Handling

If work is blocked:

1. confirm the blocker from code
2. state the blocker precisely
3. do not force speculative implementation
4. update:
   - `AI_FEATURE_BACKLOG.md`
   - `AI_NEXT_ACTIONS.md`
   - `AI_TECH_DEBT.md` if it reveals real debt

### 12.1 Common blocker types in this repo
- legacy schema without active module direction
- UI surface broader than backend support
- lifecycle-rule ambiguity
- roadmap-only features without current consumer
- missing source-of-truth decision between settings surfaces

---

## 13. Workflow File Maintenance

After meaningful work, update the relevant files in `/ai-workflow`.

### 13.1 Always update after meaningful work
- `AI_CHANGELOG.md`
- `AI_FEATURE_BACKLOG.md`
- `AI_NEXT_ACTIONS.md`

### 13.2 Update these when understanding changed
- `AI_MASTER_BRIEF.md`
- `AI_FILE_MAP.md`
- `AI_TECH_DEBT.md`
- `AI_SYSTEM_RULES.md`
- `AI_UX_DOCTRINE.md`
- `AI_IMPLEMENTATION_PLAYBOOK.md`

### 13.3 Workflow maintenance rule
Do not leave workflow docs stale after:
- architecture changes
- new routes
- module lifecycle changes
- decision clarifications
- meaningful product capability changes

---

## 14. Task Close-Out Checklist

Before finishing a run, confirm:

- current code was inspected first
- active architecture was preserved
- org and auth safety were handled
- validation is correct
- no duplicate logic path was introduced
- unsupported UI was not exposed
- loading/empty/error/blocked states were handled
- verification was done
- relevant workflow files were updated

---

## 15. What Future Runs Must Avoid

Do not:
- broadly rewrite framework structure without approval
- add new product work on legacy business tables
- treat roadmap ideas as live product without code evidence
- duplicate auth/org/template/invoice rule logic
- create decorative UI that outruns real capability
- merge partial work into active product paths
- use generic SaaS assumptions instead of repo reality