# Trade Modal Styles Architecture

## Files
- `index.css`: single entrypoint imported by `TradeModalShell.vue`.
- `base.css`: shell layout, global modal frame, wallet, common controls.
- `forms-and-detail.css`: forms and item detail panel styles.
- `assortment.css`: assortment mode layout and cards.
- `dialogs.css`: image/owner dialogs and related UI blocks.
- `responsive-mobile.css`: mobile breakpoints.
- `states-and-icons.css`: shared states, inventory icon visuals, API note.
- `responsive-desktop.css`: large-screen breakpoints.

## Import order
Order in `index.css` follows the previous inline style cascade to avoid regressions.

## Sass decision
Current implementation stays in plain CSS to avoid dependency churn.
Structure is Sass-ready:
- each concern is already isolated,
- migration path is `*.css` -> `*.scss` with same import graph,
- optional next step: install `sass` + `sass-loader` and convert module-by-module.
