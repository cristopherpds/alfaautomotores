---
paths:
  - resources/css/alfa.css
---

# Css

## El sitio público usa CSS propio, encapsulado en .alfa
La web pública (portada y lo que se porte del proyecto Next `alfa.automotores`) no usa Tailwind: usa el sistema de diseño "Catálogo Alfa" en `resources/css/alfa.css`, con los nombres de clase originales (.shell, .hero, .card, .grid, .btn).
Todo va anidado dentro de `.alfa` porque esos nombres chocan con utilidades de Tailwind que usa el panel (`.grid`, `.card`, `.badge`). Si agregás estilos del sitio público, van adentro de ese bloque; la página tiene que colgar de un contenedor con `className="alfa"`.
Los datos del local salen de `config/alfa.php`; la tipografía (Archivo) se declara en `vite.config.ts` con `bunny()`.

## Los heros de primera vista miden 100dvh menos el header
Los tres heros del sitio público (`.hero` de la portada, `.taller-hero`, `.lavadero-hero`) llenan la primera vista con el mismo par de líneas: `min-height: calc(100vh - var(--alfa-header))` y debajo la misma con `dvh`. `dvh` es la que manda: en móvil el alto útil cambia al esconderse la barra del navegador y con `vh`/`svh` la sección siguiente asoma o el hero se pasa de largo; la línea con `vh` queda de respaldo para navegadores viejos.
El hero es el flex que reparte ese alto (`display: flex` + `align-items: center` o `flex-end`), así que su `__inner` necesita `width: 100%` o el `shell` se encoge a su contenido.
Cuando el hero no tiene foto que sostenga el alto, el título suma el término `vh` al tamaño (`clamp(32px, min(4.4vw, 9vh), 58px)`): en una laptop baja (1366×768 deja ~640px útiles) todo el bloque tiene que caber en esa única vista. El `min()` con `vw` hace que en móvil el `vh` nunca mande, así el tamaño no salta al aparecer y desaparecer la barra.

## El nav del sitio público pasa a hamburguesa en 1023px, no en 767px
El corte del panel desplegable estaba en 767px cuando el menú tenía cuatro entradas. Con Movilidad y Bicicletas son seis más el botón de WhatsApp y a 1024px ya no entran en una línea; como `.header__inner` no envuelve a propósito (para que `--alfa-header` sea constante), pasado ese ancho la marca y el menú se pisan. Si se agrega otra sección, medir de nuevo antes que subir el corte a ciegas.

El otro `@media (max-width: 767px)` del archivo es del velo de `.taller-hero` y no tiene que ver: ese sigue en 767.
