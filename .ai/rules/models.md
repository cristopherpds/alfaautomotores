---
paths:
  - app/Models/Vehiculo.php
  - app/Models/Entrega.php
  - app/Models/Turno.php
  - 'app/Models/{Puesto,Servicio,Turno}.php'
  - app/Models/Producto.php
  - 'app/Models/**'
  - app/Models/Cliente.php
  - app/Models/Ajuste.php
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

## La capacidad es por rubro: un lavado no ocupa un puesto de mecánica
`App\Enums\Rubro` (taller | lavadero) es columna en `servicios` y en `puestos`, y parte la capacidad en dos: los horarios de un servicio salen ÚNICAMENTE de los puestos de su mismo rubro.

`Puesto::huecosDelDia()` y `libreEn()` no reciben el rubro: lo derivan de `$servicio->rubro`, y con él resuelven `Puesto::activos($rubro)` y el buffer. Por eso sus firmas no cambiaron. `Puesto::activos()` SÍ lo exige. Si alguna vez vuelve a sumar todos los puestos activos, el test «booking a wash does not eat into the workshop capacity» (tests/Feature/LavaderoReservaTest.php) es el que lo caza.

El valor del enum coincide a propósito con el nombre del archivo de config: `$rubro->config('buffer')` lee `config/taller.php` o `config/lavadero.php`. Cada rubro tiene su comando de agenda (`taller:agenda`, `lavadero:agenda`), ambos sobre `AgendarPuestos`.

Trampa: el nombre del schedule de Zap es la clave de idempotencia y quedó como "Horario del {rubro} {anio}" — para taller la cadena es idéntica a la vieja, así que los datos existentes no se duplicaron. No cambiar ese formato sin migrar.

`servicios.area` es nullable: sólo el taller clasifica por área. `ServicioRequest` la exige con `required_if:rubro,taller`.

## El catálogo de movilidad son dos grillas sobre una tabla, y la mitad va sin precio
`App\Models\Producto` (tabla `productos`, fotos en `producto_imagenes`) alimenta `/movilidad` y `/bicicletas`. La frontera entre las dos páginas es `FamiliaProducto::esBicicleta()`: la consultan `Producto::deSeccion()`, `contar()` y los chips de las grillas. `deSeccion()`, `buscar()`, `similares()` y `contar()` son el contrato de `ProductoController`; no se cambian de firma.

`precio` es nullable a propósito: las bicicletas se cotizan por WhatsApp y la grilla muestra «Consultar precio» en vez de una cifra (y sin precios no se dibuja el deslizador). `EstadoProducto` tiene cuatro casos y tres llegan al público: `sin_stock` es lo que el catálogo impreso marca agotado y `por_encargue` el modelo que el proveedor ya no publica pero consigue a pedido.

`familia` y `estado` viajan como el valor del enum; las etiquetas visibles salen de `familiaLegible()` / `estadoLegible()` en `resources/js/lib/productos.ts`, igual que el estado del vehículo en `lib/catalogo.ts`. No ponerlas en `$appends`: un accessor de `Attribute` con nombre camelCase ahí revienta con «Call to undefined method getFamiliaLabelAttribute()».

La semilla (`database/data/productos.json` + las fotos de al lado) sale de dos fuentes: la Store API de WooCommerce del proveedor (`worldsports.com.uy/wp-json/wc/store/v1/products`), de donde vienen precios en pesos y stock real, y los catálogos PDF 2026, de donde vienen los modelos que la web ya no lista — esos van sin precio porque el precio de lista viejo no sirve para el mostrador.

## Auditoría: un modelo nuevo que se edite desde el panel usa el trait Auditable
`App\Concerns\Auditable` escribe en `auditorias` cada `created`/`updated`/`deleted` con el usuario de la sesión («Cliente (sitio web)» si es un request sin login, «Sistema» en consola). El modelo define `tipoDeAuditoria()` (clave del filtro; sumarla también a `TIPOS_AUDITORIA` en `pages/panel/auditoria/index.tsx`) y `etiquetaDeAuditoria()`.
Trampas: una escritura masiva (`query()->update()`, `->whereKey()->update()`, delete sobre builder) NO dispara eventos: anotarla con `Auditoria::registrar()`, como `*ImagenController::orden()`. Nunca se guardan `remember_token` ni `two_factor_*`; `password` queda como «cambiada». `DatabaseSeeder` usa `WithoutModelEvents`, por eso sembrar no audita; un seeder corrido suelto (`--class=`) sí, como «Sistema». `Puesto` no se audita a propósito (lo arma `taller:agenda`).

## vendido_at la pone el modelo, no un formulario
`vehiculos.vendido_at` es la fecha de venta. La pone y la limpia el hook `saving` de `Vehiculo`: al pasar a `vendido` (ficha, lote o donde sea) toma `now()`; si ya estaba vendido no se mueve; si vuelve a otro estado queda en null. Está FUERA de `#[Fillable]` y en `#[Hidden]` (no viaja al sitio público). «Vendidos este mes» del dashboard cuenta por esta columna. Los vendidos anteriores a la columna quedaron en null (no hay de dónde sacar la fecha): no cuentan para ningún mes.

## Sólo lo listable (publicado, reservado) se lista y se destaca; el vendido conserva su ficha
Reemplaza a «un borrador nunca se destaca». `EstadoVehiculo::listables()` (publicado, reservado) es la única definición de qué ofrece el sitio: la usan `publicos()` (/catalogo), `contar()` (totalStock de la home), `destacados()` y `contarDestacados()`, y `esDestacable()` = `estado->esListable()`. Un vendido sale de todos los listados y de la portada (el hook `saving` le quita el destacado al venderlo), pero `buscar()` lo sigue devolviendo: su ficha abre con el cartel «Vendido» para que los links viejos no den 404. `similares()` es más estricto a propósito: sólo publicados. `contarDestacados()` y `destacados()` tienen que seguir filtrando el mismo conjunto.

## Clientes: identidad por celular, el consentimiento sólo lo suma una reserva
`Cliente` nace de `Turno::reservar()` (vía `Cliente::desdeReserva()`, dentro de la transacción: una reserva sin lugar no crea cliente) o del alta manual del panel. Identidad = `celular_normalizado` (sólo dígitos, único, lo calcula el hook `saving`); si no coincide, se busca por email en minúsculas. Una reserva pisa nombre/email/celular con lo último, y `acepta_novedades` sólo lo SUMA: nunca lo quita (eso es desde la ficha). `acepta_novedades_at` lo pone/borra el hook al cambiar el flag (ley 18.331: consentimiento con fecha, casilla nunca pretildada).
El turno guarda su propia copia de los datos; `turnos.cliente_id` es nullOnDelete. La relación en Turno se llama `ficha()` porque `Turno::cliente()` ya era el nombre completo en texto. En el form del panel el consentimiento viaja en un input oculto 1/0 (un checkbox destildado no manda nada); los Checkbox de Radix necesitan `value="1"` porque mandan «on» y `boolean` lo rechaza. Tests de reserva: fijar `->duracion(30)` en el servicio, los huecos saltan según la duración aleatoria de la factory.

## Ajustes del sitio: tabla `ajustes`, cacheada, con valores por defecto en el modelo
Lo que se configura desde el panel y afecta al sitio público vive en `ajustes` (una fila por grupo, `valor` json). Leer siempre con `Ajuste::valor()` o el accessor del grupo (`Ajuste::botonWhatsapp()`): mezcla lo guardado sobre los valores por defecto, así una base nueva funciona sin sembrar nada. Va cacheado para siempre porque lo lee cada página pública (`ProvidesSiteInfo`); escribir SÓLO con `Ajuste::guardar()`, que limpia el caché (un `update()` directo dejaría el sitio mostrando lo viejo). Usa `Auditable`.
El número de WhatsApp es uno solo: `siteInfo()['whatsapp']` sale del ajuste (fallback `config('alfa.whatsapp')`), así cabecera, pie, fichas y botón flotante no pueden quedar con números distintos. El botón flotante lo monta `AlfaLayout` (`components/alfa/boton-whatsapp.tsx`); `TarjetaWhatsapp` se reusa en la vista previa del panel dentro de un `.alfa`.

## Botón de WhatsApp: varios contactos con horario y uno de respaldo
`boton_whatsapp` guarda `titulo`, `subtitulo` y `contactos[]` (nombre, detalle, numero, mensaje, respaldo, horario {siempre, dias ISO 1–7, desde, hasta}). `Ajuste::whatsappDisponible(now())` es la única definición de quién se ofrece: los que están en horario, en el orden del panel; si no hay ninguno, el respaldo (exactamente uno, lo valida `AjusteWhatsappRequest::after()`) con `fueraDeHorario`. Una franja con desde > hasta cruza la medianoche y vale en la madrugada del día siguiente si el día anterior está en `dias`. `siteInfo()['whatsapp']` = el primer disponible, así los links sueltos siguen el mismo horario. El formato viejo (un solo `numero`) se lee como un contacto de respaldo.
Trampas: `distinct` en `contactos.*.horario.dias.*` compara entre TODOS los contactos (rechaza dos que atienden el lunes): los repetidos se limpian en el controller. Los tests de horario que usan `Ajuste::guardar()` no pasan por la validación: hay uno que manda varios contactos por HTTP. En el form (useForm), los errores llegan con claves anidadas (`contactos.0.horario.dias.2`): el helper busca por prefijo.
