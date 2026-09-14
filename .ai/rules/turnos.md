---
paths:
  - 'resources/js/pages/panel/turnos/**'
---

# Turnos

## FullCalendar va clavado en 6.1.21
`@fullcalendar/react` y `@fullcalendar/core` ya publicaron 7.x, pero los plugins de vista (`daygrid`, `timegrid`, `interaction`) siguen con 6.1.21 como última estable. Instalar sin fijar versión deja react@7 + daygrid@6 y la app no compila. Los cinco paquetes van en la misma versión.

El calendario recibe los turnos ya con forma de evento desde `Panel\TurnoController::toEvento()`; al cambiar de mes o de vista, `datesSet` pide una recarga parcial de `turnos` con el rango nuevo (con una guarda por rango repetido, porque `datesSet` también dispara al montar). Los colores por estado son clases `.turno--<estado>` y su CSS vive en `resources/css/app.css`, atado a los tokens del panel.

El alta manual entra confirmada, con `origen = panel`, y sin la ventana de «desde mañana» que sí aplica a la web.

## La agenda es una sola: el fondo dice el estado y la barra izquierda el rubro
Taller y lavadero comparten `panel/turnos`: el mostrador atiende los dos y necesita ver el día completo. El filtro es un `ToggleGroup` (Todos / Taller / Lavadero) que viaja como query `rubro`.

Dos señales en canales distintos para que no se pisen:
- FONDO del evento = estado (`.turno--<estado>`).
- BARRA IZQUIERDA = rubro (`.turno--taller` / `.turno--lavadero`), con los tokens `--rubro-taller` / `--rubro-lavadero` de `resources/css/app.css`. Esos tokens NO son `--chart-*`: los de shadcn cambian de matiz entre claro y oscuro y acá el color significa algo. El `border-left` se le sacó a `.turno--pendiente`, que ya se distingue por su fondo.

Trampa: la guarda de `datesSet` es un `useRef` con la clave del rango. El rubro TIENE que entrar en esa clave o cambiar de filtro sobre el mismo mes no pide nada. Y `pedirRango` guarda el rango visible en otro ref para que el filtro pueda repedir el mismo tramo sin esperar a que el usuario navegue.

FullCalendar acá sólo tiene los plugins gratuitos: no hay columnas por recurso (ese plugin es premium).
