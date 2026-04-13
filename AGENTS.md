# AGENTS.md

This repository is the `plugins/khatabook` headless WordPress billing and business-control app. Build against the live product, not generic SaaS assumptions.

## Project Overview

- Host platform: WordPress plugin with React SPA frontend and REST backend.
- Active product areas: auth/onboarding, org membership, accounts, invoices, expenses, reports, invoice templates, settings, admin tooling.
- Source of truth for repo governance lives in `ai-workflow/`.

## Architecture Rules

- Keep the current shape:
  - bootstrap in `main.php`
  - REST controllers in `backend/Api`
  - shared rules in `backend/Helpers`
  - SPA in `app/src`
- Extend existing modules before inventing parallel systems.
- Do not add fake UI for unsupported backend behavior.
- Preserve current routing and auth patterns unless a confirmed bug or security issue requires change.

## Active Data Direction

- Canonical business schema: `vy_*`
- Supporting platform tables still in active use: `kbs_organizations`, `kbs_user_org_roles`, `kbs_pending_users`, `kbs_org_invites`, `kbs_settings`, and log tables.
- Do not add new business/accounting work to legacy `kbs_*` business tables.

## Mandatory Working Rules

- Inspect before change. Read relevant files in `ai-workflow/` first, then inspect the exact current backend and frontend code paths.
- Trace end to end before patching:
  - page or caller
  - API client
  - route registration
  - permission callback
  - org resolution
  - helper/business rules
  - tables touched
- Reuse existing helpers and controllers. Do not duplicate invoice, auth, org, template, or settings logic.
- Every write path must include validation, permission checks, org safety, and explicit error behavior.
- Prefer small complete fixes over broad rewrites.
- When work maps to items in `ai-workflow/product_feature_list.md`, update the companion product-feature tracker files as part of close-out.

## UX Rules

- This is an operational business product, not a marketing site.
- Keep UI clear, restrained, and trustworthy.
- One dominant job per screen.
- Lists need clear headers, primary action, filters, bounded results, loading, empty, and error states.
- Detail pages must show state, summary, and only valid actions.
- Forms must be direct, validated, and must not imply unsupported workflows.
- Follow `ai-workflow/AI_UX_DOCTRINE.md` for UX decisions.

## Testing Rules

- Add automated coverage when realistic in the current stack.
- Reuse the current PHP harness in `tests/`; do not build a new test framework casually.
- For UI-heavy changes, provide a concrete manual verification checklist.
- Do not claim a workflow is fixed without tracing and checking the full affected path.

## Workflow File Maintenance

After meaningful work, update the relevant files in `ai-workflow/`:

- Always: `AI_CHANGELOG.md`, `AI_FEATURE_BACKLOG.md`, `AI_NEXT_ACTIONS.md`
- When understanding changes: `AI_MASTER_BRIEF.md`, `AI_FILE_MAP.md`, `AI_TECH_DEBT.md`, `AI_SYSTEM_RULES.md`, `AI_UX_DOCTRINE.md`, `AI_IMPLEMENTATION_PLAYBOOK.md`
- When feature coverage changes against `product_feature_list.md`: `PRODUCT_FEATURE_STATUS.md`, `PRODUCT_FEATURE_PROGRESS_LOG.md`

Treat `ai-workflow/product_feature_list.md` as the requested product-scope reference.
Do not write status markers into that source list unless explicitly asked.
Keep live implementation status in:
- `ai-workflow/PRODUCT_FEATURE_STATUS.md`
- `ai-workflow/PRODUCT_FEATURE_PROGRESS_LOG.md`

## Blocker Handling

- If blocked, confirm the blocker from code, not assumption.
- Record confirmed debt in `AI_TECH_DEBT.md`.
- Record decision blockers or deferred work in `AI_FEATURE_BACKLOG.md` and `AI_NEXT_ACTIONS.md`.
- Do not ship speculative or half-wired work to “unstick” a task.

## Agents Must Avoid

- Broad framework rewrites without approval
- New product work on legacy business tables
- Duplicate auth, org, template, invoice, or settings logic
- Fake buttons, dead forms, placeholder modules presented as live product
- Trusting client org identifiers as authorization proof
- Stale workflow docs after meaningful changes
