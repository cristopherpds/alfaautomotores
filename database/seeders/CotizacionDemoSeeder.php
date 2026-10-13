<?php

namespace Database\Seeders;

use App\Enums\EstadoCotizacion;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\VehiculoImagen;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;

class CotizacionDemoSeeder extends Seeder
{
    /**
     * Solicitudes de «Cotizá tu auto» de muestra, para ver el panel con
     * contenido.
     *
     * **No se llama desde `DatabaseSeeder` a propósito**: son personas
     * inventadas y no tienen nada que hacer en producción. Se corre a mano:
     *
     *     php artisan db:seed --class=CotizacionDemoSeeder
     *
     * Las fotos se toman prestadas de la galería de un vehículo del stock
     * (`vehiculo` + posición en su orden) y se copian al disco privado, como
     * quedaría una subida real. Si ese vehículo no está o no tiene la foto, la
     * casilla queda vacía. Es idempotente por celular.
     *
     * @var list<array{dias: int, nombre: string, apellido: string, celular: string, marca: string, modelo: string, anio: int, kilometros: int, estado: EstadoCotizacion, detalles?: string, vehiculo?: int, fotos?: array<string, int>}>
     */
    private const SOLICITUDES = [
        ['dias' => 1, 'nombre' => 'Lorena', 'apellido' => 'Giménez', 'celular' => '099 284 615', 'marca' => 'Hyundai', 'modelo' => 'HB20', 'anio' => 2022, 'kilometros' => 41200, 'estado' => EstadoCotizacion::Nueva, 'detalles' => 'Única dueña, services en la agencia. Lo quiero entregar como parte de pago de una camioneta.', 'vehiculo' => 35, 'fotos' => ['derecha' => 2, 'izquierda' => 4, 'interior' => 7, 'tablero' => 6]],
        ['dias' => 1, 'nombre' => 'Matías', 'apellido' => 'Sosa', 'celular' => '098 417 302', 'marca' => 'Toyota', 'modelo' => 'Hilux DX 4x4', 'anio' => 2018, 'kilometros' => 178500, 'estado' => EstadoCotizacion::Nueva, 'detalles' => 'Cubiertas nuevas. Tiene un golpe chico en la caja.', 'vehiculo' => 43, 'fotos' => ['derecha' => 3, 'izquierda' => 1, 'interior' => 9, 'tablero' => 7]],
        ['dias' => 1, 'nombre' => 'Florencia', 'apellido' => 'Lima', 'celular' => '091 563 840', 'marca' => 'Volkswagen', 'modelo' => 'Up', 'anio' => 2017, 'kilometros' => 86000, 'estado' => EstadoCotizacion::Nueva, 'vehiculo' => 37, 'fotos' => ['derecha' => 1, 'izquierda' => 4, 'interior' => 8, 'tablero' => 6]],
        ['dias' => 3, 'nombre' => 'Gonzalo', 'apellido' => 'Pintos', 'celular' => '099 730 158', 'marca' => 'Renault', 'modelo' => 'Captur', 'anio' => 2018, 'kilometros' => 82000, 'estado' => EstadoCotizacion::Contactada, 'detalles' => 'Automática. Le falta el service de los 90.000.', 'vehiculo' => 52, 'fotos' => ['derecha' => 6, 'izquierda' => 1, 'interior' => 8, 'tablero' => 9]],
        ['dias' => 4, 'nombre' => 'Verónica', 'apellido' => 'Dutra', 'celular' => '094 205 671', 'marca' => 'Chevrolet', 'modelo' => 'Agile', 'anio' => 2011, 'kilometros' => 163000, 'estado' => EstadoCotizacion::Contactada, 'detalles' => 'Las demás fotos se las mando por WhatsApp.', 'vehiculo' => 32, 'fotos' => ['derecha' => 1, 'tablero' => 8]],
        ['dias' => 6, 'nombre' => 'Héctor', 'apellido' => 'Larrosa', 'celular' => '098 641 097', 'marca' => 'Peugeot', 'modelo' => '207 Compact', 'anio' => 2012, 'kilometros' => 210000, 'estado' => EstadoCotizacion::Descartada, 'detalles' => 'Motor hecho hace un año. Papeles al día.'],
        ['dias' => 8, 'nombre' => 'Daniela', 'apellido' => 'Viera', 'celular' => '092 318 476', 'marca' => 'Fiat', 'modelo' => 'Mobi', 'anio' => 2021, 'kilometros' => 33500, 'estado' => EstadoCotizacion::Nueva],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $nuevas = 0;

        foreach (self::SOLICITUDES as $posicion => $solicitud) {
            $celular = Cliente::normalizarCelular($solicitud['celular']);

            $yaEsta = Cotizacion::query()
                ->get(['celular'])
                ->contains(fn (Cotizacion $cotizacion): bool => Cliente::normalizarCelular($cotizacion->celular) === $celular);

            if ($yaEsta) {
                continue;
            }

            $this->crear($solicitud, $posicion);
            $nuevas++;
        }

        $this->command?->info("Cotizaciones de muestra: {$nuevas} nuevas.");
    }

    /**
     * @param  array{dias: int, nombre: string, apellido: string, celular: string, marca: string, modelo: string, anio: int, kilometros: int, estado: EstadoCotizacion, detalles?: string, vehiculo?: int, fotos?: array<string, int>}  $solicitud
     */
    private function crear(array $solicitud, int $posicion): void
    {
        $cliente = Cliente::desdeReserva([
            'nombre' => $solicitud['nombre'],
            'apellido' => $solicitud['apellido'],
            'email' => null,
            'celular' => $solicitud['celular'],
        ]);

        $cotizacion = new Cotizacion([
            'marca' => $solicitud['marca'],
            'modelo' => $solicitud['modelo'],
            'anio' => $solicitud['anio'],
            'kilometros' => $solicitud['kilometros'],
            'detalles' => $solicitud['detalles'] ?? null,
            'nombre' => $solicitud['nombre'],
            'apellido' => $solicitud['apellido'],
            'celular' => $solicitud['celular'],
            'estado' => $solicitud['estado'],
        ]);
        $cotizacion->cliente()->associate($cliente);

        /* En horario de atención y siempre de ayer para atrás: dos del mismo
           día no comparten hora y ninguna queda en el futuro. */
        $recibida = Date::today()->subDays($solicitud['dias'])->setTime(9 + 2 * ($posicion % 5), 10 + 7 * $posicion);
        $cotizacion->created_at = $recibida;
        $cotizacion->updated_at = $recibida;
        $cotizacion->save();

        $galeria = isset($solicitud['vehiculo'])
            ? VehiculoImagen::where('vehiculo_id', $solicitud['vehiculo'])->orderBy('orden')->pluck('ruta')->all()
            : [];

        foreach ($solicitud['fotos'] ?? [] as $lado => $posicion) {
            $origen = $galeria[$posicion] ?? null;

            if ($origen === null || ! Storage::disk('public')->exists($origen)) {
                continue;
            }

            $destino = Cotizacion::CARPETA.'/'.$cotizacion->id.'/'.$lado.'.jpg';
            Storage::disk('local')->put($destino, Storage::disk('public')->get($origen));
            $cotizacion->{'foto_'.$lado} = $destino;
        }

        $cotizacion->saveQuietly();
    }
}
