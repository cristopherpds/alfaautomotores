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

## Las entregas se suben en lote con una fecha común
`panel/entregas` no tiene `create` ni `edit`: el resource es `only(['index','store','update','destroy'])` y todo pasa por el índice. Se suben varias fotos con una sola `fecha` (hasta `Entrega::MAX_POR_LOTE`, doce, tope de PHP y no de negocio) y después se corrige la fecha de cualquiera con `update` — es lo único editable, la foto no se reemplaza: se borra y se sube de nuevo.

`index()` manda `puedeGestionar` de página (no `can` por fila como vehículos) porque el permiso depende del rol y no de la entrega; se resuelve con `EntregaPolicy` para que la página no duplique la regla en TypeScript. `EntregaPolicy::gestiona()` (admin + vendedor; equipo sólo lee) está duplicado a propósito con `VehiculoPolicy`: son tres líneas y extraerlas a un trait recién vale con una tercera policy.

En el front, la fecha del formulario es estado controlado y no se resetea al subir: cargar una entrega vieja suele llevar más de una tanda. El helper de tests se llama `fotoDeEntrega()` y no `fotoDePrueba()` — ese nombre ya lo ocupa `VehiculoImagenTest` y Pest carga todo en el mismo proceso.
