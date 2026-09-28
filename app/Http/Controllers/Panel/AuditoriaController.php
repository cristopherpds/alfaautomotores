<?php

namespace App\Http\Controllers\Panel;

use App\Enums\AccionAuditoria;
use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Quién creó, modificó o eliminó qué en el panel.
 *
 * A diferencia de los listados del catálogo, acá los filtros y la paginación
 * son del servidor: la tabla crece sin tope.
 */
class AuditoriaController extends Controller implements HasMiddleware
{
    /**
     * Cuántas entradas por página.
     */
    private const POR_PAGINA = 50;

    /**
     * Get the middleware that should be assigned to the controller.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.Auditoria::class),
        ];
    }

    /**
     * Show the audit log, newest first.
     */
    public function index(Request $request): Response
    {
        /** @var array{usuario?: string|null, tipo?: string|null, accion?: string|null, desde?: string|null, hasta?: string|null} $filtros */
        $filtros = $request->validate([
            'usuario' => ['nullable', 'string', 'max:255'],
            'tipo' => ['nullable', 'string', 'max:50'],
            'accion' => ['nullable', Rule::enum(AccionAuditoria::class)],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        $entradas = Auditoria::query()
            ->when($filtros['usuario'] ?? null, fn ($query, string $usuario) => $query->where('usuario', $usuario))
            ->when($filtros['tipo'] ?? null, fn ($query, string $tipo) => $query->where('tipo', $tipo))
            ->when($filtros['accion'] ?? null, fn ($query, string $accion) => $query->where('accion', $accion))
            ->when($filtros['desde'] ?? null, fn ($query, string $desde) => $query->whereDate('created_at', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn ($query, string $hasta) => $query->whereDate('created_at', '<=', $hasta))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::POR_PAGINA)
            ->withQueryString()
            ->through(fn (Auditoria $entrada): array => [
                'id' => $entrada->id,
                'fecha' => $entrada->created_at->toIso8601String(),
                'usuario' => $entrada->usuario,
                'accion' => $entrada->accion->value,
                'tipo' => $entrada->tipo,
                'etiqueta' => $entrada->etiqueta,
                'cambios' => $entrada->cambios ?? [],
                'ip' => $entrada->ip,
            ]);

        return Inertia::render('panel/auditoria/index', [
            'entradas' => $entradas,
            'filtros' => [
                'usuario' => $filtros['usuario'] ?? '',
                'tipo' => $filtros['tipo'] ?? '',
                'accion' => $filtros['accion'] ?? '',
                'desde' => $filtros['desde'] ?? '',
                'hasta' => $filtros['hasta'] ?? '',
            ],
            // Salen de la tabla y no de `users`: así aparecen los usuarios ya
            // borrados y «Cliente (sitio web)».
            'usuarios' => Auditoria::query()->distinct()->orderBy('usuario')->pluck('usuario'),
            'tipos' => Auditoria::query()->distinct()->orderBy('tipo')->pluck('tipo'),
            'acciones' => AccionAuditoria::options(),
        ]);
    }
}
