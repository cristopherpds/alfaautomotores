<?php

namespace Database\Seeders;

use App\Models\Producto;
use Illuminate\Database\Seeder;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;

class ProductoSeeder extends Seeder
{
    /**
     * Cargar el catálogo de movilidad desde `database/data/productos.json`.
     *
     * Las 56 filas salen de dos lugares: la tienda del proveedor
     * (worldsports.com.uy, que publica su catálogo en la Store API de
     * WooCommerce) y los catálogos impresos 2026, de donde vienen los modelos
     * que la web ya no lista. Los primeros traen precio en pesos y el stock
     * real; los segundos van sin precio, porque el precio de lista de un
     * catálogo viejo no sirve para el mostrador.
     *
     * La foto de portada, cuando la hay, vive en `database/data/productos/` y
     * se copia al disco `public` conservando el nombre original: es la clave
     * de idempotencia, igual que en `EntregaSeeder`. Las fotos que carguen
     * después desde el panel sí van con nombre hasheado.
     *
     * Se respeta el orden del archivo: la primera fila es la más reciente, así
     * la grilla arranca con lo que el proveedor tiene hoy.
     */
    public function run(): void
    {
        $filas = json_decode(
            (string) file_get_contents(database_path('data/productos.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $momento = now();

        foreach (is_array($filas) ? $filas : [] as $indice => $fila) {
            if (! is_array($fila)) {
                continue;
            }

            $producto = Producto::firstOrNew(['slug' => (string) $fila['slug']]);

            $producto->fill([
                'nombre' => $fila['nombre'],
                'familia' => $fila['familia'],
                'codigo' => $fila['codigo'] ?? null,
                'precio' => $fila['precio'] ?? null,
                'moneda' => $fila['moneda'],
                'estado' => $fila['estado'],
                'resumen' => $fila['resumen'],
                'desc' => $fila['desc'],
                'specs' => $fila['specs'],
                'colores' => $fila['colores'],
            ]);

            $producto->created_at = $momento->copy()->subMinutes($indice);
            $producto->updated_at = $producto->created_at;

            $producto->save();

            $this->importarPortada($producto, $fila['foto'] ?? null);
        }
    }

    /**
     * Copiar la foto de portada al disco público y registrarla.
     *
     * Un producto sin foto no es un error: la grilla cae en el marcador de
     * «foto pendiente» del sistema de diseño, que además le dice a quien carga
     * el catálogo qué falta.
     */
    private function importarPortada(Producto $producto, ?string $archivo): void
    {
        if ($archivo === null) {
            return;
        }

        $origen = database_path('data/productos/'.$archivo);

        if (! is_file($origen)) {
            return;
        }

        $ruta = $producto->carpetaDeFotos().'/'.$archivo;

        if (! Storage::disk('public')->exists($ruta)) {
            Storage::disk('public')->putFileAs(
                $producto->carpetaDeFotos(),
                new File($origen),
                $archivo,
            );
        }

        $producto->fotos()->firstOrCreate(['ruta' => $ruta], ['orden' => 0]);
    }
}
