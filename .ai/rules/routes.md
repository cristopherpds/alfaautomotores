---
paths:
  - 'routes/**'
---

# Routes

## Los webhooks van en routes/webhooks.php, fuera del grupo web
`routes/webhooks.php` se carga desde `bootstrap/app.php` (`withRouting(then:)`) sin grupo de middleware: sin sesión, cookies ni CSRF. No mover el webhook de WhatsApp a `web.php`: con `SESSION_DRIVER=database` cada POST de Meta crearía una fila en `sessions`. La puerta es el token en la URL (`/webhooks/whatsapp/{WHATSAPP_VERIFY_TOKEN}`, otro token = 404) más la firma `x-hub-signature-256` con `WHATSAPP_APP_SECRET` (inválida = 401). El controlador responde 200 enseguida y encola `ProcesarWebhookWhatsapp`: en producción tiene que haber un worker de cola corriendo o no entra ningún mensaje.
