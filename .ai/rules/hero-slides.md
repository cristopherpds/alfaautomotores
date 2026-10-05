---
paths:
  - 'app/Http/Requests/HeroSlides/**'
---

# Hero Slides

## Videos del hero: 20 MB necesitan php.ini acompañando
`HeroSlideRequest` acepta video MP4/WebM de hasta 20 MB (imagen, 4 MB). PHP descarta el archivo antes de validar si `upload_max_filesize` o `post_max_size` son menores, y el error sale como `validation.uploaded` sin culpa del usuario: el servidor necesita ambos en 20M o más. El fondo se exige al crear y al cambiar `tipo_fondo` (si no, el slide quedaría con un tipo que no es el de su archivo).
