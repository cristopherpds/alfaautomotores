---
paths:
  - database/data/productos.json
---

# Data

## Los catálogos PDF del proveedor tienen 47 y 29 páginas, no las que parece
`database/data/productos.json` se arma cruzando dos fuentes: la Store API de worldsports.com.uy (`/wp-json/wc/store/v1/products`, de donde salen precio en pesos y stock real) y los PDF `MOVILIDAD.pdf` (47 páginas) y `BICICLETAS.pdf` (29), de donde salen los modelos que la web ya no publica.

Trampa ya pagada: los índices internos de los PDF numeran las páginas distinto que el archivo (el índice de MOVILIDAD dice «MONOPATINES/HOVERBOARDS 17-28» y están en las páginas 35-46). Leer por el índice deja fuera media sección — la primera pasada se comió los hoverboards, dos monopatines, la moto cross eléctrica de niño y seis bicicletas. Antes de dar por completo un catálogo, `pdfinfo` para el total de páginas y `pdftotext -layout -enc UTF-8` para el inventario; recién ahí `pdfimages`.

Un producto del PDF sin precio no es un olvido: es la regla (el precio de lista viejo no sirve para el mostrador). Las fotos recortadas que salen con fondo negro traen la transparencia en un smask aparte — hay que componerlas sobre blanco antes de guardarlas.
