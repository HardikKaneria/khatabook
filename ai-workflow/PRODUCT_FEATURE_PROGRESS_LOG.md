# Product Feature Progress Log

Base path: `/Users/hardikkaneria/Local Sites/khatabook/app/public/wp-content`

This file is the append-only history for changes against:
- `plugins/khatabook/ai-workflow/product_feature_list.md`

Use it to record:
- which requested product features moved forward
- what changed in code
- which status changes were made in `PRODUCT_FEATURE_STATUS.md`
- what is still missing after the change

## Update Rules

After meaningful feature work:

1. update `PRODUCT_FEATURE_STATUS.md`
2. append a new entry here
3. update the normal workflow files:
   - `AI_CHANGELOG.md`
   - `AI_FEATURE_BACKLOG.md`
   - `AI_NEXT_ACTIONS.md`

Do not use this file for speculative roadmap ideas.  
Only log code-backed changes.

## Entry Template

```md
## YYYY-MM-DD - Short Feature Summary

### Feature List Areas Affected
- ...

### Status Changes
- `Section` — `OLD` -> `NEW`

### Code Evidence
- `path/to/file`

### Notes
- ...
```

## 2026-04-12 - Baseline Product Feature List Audit

### Feature List Areas Affected
- Entire `product_feature_list.md` baseline was compared against the current repo.

### Status Changes
- Initial baseline created in `PRODUCT_FEATURE_STATUS.md`.

### Code Evidence
- `plugins/khatabook/main.php`
- `plugins/khatabook/backend/Endpoint/EndpointManager.php`
- `plugins/khatabook/backend/Db/TableManager.php`
- `plugins/khatabook/backend/Api/VyRestInvoices.php`
- `plugins/khatabook/backend/Api/VyRestExpenses.php`
- `plugins/khatabook/backend/Api/VyRestReports.php`
- `plugins/khatabook/backend/Api/VyRestContacts.php`
- `plugins/khatabook/backend/Api/VyRestAccounts.php`
- `plugins/khatabook/backend/Api/OrgUsersController.php`
- `plugins/khatabook/backend/Api/VyRestInvoiceSettings.php`
- `plugins/khatabook/backend/Api/VyRestInvoicePreview.php`
- `plugins/khatabook/app/src/App.jsx`
- `plugins/khatabook/app/src/pages/Home.jsx`
- `plugins/khatabook/app/src/pages/CompanySettings.jsx`
- `plugins/khatabook/app/src/modules/invoices/*`
- `plugins/khatabook/app/src/modules/expenses/*`
- `plugins/khatabook/app/src/modules/contacts/*`
- `plugins/khatabook/app/src/modules/accounts/*`
- `plugins/khatabook/app/src/modules/reports/*`
- `plugins/khatabook/app/src/modules/settings/invoices/*`

### Notes
- The repo already has a strong live billing core: auth, orgs, accounts, contacts, invoices, expenses, payables/receivables reporting, billing health, daily brief, revenue leak, invoice risk, and invoice templates.
- The largest remaining or blocked gaps from the product feature list are quotations, proforma invoices, item/service master, OCR, reconciliation, customer portal, collaboration features, approval engine, notification center, and AI/provider-backed assistants.
- The old “10 premium invoice templates” request is no longer current-product scope. The live repo now uses an approved 4-template catalog.
