# Directory skin — UI style guide

The look the wall, threads, chat and tools wear: cards on a soft canvas, one
accent green, hairline borders, a wide column beside a narrow rail. This
documents what is actually in the code so a new page can join without
reinventing the measurements.

**Where it applies**

| Page | Shell | Rail |
| --- | --- | --- |
| `/wall` | `DirectoryLayout` | wall stats, hashtags, rules, tools |
| `/threads` | `DirectoryLayout` | thread list, topics, trending, tags, rules |
| `/chat` | `DirectoryLayout` | room controls, presence, snapshot |
| `/tools` | `DirectoryLayout` | on-this-site, elsewhere, suggestion box |
| `/jobs`, `/jobs/{id}`, `/jobs/mine` | `DirectoryLayout` | job stats, alerts CTA, sources, tracker |
| `/id-check`, `/admin/*` | `NewsfeedLayout` | — still the older squared skin |

---

## 1. Tokens

All of them live at `:root` in [resources/css/app.css](resources/css/app.css),
below the original `--b-*` block.

**They are additive on purpose.** The squared `--b-*` ladder above them is
untouched, so pages that have not opted in keep the look they were given. Never
"upgrade" a `--b-*` value to make a directory page look right — add to `--d-*`
or fix the page.

They sit at `:root` rather than on the shell element so overlays teleported to
`<body>` (the composer modal, the lightbox) resolve the same values.

### Surface

| Token | Light | Dark | Use |
| --- | --- | --- | --- |
| `--d-canvas` | `#f4f5f7` | `#0a0e11` | page behind the cards |
| `--d-card` | `#ffffff` | `#11171b` | any card, panel, popover |
| `--d-soft` | `#f7f8fa` | `#151d22` | inputs, hover fills, inset areas |
| `--d-line` | `#e7e9ee` | `#222b31` | every hairline border |
| `--d-line-strong` | `#d8dce3` | `#2e3942` | hover borders, dashed drop zones |

### Ink

| Token | Light | Dark | Use |
| --- | --- | --- | --- |
| `--d-ink` | `#10141a` | `#eef2f5` | headings, names, numbers |
| `--d-ink-2` | `#3f4854` | `#c5cdd5` | body copy inside cards |
| `--d-muted` | `#6b7482` | `#93a0ac` | labels, secondary lines |
| `--d-faint` | `#9aa2ae` | `#74808c` | timestamps, placeholders, separators |

### Green

| Token | Light | Dark | Use |
| --- | --- | --- | --- |
| `--d-green` | `#137a3a` | `#2fbf63` | button and tile fills |
| `--d-green-hover` | `#0f6330` | `#27a855` | their hover |
| `--d-green-ink` | `#ffffff` | `#04240f` | text **on** a green fill |
| `--d-green-text` | `#11703a` | `#3fd277` | green text on canvas or card |
| `--d-green-tint` | `#e9f6ee` | `#11261a` | soft green panels, active chips |
| `--d-green-tint-2` | `#f2faf5` | `#0e1d15` | the lighter end of a tint gradient |
| `--d-green-tint-line` | `#cde8d8` | `#1d3e2a` | borders on tinted panels |

### Radius, shadow, scrim

| Token | Value | Use |
| --- | --- | --- |
| `--d-r-xl` | `18px` | the CTA band, the composer modal |
| `--d-r-lg` | `14px` | cards |
| `--d-r-md` | `10px` | buttons, inputs, tiles, photo grids |
| `--d-r-sm` | `8px` | small tiles, menu items, chips-in-a-row |
| `--d-r-pill` | `999px` | search box, tabs, chips, the composer trigger |
| `--d-shadow` | `0 1px 2px rgba(16,24,40,.04)` | resting card |
| `--d-shadow-lift` | `0 10px 28px rgba(16,24,40,.1)` | hover, popovers, modal |
| `--d-scrim` | `rgba(12,17,22,.45)` | behind a modal |

### Footer

`--d-footer`, `--d-footer-ink`, `--d-footer-muted`, `--d-footer-line`. The
footer is dark in **both** themes; in dark mode it is a shade below the canvas,
which is why `.dir-foot` also carries `border-top: 1px solid var(--d-line)` —
without it the seam vanishes.

### Inherited from the base sheet

`--b-sans` (Geist) for everything, `--b-mono` for `<kbd>`, and `--b-ease`
(`cubic-bezier(.16,1,.3,1)`) for every transition and animation.

---

## 2. Colour rules

**Green is deeper than it looks in most directory UIs, deliberately.** White on
`#137a3a` is **5.4:1**; the brighter green this design is modelled on would have
been 3.3:1 and fails AA for body text.

**Dark mode lifts the hue and flips the ink.** `#04240f` on `#2fbf63` is 6.4:1.
White on that same green would be 2.6:1 — so never hardcode white on a green
fill; use `--d-green-ink` and let the theme decide.

**Red is the only colour outside the ramp.** `#c53030` for a negative score,
`#dc2626` for form errors, `#e05252` for a lit downvote arrow in either theme.
JobRadar adds a fourth: the "check before applying" warning, `#c53030` on a 9%
`color-mix` tint, lifted to `#f08a8a` in dark mode.
There is no red token because these are the only places it appears.

**Never define a colour only inside a `[data-theme]` block.** Light is the base
declaration; dark overrides.

---

## 3. Layout

### Shell

[DirectoryLayout.vue](resources/js/Layouts/DirectoryLayout.vue) owns the frame:

```
sticky bar      max-width 1180px, padding .7rem 1.25rem, 1px bottom border
body grid       minmax(0,1fr) 320px, gap 1.5rem, padding 1.75rem 1.25rem 2.5rem
CTA band        green, radius --d-r-xl, dotted mask on the right
footer          dark, 4 columns, bottom bar
```

Everything is capped at **1180px** and centred. The main column lands at
~796px on a 1440px viewport.

The rail is `position: sticky; top: 4.5rem` with `max-height: calc(100vh -
5.5rem)` and its own scrollbar — a rail taller than the screen would otherwise
strand its bottom cards.

### Breakpoints

Viewport media queries, in the layout only:

| Width | What happens |
| --- | --- |
| ≤1040px | body collapses to one column, rail goes static and uncapped |
| ≤900px | nav drops to its own scrolling row; both grid tracks become `minmax(0, …)` |
| ≤640px | CTA padding shrinks, footer stacks |
| ≤600px | brand text hides, leaving the mark; search takes the room |

The `minmax(0, …)` at 900px is load-bearing. Without it the nav's and the
search box's min-content widths push the bar past the viewport instead of
shrinking.

### Container queries, not media queries, inside a page

The main column is ~796px while the viewport is 1440px, so a page that asked
the viewport would break at the wrong moment. Every page root declares a
container and queries itself:

```css
.wall { container: wall / inline-size; }

@container wall (max-width: 720px) { /* … */ }
```

In use: `wall` (720 / 560), `th` (620), `chat` (620), `tools` (700 / 540).

**One exception:** the wall's feed columns are decided in JS by a
`ResizeObserver`, not CSS — see §6.

---

## 4. Type

One family, weight and tracking doing the work.

| Role | Size | Weight | Tracking |
| --- | --- | --- | --- |
| Hero `h1` | `2.4rem` (→`2rem`→`1.7rem`) | 800 | `-0.035em` |
| Section `h2` | `1.45rem` | 800 | `-0.03em` |
| Card headline | `1.15rem` | 750–800 | `-0.025em` |
| Rail card `h2` | `0.92rem` | 750 | `-0.015em` |
| Body in a card | `0.9rem` / 1.55 | 400 | — |
| Lede | `0.95rem` / 1.6, `--d-muted`, `max-width: 46ch` | 400 | — |
| Meta line | `0.66rem` uppercase | 700 | `0.07em` |
| Timestamps | `0.7–0.78rem`, `--d-faint` | 400 | — |

Headings get **negative** tracking and tighten as they grow; the uppercase meta
line gets **positive** tracking. Hero `h1` is capped at `20ch` so it always
breaks into two or three lines.

---

## 5. Components

Scoped per page, so these are patterns to copy rather than global classes. Keep
the names — a reader moving between pages should recognise them.

### Card

```css
background: var(--d-card);
border: 1px solid var(--d-line);
border-radius: var(--d-r-lg);
box-shadow: var(--d-shadow);
```

Interactive cards add, on hover: `border-color: var(--d-line-strong)`,
`box-shadow: var(--d-shadow-lift)`, `transform: translateY(-2px)`.

### Button

`.btn` + a modifier. `padding: .62rem 1.1rem`, `border-radius: var(--d-r-md)`,
`font-size: .88rem`, `font-weight: 650`, `1px solid transparent` so the green
and plain variants are the same height.

- `.btn-green` — `--d-green` fill, `--d-green-ink` text
- `.btn-plain` — card fill, `--d-line` border, ink text
- `.btn-sm` — `.5rem .85rem` / `.82rem`
- `.btn-block` — full width, `margin-top: .9rem` (rail CTAs)

### Tile

The square mark that stands in for an avatar or icon: green fill, white
initial, `font-weight: 800`.

| Size | Box | Radius | Font |
| --- | --- | --- | --- |
| `.tile` | `2.3rem` | `--d-r-md` | `.95rem` |
| `.tile-lg` | `2.9rem` | `--d-r-lg` | `1.15rem` |
| `.tile-sm` | `1.65rem` | `--d-r-sm` | `.68–.72rem` |

### Meta line

The small-caps attribution above a title: `#tag • ANONYMOUS`. `0.66rem`,
weight 700, `0.07em` tracking, uppercase, `--d-muted`, with `<b>` in
`--d-green-text` and the `•` separator in `--d-faint`.

### Chip

`padding: .3rem .65rem`, `--d-r-pill`, card fill, `--d-line` border,
`.76rem`/600, `--d-muted`. Hover borders green; `.active` is a solid green fill
with `--d-green-ink`.

The wall's composer tags are a size up (`.35rem .7rem` / `.78rem`) because they
sit in a modal rather than a rail. Those are the only two sizes — match one of
them rather than inventing a third.

### Vote control

Up arrow, score, down arrow. Column on a spotlight card, row (`.vote-inline`,
`--d-r-pill`) inside a feed card's action bar.

- Arrows are `.vote-arrow`, circular, `--d-muted`.
- Lit state is `color` **plus** a tint mixed from `currentColor`
  (`color-mix(in srgb, currentColor 16%, transparent)`) — colour alone is too
  thin at this size. A browser without `color-mix` keeps the colour change.
- The score is `font-variant-numeric: tabular-nums` so it does not jiggle;
  `.up` is `--d-green-text`, `.down` is `#c53030`.

### Rail

```
.rail-stat   uppercase .7rem line above the first card: LABEL  12  · 3 today
.rail-card   padding .9rem .95rem 1rem, standard card
.rail-head   h2 left, a small green text action right
.rail-stats  dashed-separated label/value rows
```

A shared empty state inside a rail card drops its border and padding
(`.rail-card .empty`) so it does not read as a card in a card.

### Section head

Title and sub on the left, a green text link or count pill on the right,
`align-items: flex-end`. Stacks to a column in the narrow container query.

### Empty state

`padding: 1.4rem`, card surface, centred `.88rem` `--d-muted` text. Say what
would fill it: *"No votes yet. Upvote a post and it lands here."*

---

## 6. Feed columns

A CSS grid row is as tall as its tallest card, which left a hole under every
short post sitting next to one with a photo. The wall deals posts across
columns instead — 1 top-left, 2 top-right, 3 under 1 — so recency order reads
correctly and each column packs to its own height.

Column count comes from a `ResizeObserver` on the page root (1 below 720px, else
2) because the decision depends on the column's width, not the viewport's, and
the count has to reach JS to split the array.

---

## 7. Motion

One entrance animation, one easing.

```css
.rise { animation: rise .5s var(--b-ease) backwards; animation-delay: calc(var(--i, 0) * 60ms); }
```

`riseDelay(index)` caps `--i` at 4, so late cards do not crawl in. Transitions
are `.15s` for colour and border, `.3s` for image zoom.

Everything is disabled under `@media (prefers-reduced-motion: reduce)`.

---

## 8. Adding a page

1. Render `DirectoryLayout` **in the template** — not through
   `defineOptions({ layout })`. Inertia's persistent-layout option only reaches
   the default slot, and these pages fill `#rail`.
2. Own the search term and pass it down: `<DirectoryLayout v-model:search="search"
   search-placeholder="Search threads">`. Slot content is rendered by the page,
   which sits *above* the layout, so `inject` cannot reach a layout `provide`.
3. Give the page root a container: `container: <name> / inline-size`.
4. Copy the furniture block (`.btn`, `.tile`, `.empty`, `.rail-*`) from the
   nearest page and keep the class names.
5. Fill `#rail` with what that page actually knows. A rail of generic links is
   worse than no rail.
6. Check both themes, and check 1440 / 1024 / 768 / 400 for horizontal
   overflow before shipping.

---

## 9. Mistakes already made here

Each of these cost a round trip during the build.

- **`--nf-*` variables do not exist under this layout.** They are scoped to
  `.nf-shell` in `NewsfeedLayout`. A component moved across must be re-pointed
  at `--d-*`, or its borders go transparent and its text inherits.
- **Do not reuse a block name as a modifier.** `.featured` as both the
  spotlight section and the highlighted feed card meant one selector styled
  both. The section is `.spotlight`; the modifier is `.is-featured`.
- **`get(['col', …])` after `withSum()` returns only the aggregate.** Once the
  aggregate has put a select on the query, the columns passed to `get()` are
  ignored. Call `->select([...])` before `->withSum(...)` and `get()` with no
  arguments.
- **Grid items need `min-width: 0`** or their min-content width pushes the page
  wider than the viewport. Same for `minmax(0, …)` tracks.
- **Chrome's minimum window is ~500px.** A `--window-size=390` screenshot
  renders at 500 and crops, which looks exactly like a layout bug. Use CDP
  `Emulation.setDeviceMetricsOverride` for narrow widths.
- **`postJson` does not send cookies** unless `withCredentials()` is called, so
  a cookie-identity test silently passes for the wrong reason. A browser always
  sends them.
