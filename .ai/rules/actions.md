---
paths:
  - 'resources/js/actions/**'
---

# Actions

## Regenerate Wayfinder with --with-form
vite.config.ts runs the wayfinder plugin with `formVariants: true`, so pages rely on `Controller.action.form()`.
Running `php artisan wayfinder:generate` without `--with-form` silently strips those helpers and breaks every `<Form {...X.form()}>` page.
Always run `php artisan wayfinder:generate --with-form` (or just `npm run build` / `npm run dev`).
