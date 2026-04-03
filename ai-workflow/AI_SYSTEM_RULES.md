# AI System Rules

Base path: `/Users/hardikkaneria/Local Sites/khatabook/app/public/wp-content`

These are the operating rules for future Codex runs in this repository.

They are mandatory.

The goal is to ensure future runs stay:
- repo-grounded
- architecture-safe
- org-safe
- UX-consistent
- production-ready

---

## 1. Inspect Before Change

- Read the relevant `/ai-workflow` files first.
- Inspect the actual current backend and frontend files before editing.
- Trace the live path end-to-end:
  - SPA page or entry point
  - API caller
  - route registration
  - permission callback
  - org resolution path
  - helper/business-rule path
  - DB tables touched
- Do not patch from memory or prior assumptions when the code can be checked directly.

---

## 2. Preserve the Active Architecture

- Keep this as a single-plugin, headless WordPress business application.
- Preserve the current shape:
  - plugin bootstrap in `main.php`
  - REST controllers in `backend/Api`
  - shared rules in `backend/Helpers`
  - React/Vite SPA in `app/src`
- Do not introduce broad rewrites unless a confirmed bug or security issue truly requires it.
- Extend current modules before inventing parallel systems.

---

## 3. No Fake UI, No Fake Capability

- Do not add UI controls for unsupported backend actions.
- Do not leave dead buttons, half-wired forms, or misleading placeholder features in active product paths.
- If a flow cannot be completed safely, disable or isolate it clearly.
- Do not expose roadmap settings or modules as live product capability without evidence from current code.

---

## 4. Respect Active Data Direction

- Use `vy_*` tables for business/accounting/product record work.
- Use `kbs_*` only where the current product already does:
  - orgs
  - roles
  - invites
  - pending users
  - settings
  - logs
- Do not extend legacy `kbs_*` business tables for new product work unless explicitly approved after inspection.

---

## 5. Org and Data Safety Are Mandatory

- Never trust client org identifiers as authorization proof.
- Always enforce org membership or allowed org access on the server.
- Use `kbs_user_org_roles` and current org helpers for org-scoped access decisions.
- Keep active-org behavior consistent with the current server-side org model.
- Preserve invoice, expense, account, and report isolation by org.

---

## 6. Reuse Existing Logic

- Reuse current helpers, controllers, and established module patterns whenever possible.
- Do not duplicate business logic when one canonical path already exists.
- If a reusable rule is missing but needed in multiple places, extract a small helper rather than copying inline logic.

---

## 7. Validation and Edge Cases Are Required

Every meaningful change must handle:
- invalid input
- missing records
- forbidden access
- duplicate actions
- stale state
- unsupported edit states
- loading states
- empty states
- error states
- blocked-action explanation

Do not leave these implicit.

---

## 8. Production-Ready Quality Standard

- Prefer the smallest complete fix over wide speculative rewrites.
- Keep changes coherent, safe, and backward-aware.
- Preserve working flows unless a confirmed problem requires change.
- Do not merge “mostly wired” work into active product paths.

---

## 9. Frontend Consistency Rules

- Follow current SPA structure:
  - route in `App.jsx`
  - module `api.js`
  - module `hooks.js`
  - page/list/detail/form components
- Reuse:
  - `apiClient`
  - `makeDefaultApiFetch`
  - `authStorage`
  - `DashboardLayout`
  - current page container/header patterns
- Match the current UI tone:
  - operational
  - clear
  - restrained
  - document/business oriented
- Follow `AI_UX_DOCTRINE.md` for all UX decisions.

---

## 10. REST and Backend Safety Rules

Every new REST endpoint must have:
- explicit route registration
- permission callback
- request validation
- org-safe data access
- useful success and error responses

Prefer current auth utilities over ad hoc checks.  
Use WordPress-safe APIs for uploads, media, and mail.

---

## 11. Workflow File Maintenance Is Required

After meaningful work, update:

### Always
- `AI_CHANGELOG.md`
- `AI_FEATURE_BACKLOG.md`
- `AI_NEXT_ACTIONS.md`

### When understanding changed
- `AI_MASTER_BRIEF.md`
- `AI_FILE_MAP.md`
- `AI_TECH_DEBT.md`
- `AI_SYSTEM_RULES.md`
- `AI_UX_DOCTRINE.md`
- `AI_IMPLEMENTATION_PLAYBOOK.md`

Do not leave workflow docs stale after real product or architecture changes.

---

## 12. Document Debt and Blockers Explicitly

- If a problem is confirmed by code, record it in `AI_TECH_DEBT.md`.
- If work is blocked by a product or architecture decision, record it in:
  - `AI_FEATURE_BACKLOG.md`
  - `AI_NEXT_ACTIONS.md`
- Do not hide blockers in vague wording.

---

## 13. Verification Rules

- Add automated coverage when realistic in the current stack.
- If automation is not practical, provide a precise manual verification checklist.
- Do not claim an end-to-end workflow is fixed without checking the full affected path.

---

## 14. What Future Runs Must Avoid

Do not:
- broadly rewrite framework structure without approval
- add new product work on legacy business tables
- treat roadmap ideas as live product without code evidence
- duplicate auth/org/template/invoice/settings logic
- make the UI look more complete than the backend really is
- merge partial work into active product paths