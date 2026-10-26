---
paths:
  - 'app/Ai/**'
---

# Ai

## Agente de WhatsApp: sólo reserva horarios ofrecidos y la clave sale del panel
`ReservarTurno`/`ReprogramarTurno` sólo aceptan un inicio que esté en `horarios_ofrecidos` para esa conversación y servicio (lo escribe `ProponerHorarios`, vigencia 48 h): es la garantía de nea-agent de que el modelo no invente una hora. No saltear `HorarioOfrecido::fueOfrecido()`.
La clave y los modelos de OpenRouter salen de `Ajuste::conexionIa()` (panel, cifrado; `.env` de respaldo): `ResponderConversacion` hace `config(['ai.providers.openrouter.key' => …])` + `Ai::forgetInstance('openrouter')` antes de cada turno. Pedir humano y la racha hostil (≥3) son deterministas en `App\Ai\Guardas`, no del LLM. Toda salida pasa por `FormatoWhatsapp::convertir()` y `Mensajero`. Si el LLM o Meta fallan: nada al cliente y `derivar(MotivoDerivacion::Error)`.
Coalescing: un job por mensaje con delay de 8 s; el que no es del último entrante no hace nada. Tests: `AsistenteWhatsapp::fake([...])` (las tools no corren con el fake: se prueban llamando `handle(new Request([...]))`), `Transcription::fake()`.

## Links del agente con Ajuste::enlacePublico(), audios con Transcriptor
Todo link que manda el agente se arma con `Ajuste::enlacePublico($ruta, $params)` (URL pública del panel, `APP_URL` de respaldo), nunca con `route()`/`url()`: WhatsApp no linkea localhost ni hosts sin dominio (`Ajuste::esUrlEnlazable()`).
Las notas de voz pasan por `App\Ai\Transcriptor` (whisper por `audio/transcriptions`, respaldo `input_audio` por chat si el modelo oye audio). Un fallo marca `meta.transcripcion_fallida` + `transcripcion_intentos` (hasta 3), nunca guarda `''`. Si la media no bajó, el job se re-despacha (`ESPERAS_DE_MEDIA`).
Promesa de humano sin `derivar_a_humano` → `Guardas::prometeHumano()` deriva con `MotivoDerivacion::Modelo`.
