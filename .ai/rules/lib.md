---
paths:
  - resources/js/lib/productos.ts
---

# Lib

## Las muestras de color salen del mapa TONOS; un nombre nuevo va sin muestra
La ficha pública dibuja una muestra por color con `tonosDe()` + `fondoDeMuestra()`. `TONOS` traduce nombres normalizados (sin tildes, minúsculas, masculino y femenino) a hex; los combinados «A/B/C» o «A y B» se parten y dan una torta. Si alguna parte no está en `TONOS` el color va sólo con el nombre: es a propósito, mejor sin muestra que con una equivocada. Si desde el panel se carga un color nuevo («Fucsia») y tiene que verse, se agrega a `TONOS`. El nombre siempre se muestra al lado: la muestra es aproximada («gris mate», «marfil»).
