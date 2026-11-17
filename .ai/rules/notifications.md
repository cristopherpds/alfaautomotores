---
paths:
  - 'app/Notifications/**'
---

# Notifications

## Avisos: equipo por campana+email, cliente sólo al confirmar y si el ajuste está prendido
Cotizaciones y turnos de clientes (web o WhatsApp) avisan a `User::equipoDeAvisos()` (admin + vendedor) con `NuevaCotizacion`/`NuevoTurno` (canales database + mail). Se disparan en `Cotizacion::recibir()` y en `Turno::avisarAlEquipo()`, que llaman `AgendaPublica::reservar()` y la tool `ReservarTurno` — NO `Turno::reservar()`, así las altas del panel y las reprogramaciones no avisan.
Al cliente sólo `TurnoConfirmado` (ruta a demanda), vía `Turno::avisarConfirmacion()`: al pasar a Confirmado en el panel o en el alta del panel, y sólo si `Ajuste::avisos()['confirmacion_turno']` (apagado por defecto, Ajustes › Avisos por email) y el turno tiene email.
`toArray()` (tabla `notifications`, sin cifrar) lleva `{tipo, titulo, detalle, url}` SIN datos personales: sólo auto/servicio/horario; nombre y celular van únicamente en el email.
Todas son `ShouldQueueAfterCommit` + `#[DeleteWhenMissingModels]`: en producción hace falta el worker de cola. Mail por Resend (`MAIL_MAILER=resend`, `RESEND_API_KEY`). La campana es `Panel\NotificacionController` + `useNotificaciones` (polling 15 s, como WhatsApp).

## Los avisos leídos se borran a los 30 días
`avisos:limpiar` (app/Console/Commands/LimpiarAvisos.php, programado a diario 04:00 en routes/console.php) borra de `notifications` los leídos hace más de `LimpiarAvisos::DIAS` (30). Los no leídos nunca se borran: siguen sumando al contador de la campana. En producción hace falta el `schedule:run` en cron.
