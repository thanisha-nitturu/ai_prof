# Requirements Document

## Introduction

The course dashboard container (`block_myoverview`) currently renders with default Moodle styling that clashes with the site's custom Moove theme. The outer container has an inconsistent background, mismatched spacing, and a visual separation from the page that makes it feel like a foreign block rather than a native part of the site.

This feature covers improvements to the **dashboard container** — the outer wrapper, its background, padding, spacing, and how it integrates with the page layout — so it aligns with the site's established design system: `#f2f3f7` page background, `85%` max-width container, no rogue box-shadows, and the same card/block conventions used across the rest of the site.

All changes are scoped to the **Moove theme layer** (`theme/moove/`) via SCSS overrides, so core Moodle files remain untouched.

## Glossary

- **Dashboard_Container**: The outer `div.block-myoverview` wrapper rendered on the `/my/` page.
- **Page_Background**: The site's body background colour `#f2f3f7`, defined in `theme/moove/scss/moove/_variables.scss`.
- **Brand_Blue**: `#0f47ad` — the site's primary brand colour.
- **Accent_Blue**: `#205eff` — the site's interactive/accent colour.
- **Max_Width_Container**: The `85%` max-width, centred container pattern used across the site via `.moove-container-fluid` and `.container-fluid`.
- **Theme_Layer**: Files inside `theme/moove/` only (SCSS overrides).

---

## Requirements

### Requirement 1: Container Background Integration

**User Story:** As a student, I want the dashboard container to blend seamlessly into the page background, so that it does not look like a separate floating block.

#### Acceptance Criteria

1. THE Dashboard_Container SHALL use `Page_Background` (`#f2f3f7`) as its background colour.
2. THE Dashboard_Container SHALL have no visible `box-shadow` that separates it from the page.
3. THE Dashboard_Container SHALL have no visible `border` that outlines it against the page background.

---

### Requirement 2: Container Spacing and Width

**User Story:** As a student, I want the dashboard container to use the same spacing and width as the rest of the site's content areas, so that the layout feels consistent.

#### Acceptance Criteria

1. THE Dashboard_Container SHALL respect the site's `Max_Width_Container` pattern (`max-width: 85%`, centred with `margin: auto`).
2. THE Dashboard_Container SHALL use consistent vertical padding that aligns with the `1.25rem` top margin used by `#page-header` across the site.
3. WHEN viewed on screens narrower than `md` breakpoint, THE Dashboard_Container SHALL expand to `max-width: 95%` matching the site's responsive container behaviour.

---

### Requirement 3: Container Block Wrapper Suppression

**User Story:** As a student, I want the block chrome (title bar, block controls) around the dashboard to be hidden on the My Courses page, so that the dashboard content fills the page cleanly without a block frame.

#### Acceptance Criteria

1. WHILE on the `.page-mycourses` page, THE Dashboard_Container SHALL hide the `.block-controls` element.
2. THE Dashboard_Container block wrapper SHALL have `box-shadow: none` so it does not render the default Moodle block card shadow.

---

### Requirement 4: Course Card Grid Density

**User Story:** As a student, I want to see more courses at once on the dashboard, so that I can quickly scan all my enrolled courses without excessive scrolling.

#### Acceptance Criteria

1. WHEN the dashboard is displayed on a large screen (`lg` and above), THE Dashboard_Container SHALL render course cards in a 4-column grid (25% width per card) instead of the current 3-column grid (33.33%).
2. WHEN the dashboard is displayed on a medium screen (`md`), THE Dashboard_Container SHALL render course cards in a 3-column grid.
3. WHEN the dashboard is displayed on a small screen (below `md`), THE Dashboard_Container SHALL render course cards in a single-column layout (100% width).
4. THE Dashboard_Container card margin SHALL be reduced to `0.75rem` per card to accommodate the denser grid without overflow.

---

### Requirement 5: Theme Layer Isolation

**User Story:** As a developer, I want all dashboard container changes to live exclusively in the Moove theme layer, so that core Moodle files remain unmodified and upgrades are safe.

#### Acceptance Criteria

1. THE Theme_Layer SHALL contain all changes in `theme/moove/scss/moove/_mydashboard.scss` or `_blocks.scss`.
2. WHEN a Moodle core update is applied, THE Theme_Layer SHALL not require changes to any file outside of `theme/moove/`.

