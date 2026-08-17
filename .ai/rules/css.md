---
paths:
  - resources/css/alfa.css
---

# Css

## El sitio público usa CSS propio, encapsulado en .alfa
La web pública (portada y lo que se porte del proyecto Next `alfa.automotores`) no usa Tailwind: usa el sistema de diseño "Catálogo Alfa" en `resources/css/alfa.css`, con los nombres de clase originales (.shell, .hero, .card, .grid, .btn).
Todo va anidado dentro de `.alfa` porque esos nombres chocan con utilidades de Tailwind que usa el panel (`.grid`, `.card`, `.badge`). Si agregás estilos del sitio público, van adentro de ese bloque; la página tiene que colgar de un contenedor con `className="alfa"`.
Los datos del local salen de `config/alfa.php`; la tipografía (Archivo) se declara en `vite.config.ts` con `bunny()`.
