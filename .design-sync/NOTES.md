# design-sync notes

Project: "Alfa Automóviles UI" — https://claude.ai/design/p/7e4c5c82-196b-4c54-8aa3-9ec255bc4109
Shape: `package` (no Storybook). 45 components, all with authored previews in `previews/`.

## How the build works

- `.design-sync/pkg/entry.ts` is the synthetic package entry (shadcn `ui/` + `alfa/` components). `componentSrcMap` in `config.json` maps names whose file name doesn't match.
- `build.mjs` (`cfg.buildCmd`) emits `.d.ts` with tsc and compiles `ds.css` with Tailwind v4 into `pkg/build/` (gitignored). App type errors don't block the declaration emit.
- `shims/inertia-react.tsx` replaces `@inertiajs/react` (Link, usePage) so `alfa/` components render outside Inertia.
- `overrides/dts.mjs` groups flat shadcn sub-exports (CardHeader, TableRow…) under their parent.
- `fonts/fonts.css` carries the Archivo + Instrument Sans `@font-face` rules. The converter only copies `@font-face` from `extraFonts`, so the `--font-archivo` / `--font-instrument-sans` variables (normally injected by laravel-vite-plugin) live in `ds.css` instead. Without them `.alfa` falls back to the system font.

## Running it on Windows

- Set `DS_CHROMIUM_PATH="/c/Program Files/Google/Chrome/Application/chrome.exe"` before running the driver/capture (uses the installed Chrome, no playwright download).
- Driver: `node .ds-sync/resync.mjs --config .design-sync/config.json --node-modules ./node_modules --out ./ds-bundle [--remote .design-sync/.cache/remote-sync.json]`

## Card layout

- Overlays (Dialog, Sheet, DropdownMenu, Tooltip, Toaster, Select) use `cardMode: single` with a viewport; Select uses `primaryStory: Abierto`.
- Anything wider than a grid cell (cards, tables, grids, footer, form fields) uses `cardMode: column` — otherwise validate reports `[GRID_OVERFLOW]`.
- Changing `overrides` needs a full build (driver run); `preview-rebuild.mjs` alone fails with `[CONFIG_STALE]`.

## Known render warns

- `[EXPORT_COLLISION] lucide-react exports Badge, Icon, Sheet, Sidebar, Table` — expected: previews import those from `alfa-ui`, and lucide icons are imported under their `*Icon` names.

## Re-sync risks

- Toaster (sonner) renders with sonner's own system font stack — same as the app, not a bug.
- If `alfa.css` or `app.css` changes its font variables, re-check the `:root` block in `ds.css`.
