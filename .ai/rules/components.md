---
paths:
  - resources/js/components/producto-form-fields.tsx
---

# Components

## Los colores se cargan por nombre, no con color picker
Decisión: `colores` sigue siendo `string[]` de nombres. No hay color picker: el cliente lee el nombre del proveedor («Azul galaxia»), la mitad de las bicis son combinadas («Azul/Blanco/Amarillo») y un hex obligaría a migrar a `{nombre, hex}[]`. El `ColoresEditor` muestra en vivo la misma muestra que la ficha pública (`tonosDe`/`fondoDeMuestra`), sugiere `NOMBRES_DE_COLOR` con un `<datalist>` y avisa «Sin muestra» si un nombre no está en `TONOS`; no bloquea el guardado. Los inputs de color son controlados (`useFilas().cambiar`), los de specs siguen sin controlar.
