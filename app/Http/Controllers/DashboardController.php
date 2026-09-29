<?php

namespace App\Http\Controllers;

use App\Enums\EstadoProducto;
use App\Enums\EstadoTurno;
use App\Enums\EstadoVehiculo;
use App\Enums\FamiliaProducto;
use App\Enums\OrigenTurno;
use App\Enums\Rubro;
use App\Models\Auditoria;
use App\Models\Producto;
use App\Models\Turno;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La primera pantalla del panel: qué hay que hacer hoy y cómo está el stock.
 *
 * Todo sale de consultas agregadas o acotadas (`take()`), nunca de traer una
 * tabla entera. Stock, catálogo y turnos los ve todo el equipo, igual que en
 * sus listados; la actividad reciente, sólo quien puede leer la auditoría.
 */
class DashboardController extends Controller
{
    /**
     * Cuántos días publicado hace que un vehículo cuente como «no rota».
     */
    public const DIAS_ESTANCADO = 60;

    /**
     * Cuántos días hace que un borrador cuente como olvidado.
     */
    public const DIAS_BORRADOR_VIEJO = 7;

    /**
     * Cuántos ítems muestra cada lista «para revisar».
     */
    private const POR_LISTA = 5;

    /**
     * Show the dashboard.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('dashboard', [
            'hoy' => $this->turnosDeHoy(),
            'pendientes' => $this->pendientes(),
            'stock' => $this->stock(),
            'revisar' => $this->paraRevisar(),
            'catalogo' => [
                'movilidad' => $this->seccion(bicicletas: false),
                'bicicletas' => $this->seccion(bicicletas: true),
            ],
            'semana' => $this->semana(),
            'actividad' => $request->user()->can('viewAny', Auditoria::class)
                ? $this->actividad()
                : null,
        ]);
    }

    /**
     * Los turnos de hoy que siguen en pie, separados por rubro.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function turnosDeHoy(): array
    {
        $turnos = Turno::entre(now()->startOfDay(), now()->endOfDay())
            ->whereNot('estado', EstadoTurno::Cancelado)
            ->get();

        $porRubro = [];

        foreach (Rubro::cases() as $rubro) {
            $porRubro[$rubro->value] = $turnos
                ->filter(fn (Turno $turno): bool => $turno->servicio->rubro === $rubro)
                ->map(fn (Turno $turno): array => [
                    'id' => $turno->id,
                    'hora' => $turno->inicia_at->format('H:i'),
                    'cliente' => $turno->nombre.' '.$turno->apellido,
                    'servicio' => $turno->servicio->nombre,
                    'estado' => $turno->estado->value,
                    'estadoLabel' => $turno->estado->label(),
                ])
                ->values()
                ->all();
        }

        return $porRubro;
    }

    /**
     * Los turnos de la web que esperan confirmación, del que más espera al que
     * menos. Sólo los que todavía no pasaron.
     *
     * @return array{total: int, lista: list<array<string, mixed>>}
     */
    private function pendientes(): array
    {
        $consulta = Turno::query()
            ->where('estado', EstadoTurno::Pendiente)
            ->where('inicia_at', '>=', now()->startOfDay());

        return [
            'total' => (clone $consulta)->count(),
            'lista' => $consulta
                ->with('servicio')
                ->orderBy('created_at')
                ->orderBy('id')
                ->take(self::POR_LISTA)
                ->get()
                ->map(fn (Turno $turno): array => [
                    'id' => $turno->id,
                    'cliente' => $turno->nombre.' '.$turno->apellido,
                    'servicio' => $turno->servicio->nombre,
                    'rubroLabel' => $turno->servicio->rubro->label(),
                    'cuando' => $turno->inicia_at->toIso8601String(),
                    'recibido' => $turno->created_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }

    /**
     * El stock de autos por estado, los destacados y lo vendido este mes
     * (por `vendido_at`, que pone el modelo al pasar a vendido).
     *
     * @return array<string, int>
     */
    private function stock(): array
    {
        $porEstado = Vehiculo::query()
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return [
            'publicados' => (int) ($porEstado[EstadoVehiculo::Publicado->value] ?? 0),
            'reservados' => (int) ($porEstado[EstadoVehiculo::Reservado->value] ?? 0),
            'borradores' => (int) ($porEstado[EstadoVehiculo::Borrador->value] ?? 0),
            'vendidosMes' => Vehiculo::query()
                ->where('estado', EstadoVehiculo::Vendido)
                ->where('vendido_at', '>=', now()->startOfMonth())
                ->count(),
            'destacados' => Vehiculo::contarDestacados(),
            'maxDestacados' => Vehiculo::MAX_DESTACADOS,
        ];
    }

    /**
     * Vehículos que piden una mano: sin fotos, borradores olvidados y
     * publicados que no rotan.
     *
     * @return array<string, array{total: int, lista: list<array{id: int, titulo: string, dias: int}>}>
     */
    private function paraRevisar(): array
    {
        return [
            'sinFotos' => $this->listaDeVehiculos(
                Vehiculo::query()
                    ->whereNot('estado', EstadoVehiculo::Borrador)
                    ->whereNot('estado', EstadoVehiculo::Vendido)
                    ->doesntHave('fotos'),
            ),
            'borradoresViejos' => $this->listaDeVehiculos(
                Vehiculo::query()
                    ->where('estado', EstadoVehiculo::Borrador)
                    ->where('created_at', '<', now()->subDays(self::DIAS_BORRADOR_VIEJO)),
            ),
            'estancados' => $this->listaDeVehiculos(
                Vehiculo::query()
                    ->where('estado', EstadoVehiculo::Publicado)
                    ->where('created_at', '<', now()->subDays(self::DIAS_ESTANCADO)),
            ),
        ];
    }

    /**
     * El total de una consulta y sus primeros vehículos, del más viejo.
     *
     * @param  Builder<Vehiculo>  $consulta
     * @return array{total: int, lista: list<array{id: int, titulo: string, dias: int}>}
     */
    private function listaDeVehiculos(Builder $consulta): array
    {
        return [
            'total' => (clone $consulta)->count(),
            'lista' => $consulta
                ->orderBy('created_at')
                ->orderBy('id')
                ->take(self::POR_LISTA)
                ->get()
                ->map(fn (Vehiculo $vehiculo): array => [
                    'id' => $vehiculo->id,
                    'titulo' => $vehiculo->titulo(),
                    'dias' => (int) $vehiculo->created_at?->diffInDays(now()),
                ])
                ->all(),
        ];
    }

    /**
     * Cómo está una sección del catálogo de movilidad.
     *
     * @return array{publicados: int, sinStock: int, porEncargue: int, sinFotos: int}
     */
    private function seccion(bool $bicicletas): array
    {
        $deLaSeccion = fn () => Producto::query()->where(
            'familia',
            $bicicletas ? '=' : '!=',
            FamiliaProducto::Bicicleta,
        );

        $porEstado = $deLaSeccion()
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return [
            'publicados' => (int) ($porEstado[EstadoProducto::Publicado->value] ?? 0),
            'sinStock' => (int) ($porEstado[EstadoProducto::SinStock->value] ?? 0),
            'porEncargue' => (int) ($porEstado[EstadoProducto::PorEncargue->value] ?? 0),
            'sinFotos' => $deLaSeccion()
                ->whereNot('estado', EstadoProducto::Borrador)
                ->doesntHave('fotos')
                ->count(),
        ];
    }

    /**
     * Los próximos siete días del taller y el lavadero, más cómo entraron los
     * turnos del último mes.
     *
     * @return array{dias: list<array<string, mixed>>, cancelados: int, origen: array{web: int, panel: int}}
     */
    private function semana(): array
    {
        $desde = now()->startOfDay();
        $hasta = now()->addDays(6)->endOfDay();

        $turnos = Turno::entre($desde, $hasta)->get();
        $vigentes = $turnos->reject(fn (Turno $turno): bool => $turno->estado === EstadoTurno::Cancelado);

        $dias = [];

        // Las fechas de la app son inmutables: `addDay()` no mueve `$dia`, así
        // que cada día se calcula desde el índice.
        for ($indice = 0; $indice < 7; $indice++) {
            $dia = $desde->addDays($indice);
            $delDia = $vigentes->filter(fn (Turno $turno): bool => $turno->inicia_at->isSameDay($dia));

            $dias[] = [
                'fecha' => $dia->toDateString(),
                'taller' => $delDia->filter(fn (Turno $turno): bool => $turno->servicio->rubro === Rubro::Taller)->count(),
                'lavadero' => $delDia->filter(fn (Turno $turno): bool => $turno->servicio->rubro === Rubro::Lavadero)->count(),
            ];
        }

        $origen = Turno::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('origen, count(*) as total')
            ->groupBy('origen')
            ->pluck('total', 'origen');

        return [
            'dias' => $dias,
            'cancelados' => $turnos->count() - $vigentes->count(),
            'origen' => [
                'web' => (int) ($origen[OrigenTurno::Web->value] ?? 0),
                'panel' => (int) ($origen[OrigenTurno::Panel->value] ?? 0),
            ],
        ];
    }

    /**
     * Las últimas entradas de la auditoría.
     *
     * @return list<array<string, mixed>>
     */
    private function actividad(): array
    {
        return Auditoria::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->take(8)
            ->get()
            ->map(fn (Auditoria $entrada): array => [
                'id' => $entrada->id,
                'fecha' => $entrada->created_at->toIso8601String(),
                'usuario' => $entrada->usuario,
                'accion' => $entrada->accion->value,
                'tipo' => $entrada->tipo,
                'etiqueta' => $entrada->etiqueta,
            ])
            ->all();
    }
}
