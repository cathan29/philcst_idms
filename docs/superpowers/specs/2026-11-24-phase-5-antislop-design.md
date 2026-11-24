# Phase 5 Antislop UI/UX Design

## Design read

Institutional operations console for registrar and ID-office staff, using a restrained PHILCST visual language. Dial: ENERGY 1 / RHYTHM 2 / MOTION 1.

## Direction

The interface will behave like a durable administrative workbench rather than a marketing dashboard. Navigation stays visible on working-size screens, operational pages prioritize their primary task and data table, and mobile layouts reflow into a compact labeled menu with contained table scrolling.

## Major decisions and reasons

- **Palette:** PHILCST purple identifies navigation and primary actions; a warm gold accent marks selected context; cool neutrals carry data-heavy surfaces without competing with content.
- **Typography:** the existing system sans stack remains because it is locally available, readable at compact sizes, and avoids a new network dependency.
- **Layout:** a fixed labeled sidebar and narrow top context bar keep destinations and the active term visible during repetitive office work.
- **Spacing:** a four-pixel scale with compact page and table spacing increases information density while retaining clear grouping.
- **Surfaces:** square-to-moderate radii and one-pixel borders replace repeated floating cards; shadows are reserved for overlays and the mobile navigation drawer.
- **Status:** restrained text-and-dot indicators preserve semantic labels without turning every value into a large pill.
- **Dashboard:** a single summary band separates unique-student counts from physical-card counts, followed by actual activity and shortcuts.
- **Motion:** only state transitions, hover feedback, and the mobile navigation drawer animate; reduced-motion preferences disable transitions.
- **Responsive behavior:** desktop, small-laptop, tablet, and phone states are distinct; tables scroll inside labeled regions while the document remains fixed to the viewport.

## Scope

The redesign changes Blade presentation, shared CSS, and non-business interaction markup only. Routes, request names, HTTP methods, authorization checks, scanner events, report calculations, database relationships, and lifecycle rules remain unchanged.

## Verification

Use an isolated test environment for rendered-page inspection. Verify the complete Laravel suite, Blade compilation, Vite build, JavaScript syntax, responsive overflow, keyboard focus, navigation, forms, tables, and the existing scanner workflow.
