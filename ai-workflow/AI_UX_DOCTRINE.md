# AI UX Doctrine

Base path: `/Users/hardikkaneria/Local Sites/khatabook/app/public/wp-content`

This doctrine defines how future UI and UX work should behave in this repository.

It is not a visual-style wishlist.  
It is the operational UX standard for a headless WordPress billing and business-control application.

The goal is to make the product feel:
- fast
- trustworthy
- clear
- operational
- enterprise-ready
- low-friction

This product is not a marketing website and should never be designed like one.

---

## 1. Core Product UX Philosophy

### 1.1 What this product is
This is an operational business application for:
- billing
- expenses
- contacts
- accounts
- reporting
- settings
- internal business workflows

The interface must help users:
- understand current business state quickly
- complete actions accurately
- avoid mistakes
- recover from errors clearly
- trust the data and the system

### 1.2 What this product is not
Do not design this like:
- a flashy SaaS landing page
- a design experiment
- a dashboard full of decorative widgets
- a playful consumer app
- a form-heavy old ERP clone

### 1.3 UX values
All product decisions should favor:
- clarity over cleverness
- speed over ornament
- accuracy over novelty
- consistency over one-off creativity
- operational confidence over visual drama

---

## 2. Primary UX Principles

### 2.1 Users must understand 5 things quickly
Every major screen should make these obvious within a few seconds:
- where they are
- which organization they are acting inside
- what the primary action is
- what the current state of the record or page is
- what they can do next

### 2.2 One dominant job per screen
Each page should have one dominant purpose.

Examples:
- list page → browse, filter, create
- detail page → understand status, inspect details, take actions
- form page → complete or edit a record
- settings page → configure and save
- dashboard → understand current business situation and take quick actions

Do not overload one screen with too many unrelated jobs.

### 2.3 Business state must always be visible
The UI should never make users guess:
- status
- due state
- payment state
- approval state
- archive state
- restriction state
- org context

### 2.4 The UI must never pretend
Do not expose:
- fake buttons
- unsupported actions
- placeholder widgets disguised as live features
- settings that have no real product effect unless clearly marked
- incomplete flows that appear production-ready

If something is not supported, the UI must say so clearly or hide it.

---

## 3. Global Layout Standards

### 3.1 Dashboard shell behavior
Inside authenticated screens:
- keep page framing consistent
- keep navigation stable
- keep org switcher visible and understandable
- keep logout and user actions predictable
- avoid layout jumping between pages

### 3.2 Page header standard
Every major page should have:
- eyebrow or section label where useful
- page title
- short operational subtitle
- primary action in the top-right
- optional secondary actions only if truly useful

### 3.3 Page width and density
Use a comfortable business density:
- avoid overly wide unreadable layouts
- avoid cramped dense ERP-style layouts
- maintain readable spacing
- prioritize scanability and form usability over aesthetic minimalism

### 3.4 Section structure
Use sections with clear hierarchy:
- summary first
- actions second
- details after
- secondary tools last

The most important information should appear earlier, not buried.

---

## 4. List Page Doctrine

Examples in current code:
- `app/src/modules/invoices/InvoicesPage.jsx`
- `app/src/modules/expenses/ExpensesPage.jsx`
- `app/src/modules/accounts/AccountsPage.jsx`
- `app/src/pages/UsersAdmin.jsx`

### 4.1 Purpose of list pages
List pages exist to:
- scan records
- filter quickly
- search quickly
- understand status at a glance
- enter new records
- jump into detail pages

### 4.2 Required structure
Every list page should contain:
1. page header
2. primary action
3. filter/search row
4. one dominant results surface
5. pagination or bounded results behavior
6. empty state
7. loading state
8. error state

### 4.3 Filters
Filters should be:
- top-aligned
- simple first
- expandable only if necessary
- consistent between modules where possible

Preferred filters:
- search
- status
- date range
- type
- organization-sensitive filters where relevant

### 4.4 Table/list behavior
Tables should prioritize:
- readability
- stable columns
- useful default sorting
- obvious row actions
- status visibility
- financial clarity

Avoid:
- too many columns by default
- tiny unreadable action targets
- row actions that are ambiguous
- hidden important financial states

### 4.5 Empty states
Every empty state should:
- name the record type
- explain why the page is empty
- show the most logical next action

Bad:
- “No data”

Good:
- “No expenses recorded yet.”
- “Create your first contact to start using customers and vendors in invoices and expenses.”

### 4.6 Bulk actions
Bulk actions should only exist if:
- they have real operational value
- the backend supports them safely
- the action is common enough to justify UI complexity

Do not add bulk actions just because tables commonly have them.

---

## 5. Detail Page Doctrine

Examples in current code:
- `app/src/modules/invoices/InvoiceDetailPage.jsx`
- `app/src/modules/expenses/ExpenseDetailPage.jsx`

### 5.1 Purpose of detail pages
Detail pages should help users:
- understand the record immediately
- see current state and restrictions
- take the next valid action
- inspect full supporting details
- review activity/history if available

### 5.2 Required structure
A detail page should generally include:
1. title and record identifier
2. status and state summary
3. top action bar
4. key metadata strip
5. financial summary if relevant
6. main detail sections
7. related actions or related records
8. activity/history section where available

### 5.3 Action visibility
Only show actions that are:
- supported by backend
- allowed in the current record state
- meaningful for this user/role

If an action is disabled, explain why.

### 5.4 Financial readability
For billing and expense records:
- amounts must be prominent
- status must be obvious
- due/payment state must be visible
- important dates must be readable
- linked supporting entities must be obvious

### 5.5 Audit and history
As audit/history features are added, detail pages should become the primary place to show:
- changes over time
- who changed the record
- why an important action happened
- payment or approval history
- important internal notes if supported

---

## 6. Form Doctrine

Examples in current code:
- `app/src/modules/invoices/InvoiceForm.jsx`
- `app/src/modules/expenses/ExpenseForm.jsx`
- `app/src/pages/UsersAdmin.jsx`
- `app/src/modules/settings/invoices/InvoiceSettingsPage.jsx`

### 6.1 Purpose of forms
Forms should help users:
- enter data quickly
- understand what is required
- avoid preventable mistakes
- save confidently
- recover from errors clearly

### 6.2 Form structure
Forms should:
- group fields by task
- use clear section headings where helpful
- avoid long unstructured field walls
- place related fields together
- keep destructive actions clearly separate from save actions

### 6.3 Labels and hints
Use:
- clear labels
- short helpful hints
- obvious required fields
- readable validation messages

Avoid:
- technical field names exposed to business users
- overly verbose helper text
- hidden required logic

### 6.4 Save behavior
Every form should make save behavior obvious:
- save
- cancel
- close
- archive/delete if relevant
- draft/finalize if relevant

Do not mix multiple conflicting save patterns.

### 6.5 Validation behavior
Validation should be:
- visible
- timely
- understandable
- specific

Where practical:
- catch issues before server submission
- keep server validation as the final authority
- map server validation errors cleanly back into the UI

### 6.6 Modal vs full page rule
Use a modal only when:
- scope is tight
- few fields are needed
- the user does not need large contextual review

Use a full page when:
- the workflow is important
- the form is large
- related summaries matter
- actions can affect critical business state

Do not force a large workflow into a modal.

---

## 7. Dashboard Doctrine

Current state:
- `app/src/pages/Home.jsx` is still a placeholder

### 7.1 Purpose of the dashboard
The dashboard should help the user answer:
- what needs attention now
- what money is pending
- what happened recently
- what needs action next

### 7.2 Dashboard rules
Use:
- real current product data only
- action-oriented summaries
- recent activity
- business state visibility
- quick-create actions

Avoid:
- fake graphs
- vanity metrics
- decorative cards with no next action
- analytics that do not map to real queries

### 7.3 First dashboard version should prioritize
- receivables
- recent invoices
- recent expenses
- balance summaries
- tax/profit snapshots
- due/overdue summaries
- alerts
- quick actions

### 7.4 Dashboard widget rule
Every widget should do at least one of:
- explain current business state
- show risk or attention needed
- help users act quickly

If it does neither, it should not exist.

---

## 8. Settings Doctrine

Examples in current code:
- `app/src/pages/CompanySettings.jsx`
- `app/src/modules/settings/invoices/InvoiceSettingsPage.jsx`

### 8.1 Purpose of settings
Settings pages should:
- define a clear source of truth
- show what is configurable now
- avoid implying fake capability
- make save results predictable

### 8.2 Settings rules
- only expose settings that are active or clearly marked
- make save actions explicit
- show useful save feedback
- make scope obvious
- show preview when safe and practical
- separate live operational settings from future or uncertain settings

### 8.3 Document/output settings
Settings that affect invoices, previews, PDFs, or emails must feel:
- deterministic
- previewable
- safe
- reversible where practical

### 8.4 Uploads
If backend supports true uploads:
- use real upload UX
- show preview
- allow replace/remove
- validate file type and size
- do not force raw URL-only workflows when upload exists

---

## 9. Status, Tags, and State Communication

### 9.1 Status rules
Every meaningful status should:
- exist in both list and detail views where relevant
- map to real backend state
- be readable at a glance
- use restrained styling

### 9.2 Blocked action rules
If an action is blocked:
- say why
- say what the user can do next if applicable
- do not silently fail
- do not leave confusing disabled controls with no explanation

### 9.3 State communication
Users should always understand:
- editable vs non-editable
- paid vs unpaid
- due vs overdue
- active vs archived
- pending vs approved
- enabled vs configured but inactive

---

## 10. Loading, Empty, Error, and Recovery States

### 10.1 Loading states
Every async page or major section needs:
- visible loading feedback
- stable layout where possible
- no unexplained blank screens

### 10.2 Empty states
Every empty state should:
- name the thing that is missing
- explain whether that is normal
- show the next action

### 10.3 Error states
Every error state should:
- be readable
- be specific enough to understand
- support recovery when possible
- avoid raw technical noise unless needed for internal/admin use

### 10.4 Recovery paths
Good error UX often includes:
- retry
- go back
- create first record
- check permissions
- contact admin if needed

---

## 11. Permissions and Restricted UX

### 11.1 Permission-aware UI
The UI should respect user ability:
- hide actions that are truly unavailable
- or show them with clear blocked reasons when that teaches the user something useful

### 11.2 Forbidden states
If the user lands on something they cannot access:
- explain that clearly
- do not show broken page shells
- provide a route back to a valid area

### 11.3 Role-sensitive action design
Admin, manager, finance, accountant, and staff behavior may differ.  
The UI should remain coherent without becoming fragmented.

---

## 12. Operational Consistency Rules

Use the strongest current UI references:
- `app/src/modules/settings/invoices/InvoiceSettingsPage.jsx`
- `app/src/pages/UsersAdmin.jsx`
- `app/src/layouts/DashboardLayout.jsx`
- `app/src/modules/invoices/InvoiceDetailPage.jsx`

Consistency rules:
- one primary action per page
- stable page framing
- calm spacing and typography
- clear financial hierarchy
- predictable action placement
- predictable success/error patterns
- predictable list/detail/form behavior across modules

---

## 13. Responsive and Device Behavior

### 13.1 Responsive principle
This product should remain usable on smaller screens, but desktop/business usability remains the primary standard.

### 13.2 Mobile behavior rules
On smaller screens:
- stack sections cleanly
- keep primary actions visible
- avoid unreadable tables
- use overflow responsibly
- preserve financial clarity

### 13.3 Do not sacrifice desktop operational efficiency
Do not over-optimize for mobile in a way that harms serious desktop usage.

---

## 14. Content and Microcopy Rules

### 14.1 Tone
Use microcopy that feels:
- calm
- clear
- trustworthy
- operational
- not robotic
- not playful

### 14.2 Writing style
Use:
- clear nouns
- direct verbs
- concise explanations
- specific error messages

Avoid:
- jargon when not needed
- dramatic success copy
- vague labels like “Manage”
- generic messages like “Something went wrong” without context

---

## 15. Destructive and Sensitive Actions

### 15.1 Destructive action rules
Delete, archive, cancel, or irreversible actions must:
- be clearly labeled
- use confirmation where appropriate
- explain impact when needed
- be visually separate from primary save actions

### 15.2 Sensitive actions
For actions affecting financial integrity:
- expose caution
- show reason when blocked
- align exactly with backend rules
- do not allow accidental triggering

---

## 16. UX Standards for Future Modules

When future modules are added, they should follow the same doctrine:
- contacts
- recurring billing
- credit/debit notes
- statements
- approvals
- customer portal
- reconciliation
- AI features

No future module should introduce a totally different interaction language unless there is a compelling product reason.

---

## 17. What Future Runs Must Avoid

Do not introduce:
- flashy dashboard gimmicks
- fake charts with no real data source
- decorative complexity that slows business work
- actions that outrun backend support
- status labels that do not map to actual backend state
- inconsistent save patterns
- oversized modal workflows
- mixed interaction logic for similar modules
- UI that looks more complete than the real product is

---

## 18. Definition of Good UX in This Product

A screen is good only if:
- users understand it quickly
- users know what to do next
- users can complete the task with low friction
- states are visible and trustworthy
- errors are recoverable
- the UI matches real backend capability
- the screen feels consistent with the rest of the product