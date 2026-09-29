<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Turno;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * La lista de clientes en CSV, con los mismos filtros que el listado.
 *
 * Pensado para abrirse en Excel en español: UTF-8 con BOM (si no, las tildes
 * salen rotas) y `;` como separador (la coma es el decimal en es-UY).
 */
class ClienteExportController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:export,'.Cliente::class),
        ];
    }

    /**
     * Download the filtered client list.
     */
    public function __invoke(Request $request): StreamedResponse
    {
        $filtros = ClienteController::filtros($request);
        $archivo = 'clientes-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($filtros): void {
            $salida = fopen('php://output', 'w');

            fwrite($salida, "\u{FEFF}");

            fputcsv($salida, [
                'Nombre', 'Apellido', 'Celular', 'Email',
                'Acepta novedades', 'Fecha del consentimiento',
                'Turnos', 'Último turno', 'Rubros', 'Vehículos', 'Notas',
            ], ';');

            Cliente::filtrados($filtros)
                ->with('turnos.servicio')
                ->withCount('turnos')
                ->chunk(200, function ($clientes) use ($salida): void {
                    foreach ($clientes as $cliente) {
                        /** @var Turno|null $ultimo */
                        $ultimo = $cliente->turnos->first();

                        fputcsv($salida, [
                            $cliente->nombre,
                            $cliente->apellido,
                            $cliente->celular,
                            $cliente->email,
                            $cliente->acepta_novedades ? 'Sí' : 'No',
                            $cliente->acepta_novedades_at?->format('d/m/Y H:i'),
                            $cliente->turnos_count,
                            $ultimo?->inicia_at->format('d/m/Y'),
                            $cliente->turnos
                                ->map(fn (Turno $turno): string => $turno->servicio->rubro->label())
                                ->unique()
                                ->implode(', '),
                            $cliente->vehiculos()
                                ->map(fn (array $vehiculo): string => trim(implode(' ', array_filter([
                                    $vehiculo['marca'], $vehiculo['modelo'], $vehiculo['anio'],
                                ])).($vehiculo['matricula'] ? ' ('.$vehiculo['matricula'].')' : '')))
                                ->implode(', '),
                            $cliente->notas,
                        ], ';');
                    }
                });

            fclose($salida);
        }, $archivo, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
