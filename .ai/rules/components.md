---
paths:
  - resources/js/components/producto-form-fields.tsx
  - resources/js/components/hero-vista-previa.tsx
---

# Components

## Los colores se cargan por nombre, no con color picker
Decisión: `colores` sigue siendo `string[]` de nombres. No hay color picker: el cliente lee el nombre del proveedor («Azul galaxia»), la mitad de las bicis son combinadas («Azul/Blanco/Amarillo») y un hex obligaría a migrar a `{nombre, hex}[]`. El `ColoresEditor` muestra en vivo la misma muestra que la ficha pública (`tonosDe`/`fondoDeMuestra`), sugiere `NOMBRES_DE_COLOR` con un `<datalist>` y avisa «Sin muestra» si un nombre no está en `TONOS`; no bloquea el guardado. Los inputs de color son controlados (`useFilas().cambiar`), los de specs siguen sin controlar.

## La vista previa del hero simula la pantalla con tamaños fijos y escala
El hero real mide con `vw`/`vh`; dentro del panel esas unidades medirían la ventana del panel. `HeroVistaPrevia` renderiza a 1366×695 (escritorio) o 390×771 (móvil) con los tamaños ya resueltos en variables `--vista-*` que lee `.hero--vista-previa` en `alfa.css`, y escala el conjunto con `transform`. Si cambian los `clamp()` de `.hero`, recalcular esas variables. El ancho se mide a mano además del ResizeObserver: tras hidratar la página SSR el observador puede no volver a avisar.
