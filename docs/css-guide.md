# CSS Guide

`assets/css/main.css` is an `@import` index only — all rules live in the files below.

---

## File → What It Styles

| File | Class prefixes / selectors |
|---|---|
| `brand.css` | `:root` brand tokens only (upstream from brandhub) |
| `tokens.css` | `:root` app tokens — `--surface-*`, `--line`, `--text`, `--muted`, `--shadow-*`, `--radius-*`, `--space-*`, `--canoe/run/lift/…`, `--fatigue-color-*`, `--fatigue-row-*`, `--fatigue-glow-*` |
| `base.css` | `*`, `html`, `body`, `body::before`, `a`, `button`, shared mono-font selector list |
| `loading.css` | `#loadScreen`, `.load-logo`, `.load-logo-brand`, `.load-logo-sub`, `.load-spinner`, `.load-msg`, `.load-error-box`, `.load-error-msg`, `.load-actions`, `@keyframes spin` |
| `buttons.css` | `.retry-btn`, `.window-btn`, `.nav-btn`, `.filter-pill`, `.tl-sw-btn`, `.gen-btn`, `.icon-btn`, `.btn-icon`, `.refresh-btn`, `.refresh-icon`, `.a-card-btn`, `.detail-close`, `.tl-mini`, `.overview-recent-link` — all hover/focus/active states |
| `layout.css` | `#app`, `.app-shell`, `.site-header`, `.site-title`, `.site-subtitle`, `.brand-block`, `.header-row`, `.header-controls`, `.window-controls`, `.window-switcher`, `.window-option`, `.control-label`, `.range-summary`, `.data-sync-stamp`, `.data-status-banner`, `.nav`, `.nav-btn` sizing, `.app-main`, `.view`, `.section-shell`, `.dashboard-shell`, `.fatigue-shell` |
| `dashboard.css` | `.header-stats`, `.overview-card`, `.overview-kpi`, `.overview-chart`, `.overview-recent`, `.overview-insight`, `.overview-label`, `.overview-value`, `.overview-meta`, `.overview-foot`, `.overview-chart-shell`, `.overview-donut`, `.overview-donut-center`, `.overview-donut-kicker`, `.overview-donut-value`, `.overview-legend`, `.overview-legend-item`, `.overview-legend-dot`, `.overview-legend-meta`, `.overview-chip-row`, `.overview-chip`, `.section-title`, `.overview-insight-title`, `.overview-empty`, `.overview-recent-list`, `.overview-recent-link`, `.overview-recent-*` |
| `fatigue.css` | `.overview-heatmap-shell`, `.overview-fatigue-top`, `.overview-body-figure`, `.overview-body-caption`, `.overview-body-svg`, `.fatigue-body-outline`, `.fatigue-body-region`, `.overview-fatigue-legend`, `.overview-fatigue-board`, `.overview-fatigue-column`, `.overview-fatigue-table`, `.overview-fatigue-tooltip`, `.overview-fatigue-swatch`, `.overview-fatigue-scale-*`, `.overview-fatigue-status-*`, `.overview-fatigue-row-*`, `.overview-fatigue-token`, `.overview-fatigue-empty`, `[data-fatigue-state]` tier color rules |
| `sessions.css` | `.sessions-toolbar`, `.feed-display-tools`, `.section-kicker`, `.filter-row`, `.filter-pill` extras, `.filter-count`, `.cards`, `.a-card`, `.a-card-*`, `.a-card-compact`, `.metric`, `.metric-val`, `.metric-lab`, `.a-card-desc`, `.empty-window`, `.tl-period`, `.tl-period-header`, `.tl-period-title`, `.tl-grid`, `.tl-mini`, `.tl-mini-*`, `.bar-group`, `.bar-row`, `.bar-name`, `.bar-track`, `.bar-fill`, `.bar-num`, `.highlight-list`, `.hl-item` |
| `modal.css` | `.detail-overlay`, `.detail-modal`, `.detail-head`, `.detail-kicker`, `.detail-kicker-*`, `.detail-label`, `.detail-title`, `.detail-date`, `.detail-close`, `.detail-body`, `.detail-metrics`, `.detail-meta`, `.detail-section`, `.detail-note`, `.detail-facts`, `.detail-fact`, `.detail-fact-val`, `.hevy-list`, `.hevy-exercise`, `.hevy-ex-name`, `.hevy-set-list`, `.hevy-set`, `::-webkit-scrollbar` |
| `ai.css` | `.ai-pane`, `.ai-head`, `.ai-desc`, `.api-note`, `.recent-preview`, `.rp-label`, `.rp-row`, `.rp-name`, `.rp-date`, `.ai-thinking`, `.ai-output`, `.ai-output-label`, `.ai-output-text`, `.ai-top-btn`, `.gen-btn` variant, `@keyframes blink` |
| `fatigue-doc.css` | `.fatigue-doc`, `.fatigue-doc-summary-*`, `.fatigue-doc-body`, `.fatigue-doc-intro`, `.fatigue-doc-grid`, `.fatigue-doc-section`, `.fatigue-doc-heading`, `.fatigue-doc-text`, `.fatigue-doc-table`, `.fatigue-doc-code`, `.fatigue-doc-formula`, `.fatigue-doc-tiers`, `.fatigue-doc-tier`, `.fatigue-doc-tier-*` |
| `responsive.css` | All `@media (max-width: 1024px)` and `@media (max-width: 920px)` overrides |

---

## Custom Property Reference

### Design tokens (tokens.css)

| Variable | Purpose |
|---|---|
| `--page-bg` | Page background |
| `--surface-shell` | Section shell / header background |
| `--surface-card` | Card background |
| `--surface-card-soft` | Lighter card variant |
| `--surface-chip` | Pill/chip background |
| `--surface-chip-hover` | Pill/chip hover background |
| `--line` | Subtle border color |
| `--line-strong` | Stronger border color |
| `--line-brand` | Brand-tinted border (active states) |
| `--text` | Primary text |
| `--muted` | Secondary/label text |
| `--muted-strong` | Mid-weight text |
| `--brand-soft` | Brand tint background |
| `--shadow-sm` / `--shadow-md` | Box shadows |
| `--radius-shell` / `--radius-card` / `--radius-card-sm` / `--radius-sm` / `--radius-xs` | Border radius scale |
| `--space-1` … `--space-8` | Spacing scale (6px … 60px) |

### Activity type colors

`--canoe`, `--run`, `--lift`, `--yoga`, `--ride`, `--walk`, `--hike`, `--water`, `--row`, `--surf`, `--workout`

### Fatigue colors

| Variable | Used on |
|---|---|
| `--fatigue-color-none` | Body map — untrained region fill |
| `--fatigue-color-fresh` | Body map — fresh region fill |
| `--fatigue-color-recovering` | Body map — recovering fill |
| `--fatigue-color-fatigued` | Body map — fatigued fill |
| `--fatigue-color-high` | Body map — highly fatigued fill |
| `--fatigue-row-*` | Table row text colors per tier |
| `--fatigue-glow-high` / `--fatigue-glow-fresh` / `--fatigue-glow-none` | Drop-shadow glows on body map |

### Per-element custom properties (set inline by JS)

| Variable | Set by | Used on |
|---|---|---|
| `--donut-fill` | `renderDashboard` | `.overview-donut` |
| `--legend-color` | `renderDashboard` | `.overview-legend-dot` |
| `--chip-color` | `renderDashboard` | `.overview-chip::before` |
| `--card-accent` | `renderActivityCards` | `.a-card::before` accent bar |
| `--fatigue-fill` | `renderFatigueBodyFigure` | `.fatigue-body-region` SVG fill |
| `--fatigue-opacity` | `renderFatigueBodyFigure` | `.fatigue-body-region path` opacity |
| `--fatigue-tier-color` | `[data-fatigue-state]` CSS rules | Status labels and swatches |

---

## Naming Conventions

- **`overview-*`** — dashboard and fatigue overview components
- **`overview-fatigue-*`** — fatigue-specific components within the overview
- **`a-card-*`** — activity card components (`a-card` = "activity card")
- **`tl-*`** — timeline view components (`tl` = "timeline")
- **`detail-*`** — activity detail modal components
- **`hevy-*`** — Hevy workout breakdown within the detail modal
- **`rp-*`** — recent preview rows in AI pane (`rp` = "recent preview")
- **`ai-*`** — AI coaching pane components
- **`load-*`** — loading screen components
- **`fatigue-doc-*`** — in-page fatigue model documentation accordion
- **`nav-btn`** — top navigation tab buttons
- **`window-btn`** — date range switcher buttons
- **`filter-pill`** — activity type filter pills
- **`tl-sw-btn`** — timeline/list view switch buttons
