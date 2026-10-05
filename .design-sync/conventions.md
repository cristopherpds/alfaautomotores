# Alfa Automotores — cómo diseñar con esta librería

Dos sistemas visuales conviven y **no se mezclan** en una misma pantalla:

| Superficie | Componentes | Estilo |
|---|---|---|
| **Panel** (todo lo que está detrás del login: dashboard, turnos, clientes, ajustes) | carpeta `general/` — shadcn/ui (Button, Card, Table, Dialog, Sidebar…) | utilidades Tailwind + tokens semánticos, tipografía Instrument Sans |
| **Sitio público** (portada, catálogo, ficha, taller, lavadero) | carpeta `alfa/` — VehicleCard, ProductoCatalogo, SiteHeader… | clases propias de `alfa.css` ("Catálogo Alfa"), tipografía Archivo, **sin Tailwind** |

Todo se importa de `window.AlfaUI` (también los íconos de lucide: `Plus`, `CarFront`, `MessageCircle`…, y `toast`, `cn`).

## Envoltorios obligatorios

- **Sitio público:** todo cuelga de `<div className="alfa">`. Las clases de `alfa.css` están anidadas bajo `.alfa` (chocan con utilidades de Tailwind como `.grid`, `.card`, `.badge`); sin ese contenedor los componentes de `alfa/` salen sin estilo.
- **Panel:** envolvé la app en `<TooltipProvider delayDuration={0}>` (así lo hace `app.tsx`). Sin él, `Tooltip` y `SidebarMenuButton tooltip={…}` rompen. La navegación lateral va dentro de `<SidebarProvider>` con `<Sidebar collapsible="icon" variant="inset">` + `<SidebarInset>`.
- `Toaster` se monta una vez; los avisos se disparan con `toast.success('…')`, `toast.error('…', { description })`.

## Panel: tokens, no colores

Usá tokens semánticos, nunca colores crudos ni overrides `dark:`: `bg-background`, `bg-card`, `bg-muted`, `bg-primary`, `bg-secondary`, `bg-accent`, `bg-sidebar`, `text-foreground`, `text-muted-foreground`, `text-primary-foreground`, `text-destructive`, `border-border`, `border-input`, `border-sidebar-border/70`, `rounded-md|lg|xl`. Layout: `flex`, `grid`, `gap-*`, `p-*`, `md:grid-cols-*`, `max-w-*`, `tabular-nums`, `truncate`.

Patrones de la casa:
- Acciones de fila = `DropdownMenu` con `Button variant="ghost" size="icon"` + `<MoreHorizontal />`, items dentro de `DropdownMenuGroup`; borrar = `DropdownMenuItem variant="destructive"`.
- Estados con `Badge` (`secondary` confirmado, `outline` pendiente, `destructive` cancelado); vacíos con `Empty` + `EmptyMedia variant="icon"`.
- Tablas dentro de `<div className="rounded-xl border border-sidebar-border/70">`.
- Los íconos dentro de `Button` y `DropdownMenuItem` no llevan clase de tamaño.

```jsx
const { Card, CardHeader, CardTitle, CardDescription, CardContent, Badge } = window.AlfaUI;
<Card className="w-96">
  <CardHeader><CardTitle>Taller</CardTitle><CardDescription>3 turnos hoy</CardDescription></CardHeader>
  <CardContent>
    <ul className="grid gap-2 text-sm">
      <li className="flex items-center gap-3">
        <span className="w-12 font-medium tabular-nums">08:30</span>
        <span className="min-w-0 flex-1 truncate">Martín Cabrera</span>
        <Badge variant="secondary">Confirmado</Badge>
      </li>
    </ul>
  </CardContent>
</Card>
```

## Sitio público: vocabulario de `alfa.css`

Sin utilidades Tailwind: maquetá con estas clases (todas bajo `.alfa`).
- Layout: `.shell` (ancho máximo + márgenes), `.section`, `.section__head`, `.section__link`, `.grid` (grilla de tarjetas), `.grid--duo`, `.strip`, `.pitch`.
- Texto: `.eyebrow` (etiqueta chica en mayúsculas, azul — el recurso tipográfico central), `.lede` (párrafo de apoyo), títulos `h1`/`h2` del propio elemento.
- Botones: `.btn` (tinta, mayúsculas espaciadas), `.btn--ghost`, `.btn--light` y `.btn--outline-dark` (sobre fondo oscuro), `.btn--outline-light` (sobre foto/video).
- Héroe: `.hero` > `.hero__inner.shell` > `.eyebrow` + `h1` + `.lede` + `.hero__actions`.
- Vacíos: `.empty`, `.empty__title`, `.empty__text`.
- Tokens: `--alfa-ink` #101012, `--alfa-white`, `--alfa-offwhite` #f4f4f2, `--alfa-blue` #1e1ee6 (acento), `--alfa-text-body`, `--alfa-text-muted`, `--alfa-border`, `--alfa-reserved`, `--alfa-whatsapp`, `--alfa-max`, `--alfa-gutter`, `--alfa-header`.
- Estética: ángulos rectos (sólo son redondos los íconos circulares y la tarjeta de WhatsApp), mucho blanco, tinta casi negra, un solo acento azul, mayúsculas con tracking amplio para etiquetas y CTAs.
- Sin foto cargada, `Photo` (y por lo tanto `VehicleCard`, `ProductoCard`, `Galeria`) muestran el marcador "Foto pendiente": es el estado diseñado, no un error. El contenedor de `Photo` necesita `position: relative` y `container-type: inline-size`.
- El nav de `SiteHeader` pasa a hamburguesa por debajo de 1023px de viewport.
- WhatsApp fuera del flujo: `BotonWhatsapp` es flotante (fixed). Para mostrar la tarjeta en línea usá `<div className="wpp wpp--vista-previa"><TarjetaWhatsapp …/><span className="wpp__boton"><IconoWhatsapp className="wpp__icono" /></span></div>`.

```jsx
const { VehicleGrid } = window.AlfaUI;
<div className="alfa">
  <section className="section">
    <div className="shell">
      <div className="section__head"><p className="eyebrow">Catálogo</p><h2>Usados seleccionados</h2></div>
      <VehicleGrid vehiculos={vehiculos} />
    </div>
  </section>
</div>
```

## Dónde está la verdad

`styles.css` (importa `fonts/fonts.css` y `_ds_bundle.css`, que contiene los tokens de `app.css`, las utilidades compiladas y todo `alfa.css`). Cada componente tiene su `.d.ts` (props) y `.prompt.md` (ejemplos). Los datos de ejemplo realistas: precios en USD para autos y en $ (UYU) para movilidad; local en Ituzaingó 779, Rivera, Uruguay.
