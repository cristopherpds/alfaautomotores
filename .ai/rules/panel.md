---
paths:
  - 'app/Http/Controllers/Panel/**'
---

# Panel

## El panel de vehículos vive bajo panel/ para no chocar con el sitio público
`vehiculos.show` y `/vehiculos/{slug}` ya son del sitio público, así que el CRUD va en `Route::prefix('panel')->name('panel.')` con `App\Http\Controllers\Panel\VehiculoController`. Binding por id, no por slug: el slug es editable.
Lo mismo del lado del front: las páginas van en `resources/js/pages/panel/vehiculos/*`. Si se pusieran en `pages/vehiculos/`, el switch de `resources/js/app.tsx` (`name.startsWith('vehiculos/')`) les daría el layout público `AlfaLayout` en vez de `AppLayout`, sin sidebar.
Autorización con `HasMiddleware` + `VehiculoPolicy` (admin y vendedor gestionan; equipo sólo `viewAny`). `toListItem()` manda `can.update`/`can.delete` resueltos con la policy real.
`store()` redirige a `edit` y no al índice: las fotos necesitan que el vehículo ya exista.
