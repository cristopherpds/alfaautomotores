---
paths:
  - app/Models/Vehiculo.php
---

# Models

## El stock todavía es un mock, no una tabla
`App\Models\Vehiculo` no es Eloquent: es un objeto de sólo lectura que lee `database/data/vehiculos.json` (22 vehículos traídos de `scripts/vehiculos-mock.ts` del proyecto Next). No toca base.
Cuando se cree la migración, esta clase pasa a ser el modelo Eloquent conservando la misma API — `publicos()`, `destacados()`, `buscar()`, `similares()`, `contar()`, `titulo()` — y las páginas del sitio público no cambian. El JSON sirve como seeder.
`estado` usa los valores internos `publicado|reservado|vendido` (más `borrador`, que nunca sale al público); la etiqueta visible sale de `estadoLegible()` en `resources/js/lib/catalogo.ts`.
