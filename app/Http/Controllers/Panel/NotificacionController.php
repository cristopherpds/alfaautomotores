<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * La campana del panel: los avisos de cada usuario (cotizaciones y turnos
 * que entran desde la web o WhatsApp).
 *
 * Lo pide `useNotificaciones` cada pocos segundos desde cualquier página.
 * Cada usuario ve y marca sólo los suyos: se buscan siempre por la relación
 * `notifications()` del usuario de la sesión, así un id ajeno da 404.
 */
class NotificacionController extends Controller
{
    /**
     * Cuántos avisos muestra la campana.
     */
    private const MAX_AVISOS = 10;

    /**
     * The unread count and the latest notices.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->resumen($this->usuario($request)));
    }

    /**
     * Mark one notice as read.
     */
    public function leida(Request $request, string $notificacion): JsonResponse
    {
        $usuario = $this->usuario($request);

        $usuario->notifications()->findOrFail($notificacion)->markAsRead();

        return response()->json($this->resumen($usuario));
    }

    /**
     * Mark every notice as read.
     */
    public function leerTodas(Request $request): JsonResponse
    {
        $usuario = $this->usuario($request);

        $usuario->unreadNotifications()->update(['read_at' => now()]);

        return response()->json($this->resumen($usuario));
    }

    /**
     * @return array{noLeidas: int, avisos: list<array{id: string, tipo: string, titulo: string, detalle: string, url: string|null, leida: bool, at: string}>}
     */
    private function resumen(User $usuario): array
    {
        return [
            'noLeidas' => $usuario->unreadNotifications()->count(),
            'avisos' => $usuario->notifications()
                ->limit(self::MAX_AVISOS)
                ->get()
                ->map(fn (DatabaseNotification $aviso): array => [
                    'id' => $aviso->id,
                    'tipo' => (string) ($aviso->data['tipo'] ?? 'aviso'),
                    'titulo' => (string) ($aviso->data['titulo'] ?? 'Aviso'),
                    'detalle' => (string) ($aviso->data['detalle'] ?? ''),
                    'url' => $aviso->data['url'] ?? null,
                    'leida' => $aviso->read_at !== null,
                    'at' => $aviso->created_at?->toIso8601String() ?? now()->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    private function usuario(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
