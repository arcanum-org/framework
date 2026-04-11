# Design System: Arcanum Framework

## 1. Visual Theme & Atmosphere

Arcanum's visual identity draws from the world of craft and quiet scholarship — illuminated manuscripts, artisan workshops, well-worn notebooks. The framework's vocabulary (Codex, Quill, Forge, Rune, Parchment, Atlas) already tells this story; the design makes it visible. Every surface feels like it was made by someone who cares about the details.

The canvas is warm cream (`#faf8f1`) — not white, never white. It has the subtle warmth of unbleached paper, creating a foundation that feels human rather than clinical. Text is set in warm near-black (`#2c2a25`) with a faint brown undertone, as if written in aged ink. There are no cool grays anywhere in the system — every neutral carries warmth.

Headlines use a serif typeface (Lora) that gives every heading the weight and authority of a chapter title. Body text uses Inter for quiet, readable efficiency. Code uses JetBrains Mono. This three-font system creates a clear hierarchy: serifs command attention, sans-serif handles utility, monospace marks code. The serif is never decorative — it's structural, used to establish importance.

The signature accent is burnt copper (`#b5623f`) — a warm, earthy tone halfway between terracotta and amber. It evokes heated metal in a forge, ink mixed from natural pigments, the gilded edge of an old book. Against the cream canvas it's striking without being aggressive. It says "craft" rather than "tech."

Dark mode inverts the warmth: deep olive-black surfaces (`#1a1915`) with the same copper accent. The dark palette carries a barely perceptible warm undertone throughout, as if the parchment has been turned over to reveal its shadowed side.

**Key Characteristics:**
- Warm cream canvas (`#faf8f1`) evoking unbleached paper, not screens
- Lora serif for headings, Inter for body, JetBrains Mono for code
- Burnt copper accent (`#b5623f`) — warm, earthy, deliberately artisanal
- Exclusively warm-toned neutrals — every gray has a yellow-brown undertone
- Border-forward design: warm borders define structure, shadows are minimal
- Generous whitespace and line-heights — reading pace of a book, not a dashboard
- Dark mode with olive-black surfaces (`#1a1915`) and preserved warm copper accent

## 2. Color Palette & Roles

### Light Mode

#### Primary
- **Ink** (`#2c2a25`): Primary text, headings, strong UI elements. A warm near-black with brown undertones — never pure black.
- **Burnt Copper** (`#b5623f`): Brand accent. Primary CTAs, active states, links, and emphasis. The signature color — warm, grounded, artisanal.
- **Light Copper** (`#c8795a`): Hover/lighter variant of accent. Used for link hover states and secondary emphasis.

#### Surface & Background
- **Parchment** (`#faf8f1`): Primary page background. Warm cream with a yellow undertone — the emotional foundation.
- **Vellum** (`#f4f1e8`): Secondary surface. Cards, elevated containers, alternate sections. Slightly warmer than parchment.
- **Linen** (`#eae6da`): Tertiary surface. Input backgrounds, code block backgrounds, subtle wells.
- **Pure White** (`#ffffff`): Reserved for focus states within inputs.

#### Neutrals
- **Charcoal** (`#3d3a34`): Secondary text, strong labels, sub-headings.
- **Warm Gray** (`#6b675e`): Tertiary text, descriptions, metadata, placeholder text.
- **Stone** (`#9c9789`): Muted text, timestamps, disabled content.
- **Sand** (`#c4bfb3`): Borders, dividers, inactive UI elements.
- **Light Sand** (`#ddd9ce`): Subtle borders, secondary dividers.
- **Dust** (`#ece9e0`): Hover backgrounds, faint separators.

#### Semantic
- **Success** (`#4a7c59`): Success states, confirmations. A muted forest green — warm, not neon.
- **Error** (`#a63d2f`): Error states, destructive actions. A warm brick red — serious but not alarming.
- **Warning** (`#b8862e`): Warning states. Warm amber — noticeable without panic.
- **Info** (`#4a6fa5`): Informational states. The only cool-leaning color, used sparingly. A dusty slate blue.

### Dark Mode

#### Primary
- **Ink** (`#e8e4db`): Primary text. Warm parchment-tinted light — never pure white.
- **Burnt Copper** (`#c8795a`): Brand accent (slightly lighter than light mode for contrast). Same warmth, adjusted for dark surfaces.
- **Light Copper** (`#d9927a`): Hover variant on dark surfaces.

#### Surface & Background
- **Deep Parchment** (`#1a1915`): Primary page background. Olive-tinted near-black — the darkest warm tone.
- **Dark Vellum** (`#23211b`): Elevated surfaces. Cards, modals, panels.
- **Dark Linen** (`#2d2b24`): Tertiary surface. Code blocks, input backgrounds.

#### Neutrals
- **Light Charcoal** (`#c4bfb3`): Secondary text on dark surfaces.
- **Warm Silver** (`#9c9789`): Tertiary text, descriptions on dark surfaces.
- **Dark Stone** (`#6b675e`): Muted text, disabled content on dark surfaces.
- **Dark Sand** (`#3d3a34`): Borders on dark surfaces.
- **Dark Light Sand** (`#33302a`): Subtle borders, dividers on dark surfaces.

#### Semantic (Dark Mode)
- **Success** (`#6a9c75`): Lightened for dark surface contrast.
- **Error** (`#c85a4a`): Lightened for dark surface contrast.
- **Warning** (`#cfa04a`): Lightened for dark surface contrast.
- **Info** (`#6a8fc0`): Lightened for dark surface contrast.

## 3. Typography Rules

### Font Families
- **Headline**: `Lora`, with fallback: `Georgia, 'Times New Roman', serif`
- **Body / UI**: `Inter`, with fallback: `system-ui, -apple-system, 'Segoe UI', sans-serif`
- **Code**: `'JetBrains Mono'`, with fallback: `'Fira Code', 'Source Code Pro', Consolas, monospace`

### Hierarchy

| Role | Font | Size | Weight | Line Height | Letter Spacing | Notes |
|------|------|------|--------|-------------|----------------|-------|
| Display | Lora | 48px (3rem) | 600 | 1.10 | -0.5px | Hero headings, page titles |
| Heading 1 | Lora | 36px (2.25rem) | 600 | 1.15 | -0.3px | Section headings |
| Heading 2 | Lora | 28px (1.75rem) | 600 | 1.20 | normal | Sub-section headings |
| Heading 3 | Lora | 22px (1.375rem) | 600 | 1.25 | normal | Card titles, feature names |
| Heading 4 | Lora | 18px (1.125rem) | 600 | 1.30 | normal | Small section headings |
| Body Large | Inter | 18px (1.125rem) | 400 | 1.65 | normal | Intro paragraphs, lead text |
| Body | Inter | 16px (1rem) | 400 | 1.65 | normal | Standard reading text |
| Body Small | Inter | 14px (0.875rem) | 400 | 1.55 | normal | Secondary descriptions, captions |
| Label | Inter | 14px (0.875rem) | 500 | 1.25 | 0.1px | Form labels, nav items |
| Label Small | Inter | 12px (0.75rem) | 500 | 1.25 | 0.2px | Badges, overlines, metadata |
| Button | Inter | 15px (0.9375rem) | 500 | 1.00 | 0.1px | Button text |
| Code | JetBrains Mono | 14px (0.875rem) | 400 | 1.60 | normal | Inline code, code blocks |
| Code Small | JetBrains Mono | 13px (0.8125rem) | 400 | 1.55 | normal | Compact code, terminal |

### Principles
- **Serif for authority, sans for utility**: Lora carries all headings with semibold weight (600), giving every title the presence of a chapter heading. Inter handles all functional text — body, labels, buttons, navigation — with clean readability.
- **Generous body line-height**: Body text uses 1.65 line-height — more breathing room than typical tech sites. This creates a reading pace closer to a book.
- **Tight heading line-height**: Headings range from 1.10 to 1.30 — compact but never cramped. Serif letterforms need room to breathe, but not too much.
- **Single heading weight**: All Lora headings use 600 — no variation. This creates a consistent voice across all sizes.
- **Weight as role signal**: Inter uses 400 for reading, 500 for interaction (labels, buttons, nav), never 600 or above.

## 4. Component Stylings

### Buttons

**Primary (Copper)**
- Background: Burnt Copper (`#b5623f`)
- Text: `#faf8f1`
- Padding: 10px 20px
- Radius: 6px
- Border: none
- Hover: Light Copper (`#c8795a`)
- Active: `#9e5436`
- Use: Primary actions, submit, confirm

**Secondary**
- Background: Linen (`#eae6da`)
- Text: Charcoal (`#3d3a34`)
- Padding: 10px 20px
- Radius: 6px
- Border: 1px solid Sand (`#c4bfb3`)
- Hover: background Dust (`#ece9e0`), border Light Sand (`#ddd9ce`)
- Use: Secondary actions, cancel, back

**Ghost**
- Background: transparent
- Text: Burnt Copper (`#b5623f`)
- Padding: 10px 20px
- Radius: 6px
- Border: 1px solid Sand (`#c4bfb3`)
- Hover: background Dust (`#ece9e0`)
- Use: Tertiary actions, less prominent options

**Danger**
- Background: Error (`#a63d2f`)
- Text: `#faf8f1`
- Padding: 10px 20px
- Radius: 6px
- Border: none
- Hover: `#b84a3c`
- Use: Destructive actions, delete, remove

**Dark Mode Buttons**: Copper buttons retain their color. Secondary buttons use Dark Linen (`#2d2b24`) with Dark Sand (`#3d3a34`) borders. Ghost buttons use copper text with dark sand borders.

### Inputs

**Text Input**
- Background: Linen (`#eae6da`)
- Text: Ink (`#2c2a25`)
- Placeholder: Stone (`#9c9789`)
- Padding: 10px 14px
- Radius: 6px
- Border: 1px solid Sand (`#c4bfb3`)
- Focus: border Burnt Copper (`#b5623f`), background `#ffffff`, subtle copper ring (`0 0 0 2px rgba(181, 98, 63, 0.15)`)
- Use: All text inputs, textareas, selects

**Dark Mode Inputs**: Background Dark Linen (`#2d2b24`), border Dark Sand (`#3d3a34`), focus border copper with darker ring.

### Cards

- Background: Vellum (`#f4f1e8`)
- Border: 1px solid Light Sand (`#ddd9ce`)
- Radius: 8px
- Padding: 24px
- Shadow: none (border-forward design)
- Hover (if interactive): border Sand (`#c4bfb3`)

**Dark Mode Cards**: Background Dark Vellum (`#23211b`), border Dark Light Sand (`#33302a`).

### Code Blocks

- Background: Linen (`#eae6da`)
- Border: 1px solid Light Sand (`#ddd9ce`)
- Radius: 6px
- Padding: 16px 20px
- Font: JetBrains Mono at 14px
- Text: Charcoal (`#3d3a34`)

**Inline Code**
- Background: Linen (`#eae6da`)
- Padding: 2px 6px
- Radius: 3px
- Font: JetBrains Mono at 0.9em relative to surrounding text

**Dark Mode Code**: Background Dark Linen (`#2d2b24`), border Dark Sand (`#3d3a34`).

### Navigation

- Background: Parchment (`#faf8f1`)
- Border-bottom: 1px solid Light Sand (`#ddd9ce`)
- Link text: Charcoal (`#3d3a34`), weight 500
- Link hover: Ink (`#2c2a25`)
- Active link: Burnt Copper (`#b5623f`)
- Padding: 16px 0 (vertical), 24px between items

### Alerts / Notices

- Radius: 6px
- Padding: 14px 18px
- Border-left: 3px solid (semantic color)
- Background: semantic color at 8% opacity
- Text: matching dark semantic tone
- Success: green-tinted background, Success border
- Error: red-tinted background, Error border
- Warning: amber-tinted background, Warning border
- Info: blue-tinted background, Info border

### Tables

- Header: background Vellum (`#f4f1e8`), text Charcoal, weight 500, uppercase Label Small size
- Rows: background Parchment, border-bottom 1px Light Sand
- Alternate rows: background Vellum (optional, subtle striping)
- Cell padding: 12px 16px

## 5. Layout Principles

### Spacing Scale (8px base)

| Token | Value | Use |
|-------|-------|-----|
| xs | 4px | Tight gaps, inline spacing |
| sm | 8px | Between related elements, icon gaps |
| md | 16px | Standard element spacing, form gaps |
| lg | 24px | Section padding, card padding |
| xl | 32px | Between content sections |
| 2xl | 48px | Major section breaks |
| 3xl | 64px | Page section spacing |
| 4xl | 96px | Hero spacing, page top/bottom padding |

### Content Width
- **Max content width**: 720px for prose (documentation, error messages, articles)
- **Max layout width**: 1120px for page layouts (with padding)
- **Full bleed**: Background colors and borders extend edge-to-edge; content stays contained

### Grid
- Single-column for prose and error screens
- Two-column for side-by-side layouts (max 1120px)
- Gutter: 32px between columns
- Margin: 24px minimum on mobile, 48px on desktop

### Principles
- **Generous vertical rhythm**: Sections breathe. Use 2xl–3xl spacing between major content blocks.
- **Content-width prose**: Body text never stretches beyond 720px — optimal reading line length.
- **Consistent padding**: Cards, alerts, code blocks all use lg (24px) or close to it.
- **Alignment over decoration**: Clean left alignment. No centered body text. Headings left-aligned.

## 6. Depth & Elevation

Arcanum uses a **border-forward** depth model. Structure is communicated through warm borders and surface color shifts, not shadows. This matches the paper/parchment metaphor — paper doesn't cast shadows on itself; edges and folds create boundaries.

### Elevation Levels

| Level | Treatment | Use |
|-------|-----------|-----|
| Base | Parchment background, no border | Page canvas |
| Raised | Vellum background, 1px Light Sand border | Cards, containers, grouped content |
| Inset | Linen background, 1px Light Sand border | Inputs, code blocks, wells |
| Overlay | Vellum background, 1px Sand border, soft shadow | Dropdowns, modals, popovers |

### Shadow (overlay only)
- `0 4px 12px rgba(44, 42, 37, 0.08)` — a single warm-tinted shadow, used only for floating elements (dropdowns, modals, tooltips)
- Never used on cards or static elements

### Focus Rings
- `0 0 0 2px rgba(181, 98, 63, 0.25)` — copper-tinted focus ring for accessibility
- Applied to all interactive elements on `:focus-visible`

## 7. Do's and Don'ts

### Do
- Use warm neutrals everywhere — every gray should have a brown/yellow undertone
- Use serif (Lora) for headings and display text only — never for body or UI
- Use borders to define structure — cards, sections, inputs all use 1px warm borders
- Keep accent usage deliberate — copper for primary actions, active states, and links only
- Maintain generous whitespace — let content breathe, especially between sections
- Use semantic colors for their intended purpose — never repurpose error red as an accent
- Keep dark mode warm — olive-black surfaces, warm light text, same copper accent

### Don't
- Use pure white (`#fff`) as a page background — always use Parchment or warmer
- Use pure black (`#000`) for text — always use Ink or warmer
- Use cool grays or blue-tinted neutrals anywhere in the palette
- Apply shadows to cards or static elements — use borders instead
- Use more than one accent color — Burnt Copper is the sole accent
- Mix serif and sans-serif in the same heading — one font per role
- Center-align body text — left-align everything except hero display text (which may be centered)
- Use thin font weights (300 or below) — minimum 400 for body, 500 for labels, 600 for headings

## 8. Responsive Behavior

### Breakpoints

| Name | Width | Notes |
|------|-------|-------|
| Mobile | < 640px | Single column, reduced spacing |
| Tablet | 640px – 1024px | Flexible columns, standard spacing |
| Desktop | > 1024px | Full layout, max-width containers |

### Scaling Rules
- **Display text**: 48px desktop, 36px tablet, 28px mobile
- **Heading 1**: 36px desktop, 28px tablet, 24px mobile
- **Heading 2**: 28px desktop, 24px tablet, 20px mobile
- **Body**: 16px at all sizes (never shrink body text)
- **Spacing**: 3xl/4xl spacing reduces by one step on mobile (e.g., 64px becomes 48px)
- **Cards**: Full-width on mobile with reduced padding (16px)
- **Navigation**: Collapses to hamburger below 640px
- **Max content width**: 720px remains, with 16px horizontal padding on mobile

### Principles
- Never shrink body text below 16px
- Maintain touch targets at minimum 44px on mobile
- Reduce spacing, not content, on smaller screens
- Cards and containers go full-width on mobile — no horizontal scrolling

## 9. Agent Prompt Guide

When generating UI for Arcanum, follow these rules:

### Surface & Color
- Start with Parchment (`#faf8f1`) as the base. Never use `#ffffff` as a page background.
- Use Vellum (`#f4f1e8`) for any elevated or grouped content (cards, panels, sections).
- Use Linen (`#eae6da`) for inset elements (inputs, code blocks, wells).
- Apply Burnt Copper (`#b5623f`) only to: primary buttons, links, active states, and focus rings. No other accent colors.
- For dark mode, swap to Deep Parchment (`#1a1915`) base with matching warm dark surfaces.

### Typography
- All headings: Lora, weight 600. No exceptions. No sans-serif headings.
- All body, labels, buttons, navigation: Inter, weight 400 or 500. Never Lora for functional text.
- All code: JetBrains Mono. Inline code gets Linen background with 3px radius.
- Body line-height: 1.65. Headings: 1.10–1.30. Never go below 1.10.

### Components
- Buttons: 6px radius, 10px 20px padding. Primary is copper, secondary is linen with sand border.
- Cards: 8px radius, 24px padding, 1px Light Sand border, Vellum background. No shadow.
- Inputs: 6px radius, Linen background, Sand border. Focus adds copper border and subtle copper ring.
- Alerts: 6px radius, left border 3px in semantic color, tinted background at 8% opacity.

### Layout
- Max prose width: 720px. Max layout width: 1120px.
- Use 8px spacing increments. Standard gap: 16px. Section gap: 48–64px.
- Left-align all text. Center-align only display headings in hero sections.

### Depth
- Use borders, not shadows, for structure. Only floating elements (modals, dropdowns) get shadows.
- Focus rings: 2px copper-tinted ring on `:focus-visible`.

### Error Screens
- Center the content block vertically and horizontally. Max-width 480px.
- HTTP status code as Display heading in Burnt Copper.
- Error message as Heading 2 in Ink.
- Helpful description as Body in Warm Gray.
- "Go back" or "Go home" as Ghost button.
- Parchment background, no card — just the content on the page canvas.
