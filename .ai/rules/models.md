---
paths:
  - app/Models/Vehiculo.php
  - app/Models/Entrega.php
  - app/Models/Turno.php
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

## La tira de la portada ya es una tabla; `public/entregas/` dejó de ser el dato
`App\Models\Entrega` (tabla `entregas`, archivos en el disco `public` bajo `entregas/`) reemplaza al viejo `ProvidesEntregas`, que listaba `public/entregas/`. Las 40 fotos históricas quedaron como semilla en `database/data/entregas/` y las carga `EntregaSeeder` (idempotente por `ruta`, que es unique; conserva el nombre original en vez del hash de `store()`). En un entorno nuevo hace falta `php artisan db:seed --class=EntregaSeeder` y `php artisan storage:link`: sin el symlink la tira sale con recuadros grises y sin error visible.

`paraLaTira()` es el contrato de `HomeController`: devuelve `{url, fecha, etiqueta, legible}`, los mismos cuatro campos del tipo `Entrega` de `resources/js/types/alfa.ts`. No se cambia de firma. `ordenadas()` es la única definición del orden (fecha desc, id desc) y la consultan la portada y el panel; el id desempata las del mismo día, que es lo que antes hacía el número de adelante en el nombre del archivo (por eso el seeder importa en orden ascendente).

Los meses en español van a mano en `Entrega::MESES`, no por locale de Carbon: el local escribe «setiembre», no «septiembre». Hay un test dedicado en `HomePageTest` porque es justo el detalle que un refactor prolijo rompe en silencio.

## El turno manda; la cita de Zap es el espejo que ocupa el horario
La agenda del taller corre sobre `laraveljutsu/zap`, pero el paquete NO guarda el turno: `App\Models\Turno` es la fuente de verdad (cliente, vehículo, estado) y `schedule_id` apunta a la cita espejo que hace que el horario deje de ofrecerse. Se hizo así porque el panel filtra por estado y ordena por fecha, y contra las tablas genéricas de Zap eso sería JSON sin tipos.

Regla que se sigue de eso: **estado y cita se mueven juntos**. `Turno::reservar()` crea las dos cosas en una transacción (con `lockForUpdate` sobre los puestos y un segundo chequeo adentro, para que dos personas no se lleven el mismo hueco); `cancelar()` borra la cita y deja `schedule_id` en null; un hook `deleting` limpia la cita. Si alguna vez se reprograma un turno, es borrar la cita y crear otra, no editarla.

Capacidad: Zap no maneja capacidad > 1 sobre un recurso, así que N autos en paralelo son N filas en `puestos`, cada una con su agenda (`config('taller.puestos')`, las crea `php artisan taller:agenda`). `Puesto::huecosDelDia()` y `Puesto::libreEn()` son la única definición de la disponibilidad: las consultan el sitio público y el panel.

Trampa ya pagada: al construir un schedule de Zap, no reutilices los nombres de variable del rango (`$desde`/`$hasta`) dentro del `foreach` que agrega los períodos — el destructuring los pisa y la agenda queda con `end_date = start_date` sin dar ningún error. Y `forYear()` del año en curso falla porque Zap rechaza fechas de inicio pasadas: hay que arrancar en hoy.
