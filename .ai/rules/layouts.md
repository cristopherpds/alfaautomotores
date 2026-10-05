---
paths:
  - 'resources/js/layouts/**'
---

# Layouts

## El contenido del panel usa overflow-x-clip, no overflow-x-hidden
`AppContent` en `layouts/app/app-sidebar-layout.tsx` lleva `overflow-x-clip`. Con `overflow-x-hidden` el contenedor pasa a ser de scroll (overflow-y se vuelve auto) y cualquier `sticky` adentro deja de pegarse: así se rompían las vistas previas de Portada y del botón de WhatsApp, que van `lg:sticky lg:top-4`. `clip` recorta igual sin crear contenedor de scroll.
