---
paths:
  - 'app/Support/**'
---

# Support

## CIFRADO_KEY no admite claves anteriores: rotarla pide re-cifrar
`App\Support\Cifrado` (AES-256-GCM, `Model::encryptUsing()`) usa una sola `CIFRADO_KEY`, sin lista de claves anteriores como `APP_PREVIOUS_KEYS`. Si cambiás la clave, todo lo cifrado deja de leerse. Para rotarla hace falta una migración/comando que descifre cada columna con la clave vieja y la vuelva a cifrar con la nueva, en una sola pasada y con backup previo. Lo mismo vale para `INDICE_CIEGO_KEY`: cambiarla obliga a recalcular todos los `*_hash`.
