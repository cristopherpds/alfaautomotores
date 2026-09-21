# Semilla de entregas

Las 40 fotos con las que arrancó la tira «Nuestros clientes» de la portada,
exportadas de las historias destacadas de `@alfaautomoviles2023`.

**Esto es la semilla, no el dato.** La fuente de verdad es la tabla `entregas`;
las fotos nuevas se suben desde el panel (`/panel/entregas`) y van a parar al
disco `public`. Copiar un archivo acá no lo publica: lo carga `EntregaSeeder`,
que se corre una sola vez por entorno.

```
php artisan db:seed --class=EntregaSeeder
```

El seeder es idempotente —se puede repetir sin duplicar— y saltea en silencio
todo lo que no siga la convención de nombres, este README incluido.

## Nombre del archivo

```
<orden>_<aaaa-mm-dd>.jpg      →      07_2025-11-14.jpg
```

La fecha es la de la entrega y es la que queda en la fila. El número de adelante
sólo ordena las entregas del mismo día: se importan en orden ascendente para que
los ids reproduzcan el orden con el que la tira se veía antes de la tabla.

Sirven las extensiones `.jpg`, `.jpeg`, `.png`, `.webp` y `.avif`.

## Formato

Los recuadros de la tira son **9:16**, como una historia de Instagram, y se
recortan con `object-fit: cover`. El más grande que se muestra mide 232px de
ancho, así que 640px alcanzan y sobran: estas están reducidas a 640px con
calidad 78 y pesan unos 80 kB cada una.
