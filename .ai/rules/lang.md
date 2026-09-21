---
paths:
  - 'lang/**'
---

# Lang

## Sin lang/es los errores de validación salen como clave cruda
`app.locale` y `app.fallback_locale` son los dos `es`, así que Laravel nunca cae al inglés que trae el framework: si falta la traducción, el usuario ve la clave («validation.required», «validation.uploaded»). Por eso existe `lang/es/validation.php`.

Las claves tienen que coincidir con las de `lang/en/validation.php`, que es lo que publica `php artisan lang:publish` para la versión de Laravel instalada. Al actualizar Laravel conviene volver a publicarlo y comparar: 13 sumó `array_keys`, `base64`, `doesnt_contain`, `encoding` y `list`.

`custom` y `attributes` quedan vacíos a propósito: los mensajes propios se escriben literales en el `after()` de cada Form Request, al lado de la regla que los motiva, y en los formularios el error se muestra debajo de su campo ya etiquetado.

Ojo con `validation.uploaded`: no es un error de la app sino de PHP descartando el archivo antes de validar, casi siempre por `upload_max_filesize`. Las reglas del repo piden `max:4096` (4 MB), así que el php.ini del entorno tiene que acompañar o el mensaje aparece sin que el usuario haya hecho nada mal.
