---
paths:
  - 'resources/js/pages/panel/turnos/**'
---

# Turnos

## FullCalendar va clavado en 6.1.21
`@fullcalendar/react` y `@fullcalendar/core` ya publicaron 7.x, pero los plugins de vista (`daygrid`, `timegrid`, `interaction`) siguen con 6.1.21 como última estable. Instalar sin fijar versión deja react@7 + daygrid@6 y la app no compila. Los cinco paquetes van en la misma versión.

El calendario recibe los turnos ya con forma de evento desde `Panel\TurnoController::toEvento()`; al cambiar de mes o de vista, `datesSet` pide una recarga parcial de `turnos` con el rango nuevo (con una guarda por rango repetido, porque `datesSet` también dispara al montar). Los colores por estado son clases `.turno--<estado>` y su CSS vive en `resources/css/app.css`, atado a los tokens del panel.

El alta manual entra confirmada, con `origen = panel`, y sin la ventana de «desde mañana» que sí aplica a la web.
