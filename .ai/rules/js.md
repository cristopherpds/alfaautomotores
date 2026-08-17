---
paths:
  - 'resources/js/**'
---

# Js

## El panel se construye con shadcn/ui; el sitio público es la excepción
Todo lo que está detrás del login (dashboard, usuarios, settings) se arma con componentes de shadcn/ui + Tailwind. Antes de escribir markup propio, revisá si el componente ya existe en `resources/js/components/ui/` y si no, agregalo con `npx shadcn@latest add <componente>` (proyecto: style new-york, base radix, iconos lucide, alias `@/`).
Seguí los patrones oficiales en lugar de improvisar: acciones de fila = DropdownMenu con trigger MoreHorizontal y los items dentro de DropdownMenuGroup; badges con `Badge`; estados vacíos y separadores con los componentes correspondientes. Nada de divs estilizados a mano para lo que ya tiene componente. Los iconos no llevan clases de tamaño: `Button` y `DropdownMenuItem` los dimensionan por CSS. Usá tokens semánticos (`text-muted-foreground`, `bg-primary`), no colores crudos ni overrides `dark:`.
Trampa conocida: un Dialog que se abre desde un DropdownMenuItem tiene que estar FUERA del DropdownMenuContent y controlado por estado (`open`/`onOpenChange`), o se desmonta al cerrarse el menú. Ver `resources/js/pages/users/index.tsx`.
Excepción: el sitio público (`welcome`, `catalogo`, `vehiculos/*` y `components/alfa/**`) NO usa shadcn ni Tailwind — usa el sistema de diseño propio de `resources/css/alfa.css`.
