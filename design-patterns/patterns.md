# Earmark interface patterns

These conventions translate the accepted Earmark product research into a
shared presentation language. They are evidence-backed starting points, not a
copy of another product's styling. The code remains the source of truth when a
shipped pattern evolves.

## Navigation and page jobs

- Primary navigation follows household jobs: **Overview, Plan, Transactions,
  Accounts, and Net Worth**.
- Overview orients the household and links to source pages. It does not repeat
  a register, plan editor, or projection workspace.
- Only implemented destinations are links. A destination may appear as a
  clearly disabled, labelled item while its page is still being built; it must
  not be a dead link.
- Transfers belong to transaction workflows, Buckets and Categories belong to
  Plan configuration, and Members belongs to Household settings. Addressable
  routes can remain without becoming primary navigation peers.

## Page hierarchy and responsive layout

- Lead with the household outcome or current state, then supporting detail,
  then editing controls. On narrow screens, results and milestones stay ahead
  of charts and editors.
- Use semantic theme tokens and the existing shared UI components. Solid
  borders contain real content; dashed borders signal setup or empty states.
- Pages reflow to one column at narrow widths. A wide data table may scroll in
  its own region, but the page itself must not require horizontal scrolling.
- Summary cards answer one named household question and offer a route to the
  complete source page when further work is possible.

## Financial display

- Financial rows use the shared money-row anatomy: a plain-language label,
  optional supporting context, and one display-ready amount aligned for easy
  comparison.
- Formatting is consistent across pages, but shared presentation components do
  not calculate totals, signs, freshness thresholds, or projection values.
  Those arrive as explicit, display-ready props from the owning application
  seam.
- Owner provenance and valuation freshness stay visible beside financial
  positions and summaries. Household authorization and an individual's
  ownership are described separately.
- Use **financial position**, **valuation**, **investable assets**, **total net
  worth**, **household plan**, **contribution phase**, **projection**, and
  **snapshot** as defined in `CONTEXT.md`.

## Empty, error, and loading states

- An actionable empty state names what is missing, explains why the page is
  useful, and offers one primary next action. Dependent pages must not invent
  conflicting starter data.
- A no-results state preserves the user's filters and offers clear/reset. It is
  not the same as a first-use empty state.
- Validation errors preserve entered values, focus a named summary, link each
  summary message to its field, and repeat the specific message inline.
  Access-denied states remain distinct from validation failures.
- Loading skeletons preserve the hierarchy and approximate space of the final
  content instead of replacing the page with an unrelated spinner.

## Accessibility and charts

- Interactive controls have visible text or an explicit accessible name and a
  visible keyboard focus state. Disabled destinations explain their status.
- Charts use a visible caption, `role="img"`, and an accessible name. An
  adjacent structured table or equivalent text exposes every important value.
- Colour is never the only scenario distinction; labels and line patterns carry
  the same meaning. Tables use captions and structural headers.
- Test hooks use `data-test="kebab-case-name"` and name the behavior or region,
  not its styling.

## Evidence

These conventions come from
`docs/research/net-worth-projection-product-research.md`: its Earmark repository
inventory; expert standards SWDP-020, SWDP-070, SWDP-076, EDA-001, and EDS-093;
the documented Monarch, YNAB, Empower, Origin, Rocket Money, Copilot Money, and
Quicken patterns; WCAG 2.2 and W3C chart/table guidance; and the GOV.UK linked
error-summary pattern.
