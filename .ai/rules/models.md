---
paths:
  - app/Models/Vehiculo.php
---

# Models

## El stock todavía es un mock, no una tabla
`App\Models\Vehiculo` no es Eloquent: es un objeto de sólo lectura que lee `database/data/vehiculos.json` (22 vehículos traídos de `scripts/vehiculos-mock.ts` del proyecto Next). No toca base.
Cuando se cree la migración, esta clase pasa a ser el modelo Eloquent conservando la misma API — `publicos()`, `destacados()`, `buscar()`, `similares()`, `contar()`, `titulo()` — y las páginas del sitio público no cambian. El JSON sirve como seeder.
`estado` usa los valores internos `publicado|reservado|vendido` (más `borrador`, que nunca sale al público); la etiqueta visible sale de `estadoLegible()` en `resources/js/lib/catalogo.ts`.

## El stock ya es una tabla; la API estática es el contrato del sitio público
`App\Models\Vehiculo` ya es Eloquent (tabla `vehiculos`). Reemplaza la regla vieja: el mock JSON quedó como semilla en `database/data/vehiculos.json`, que carga `VehiculoSeeder` con `created_at` descendente en el orden del archivo.
`publicos()`, `destacados()`, `buscar()`, `similares()`, `contar()` y `titulo()` son el contrato de `HomeController` y `VehiculoController`: devuelven vehículos con `fotos` precargadas y no se cambian de firma.
`#[Hidden(['id','destacado','fotos','created_at','updated_at'])]` deja la serialización en los 13 campos que espera el tipo `Vehiculo` de `resources/js/types/alfa.ts`, más el accessor `imagenes` (URLs). La relación se llama `fotos()` y el accessor `imagenes` a propósito: un accessor y una relación no pueden compartir nombre.
`destacado` está FUERA del `#[Fillable]` — es la barrera de mass assignment; sólo lo toca `Panel\VehiculoController::destacado()`, con el tope `MAX_DESTACADOS` validado en `DestacarVehiculoRequest`. `destacados()` completa sola con los publicados más recientes si no llegan al tope.
Portada = la foto de menor `orden` (no hay columna `portada`). Al borrar un vehículo, un hook `deleting` limpia `storage/app/public/vehiculos/{id}`.
`estado`, `tipo`, `comb`, `trans` y `moneda` son enums; en los cuatro últimos el valor guardado ES el texto visible, para que `opcionesDe()`/`filtrar()` de `resources/js/lib/catalogo.ts` sigan funcionando sin traducción.

## Un borrador nunca se destaca; el tope y la portada cuentan el mismo conjunto
Regla: se puede destacar cualquier vehículo que llegue al público (publicado, reservado, vendido); sólo el borrador no. `Vehiculo::esDestacable()` es la única definición — la consultan el hook `saving` (que limpia `destacado` al pasar a borrador), `DestacarVehiculoRequest` y los payloads `destacable` de `Panel\VehiculoController`.

Trampa que ya se pagó una vez: `contarDestacados()` y `destacados()` tienen que filtrar el MISMO conjunto. Cuando `contarDestacados()` contaba todo y `destacados()` filtraba sólo `publicado`, el panel decía "6 de 6" mientras la portada mostraba 5 y el lugar libre se lo comía el relleno automático. Si tocás una, tocá la otra.

`destacados()` = (destacado OR publicado) AND no borrador: los fijados a mano entran en cualquier estado público, el relleno automático sigue siendo sólo de publicados.
