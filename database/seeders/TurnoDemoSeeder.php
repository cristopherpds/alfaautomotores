<?php

namespace Database\Seeders;

use App\Enums\EstadoTurno;
use App\Enums\OrigenTurno;
use App\Models\Puesto;
use App\Models\Servicio;
use App\Models\Turno;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;

class TurnoDemoSeeder extends Seeder
{
    /**
     * Agenda de muestra para ver el calendario con contenido.
     *
     * **No se llama desde `DatabaseSeeder` a propósito**: son clientes
     * inventados y no tienen nada que hacer en producción. Se corre a mano:
     *
     *     php artisan db:seed --class=TurnoDemoSeeder
     *
     * Los turnos del pasado se crean derecho, sin cita en Zap: el paquete no
     * acepta citas con fecha vieja y un turno que ya pasó no necesita reservar
     * ningún horario. Los de acá en adelante van por `Turno::reservar()`, que es
     * el camino real, así que ocupan el hueco igual que una reserva web.
     *
     * @var list<array{dia: string, hora: string, servicio: string, nombre: string, apellido: string, celular: string, marca: string, modelo: string, anio: int, estado: EstadoTurno, origen: OrigenTurno, comentario?: string}>
     */
    private const AGENDA = [
        // --- Setiembre: lo que ya pasó -----------------------------------
        ['dia' => '2026-09-01', 'hora' => '08:30', 'servicio' => 'service-completo', 'nombre' => 'Martín', 'apellido' => 'Cabrera', 'celular' => '099 412 336', 'marca' => 'Chevrolet', 'modelo' => 'Onix', 'anio' => 2019, 'estado' => EstadoTurno::Completado, 'origen' => OrigenTurno::Web],
        ['dia' => '2026-09-01', 'hora' => '14:00', 'servicio' => 'pastillas-de-freno', 'nombre' => 'Lucía', 'apellido' => 'Ferreira', 'celular' => '098 776 210', 'marca' => 'Fiat', 'modelo' => 'Cronos', 'anio' => 2021, 'estado' => EstadoTurno::Completado, 'origen' => OrigenTurno::Panel],
        ['dia' => '2026-09-02', 'hora' => '09:30', 'servicio' => 'alineacion-y-balanceo', 'nombre' => 'Diego', 'apellido' => 'Rossi', 'celular' => '091 305 884', 'marca' => 'Volkswagen', 'modelo' => 'Gol', 'anio' => 2016, 'estado' => EstadoTurno::Completado, 'origen' => OrigenTurno::Web],
        ['dia' => '2026-09-03', 'hora' => '08:30', 'servicio' => 'aceite-y-filtro', 'nombre' => 'Carolina', 'apellido' => 'Méndez', 'celular' => '094 128 907', 'marca' => 'Renault', 'modelo' => 'Sandero', 'anio' => 2018, 'estado' => EstadoTurno::Completado, 'origen' => OrigenTurno::Panel],
        ['dia' => '2026-09-03', 'hora' => '15:30', 'servicio' => 'scanner-obd2', 'nombre' => 'Rodrigo', 'apellido' => 'Silveira', 'celular' => '099 550 143', 'marca' => 'Ford', 'modelo' => 'Ka', 'anio' => 2017, 'estado' => EstadoTurno::Completado, 'origen' => OrigenTurno::Web, 'comentario' => 'Se prende la luz del motor en frío.'],
        ['dia' => '2026-09-04', 'hora' => '10:00', 'servicio' => 'aire-acondicionado', 'nombre' => 'Valeria', 'apellido' => 'Núñez', 'celular' => '092 664 018', 'marca' => 'Peugeot', 'modelo' => '208', 'anio' => 2020, 'estado' => EstadoTurno::Completado, 'origen' => OrigenTurno::Web],

        // --- Setiembre: de hoy en adelante -------------------------------
        ['dia' => '2026-09-07', 'hora' => '15:30', 'servicio' => 'service-completo', 'nombre' => 'Fernando', 'apellido' => 'Duarte', 'celular' => '099 201 774', 'marca' => 'Toyota', 'modelo' => 'Etios', 'anio' => 2019, 'estado' => EstadoTurno::Confirmado, 'origen' => OrigenTurno::Panel],

        /* Dos autos a la misma hora: los dos puestos ocupados. Es el caso que
           conviene mostrar, porque explica por qué la capacidad son puestos. */
        ['dia' => '2026-09-08', 'hora' => '08:30', 'servicio' => 'aceite-y-filtro', 'nombre' => 'Andrés', 'apellido' => 'Piriz', 'celular' => '091 887 452', 'marca' => 'Chevrolet', 'modelo' => 'Prisma', 'anio' => 2018, 'estado' => EstadoTurno::Confirmado, 'origen' => OrigenTurno::Web],
        ['dia' => '2026-09-08', 'hora' => '08:30', 'servicio' => 'bateria', 'nombre' => 'Soledad', 'apellido' => 'Rivas', 'celular' => '098 330 617', 'marca' => 'Fiat', 'modelo' => 'Uno', 'anio' => 2014, 'estado' => EstadoTurno::Pendiente, 'origen' => OrigenTurno::Web, 'comentario' => 'No arranca si queda dos días parado.'],

        ['dia' => '2026-09-09', 'hora' => '14:00', 'servicio' => 'pre-inspeccion', 'nombre' => 'Gabriel', 'apellido' => 'Olivera', 'celular' => '099 074 285', 'marca' => 'Nissan', 'modelo' => 'March', 'anio' => 2015, 'estado' => EstadoTurno::Confirmado, 'origen' => OrigenTurno::Panel],

        ['dia' => '2026-09-10', 'hora' => '09:15', 'servicio' => 'aceite-y-filtro', 'nombre' => 'Natalia', 'apellido' => 'Bentancor', 'celular' => '094 619 730', 'marca' => 'Volkswagen', 'modelo' => 'Polo', 'anio' => 2022, 'estado' => EstadoTurno::Pendiente, 'origen' => OrigenTurno::Web],
        ['dia' => '2026-09-10', 'hora' => '15:30', 'servicio' => 'pastillas-de-freno', 'nombre' => 'Pablo', 'apellido' => 'Machado', 'celular' => '099 845 062', 'marca' => 'Citroën', 'modelo' => 'C3', 'anio' => 2017, 'estado' => EstadoTurno::Confirmado, 'origen' => OrigenTurno::Web, 'comentario' => 'Chilla al frenar de mañana.'],

        ['dia' => '2026-09-14', 'hora' => '10:30', 'servicio' => 'alineacion-y-balanceo', 'nombre' => 'Camila', 'apellido' => 'Suárez', 'celular' => '092 158 493', 'marca' => 'Hyundai', 'modelo' => 'HB20', 'anio' => 2021, 'estado' => EstadoTurno::Pendiente, 'origen' => OrigenTurno::Web],
        ['dia' => '2026-09-16', 'hora' => '14:00', 'servicio' => 'scanner-obd2', 'nombre' => 'Sebastián', 'apellido' => 'Correa', 'celular' => '091 442 806', 'marca' => 'Ford', 'modelo' => 'Fiesta', 'anio' => 2016, 'estado' => EstadoTurno::Pendiente, 'origen' => OrigenTurno::Web],
        ['dia' => '2026-09-22', 'hora' => '08:30', 'servicio' => 'service-completo', 'nombre' => 'Alejandra', 'apellido' => 'Pereyra', 'celular' => '099 337 951', 'marca' => 'Renault', 'modelo' => 'Duster', 'anio' => 2020, 'estado' => EstadoTurno::Confirmado, 'origen' => OrigenTurno::Panel],

        // --- Octubre ------------------------------------------------------
        ['dia' => '2026-10-01', 'hora' => '09:00', 'servicio' => 'bateria', 'nombre' => 'Joaquín', 'apellido' => 'Tabárez', 'celular' => '098 512 379', 'marca' => 'Suzuki', 'modelo' => 'Swift', 'anio' => 2019, 'estado' => EstadoTurno::Pendiente, 'origen' => OrigenTurno::Web],
        ['dia' => '2026-10-02', 'hora' => '14:00', 'servicio' => 'service-completo', 'nombre' => 'Mariana', 'apellido' => 'Acosta', 'celular' => '099 660 428', 'marca' => 'Chevrolet', 'modelo' => 'Spin', 'anio' => 2018, 'estado' => EstadoTurno::Confirmado, 'origen' => OrigenTurno::Web],
        ['dia' => '2026-10-06', 'hora' => '10:45', 'servicio' => 'aceite-y-filtro', 'nombre' => 'Nicolás', 'apellido' => 'Barrios', 'celular' => '091 209 764', 'marca' => 'Fiat', 'modelo' => 'Argo', 'anio' => 2022, 'estado' => EstadoTurno::Pendiente, 'origen' => OrigenTurno::Web],
        ['dia' => '2026-10-06', 'hora' => '15:30', 'servicio' => 'pastillas-de-freno', 'nombre' => 'Florencia', 'apellido' => 'Gutiérrez', 'celular' => '094 883 015', 'marca' => 'Volkswagen', 'modelo' => 'Voyage', 'anio' => 2017, 'estado' => EstadoTurno::Pendiente, 'origen' => OrigenTurno::Web],
        ['dia' => '2026-10-13', 'hora' => '14:00', 'servicio' => 'pre-inspeccion', 'nombre' => 'Ignacio', 'apellido' => 'Fernández', 'celular' => '099 471 260', 'marca' => 'Toyota', 'modelo' => 'Yaris', 'anio' => 2020, 'estado' => EstadoTurno::Confirmado, 'origen' => OrigenTurno::Panel, 'comentario' => 'Quiere el informe por escrito.'],
    ];

    /**
     * Cargar la agenda de muestra.
     */
    public function run(): void
    {
        if (Servicio::count() === 0) {
            $this->call(ServicioSeeder::class);
        }

        /* Sin puestos con horario no hay dónde meter un turno. */
        if (Puesto::query()->where('activo', true)->doesntExist()) {
            Artisan::call('taller:agenda');
        }

        $hoy = now()->startOfDay();
        $cargados = 0;

        foreach (self::AGENDA as $fila) {
            $servicio = Servicio::where('slug', $fila['servicio'])->first();

            if ($servicio === null) {
                continue;
            }

            $inicio = Date::parse($fila['dia'].' '.$fila['hora']);

            $datos = [
                'nombre' => $fila['nombre'],
                'apellido' => $fila['apellido'],
                'email' => mb_strtolower($fila['nombre'].'.'.$fila['apellido']).'@example.com',
                'celular' => $fila['celular'],
                'vehiculo_marca' => $fila['marca'],
                'vehiculo_modelo' => $fila['modelo'],
                'vehiculo_anio' => $fila['anio'],
                'comentario' => $fila['comentario'] ?? null,
            ];

            /* Idempotente por cliente y horario: correrlo de nuevo no duplica. */
            $existe = Turno::query()
                ->where('inicia_at', $inicio)
                ->where('apellido', $fila['apellido'])
                ->exists();

            if ($existe) {
                continue;
            }

            if ($inicio->lessThan($hoy)) {
                Turno::create([
                    ...$datos,
                    'servicio_id' => $servicio->id,
                    'puesto_id' => Puesto::query()->where('activo', true)->value('id'),
                    'schedule_id' => null,
                    'estado' => $fila['estado'],
                    'origen' => $fila['origen'],
                    'inicia_at' => $inicio,
                    'termina_at' => $inicio->copy()->addMinutes($servicio->duracion),
                ]);

                $cargados++;

                continue;
            }

            $turno = Turno::reservar($servicio, $inicio, $datos, $fila['estado'], $fila['origen']);

            if ($turno === null) {
                $this->command->warn("Sin lugar para {$fila['apellido']} el {$fila['dia']} a las {$fila['hora']}.");

                continue;
            }

            $cargados++;
        }

        $this->command->info("{$cargados} turno(s) de muestra cargados.");
    }
}
