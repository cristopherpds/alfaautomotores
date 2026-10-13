<?php

namespace App\Ai\Tools;

use App\Enums\DireccionMensaje;
use App\Enums\TipoMensaje;
use App\Http\Requests\Cotizaciones\CotizacionRequest;
use App\Models\Cotizacion;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Registrar un usado para cotizar, igual que el formulario «Cotizá tu auto».
 *
 * Las fotos son las últimas que la persona mandó por el chat (hasta cuatro,
 * en el orden de los lados de `Cotizacion::FOTOS`). Queda en el panel, en
 * Cotizaciones, y la persona como lead en Clientes.
 */
class RegistrarCotizacion extends Herramienta
{
    /**
     * Hasta cuándo para atrás se toman fotos del chat.
     */
    private const HORAS_DE_FOTOS = 24;

    public function name(): string
    {
        return 'registrar_cotizacion';
    }

    public function description(): Stringable|string
    {
        return 'Registra un auto usado que la persona quiere vender o entregar, para que el equipo lo cotice. Toma solas las fotos que mandó por el chat. Necesita marca, modelo, año, kilómetros, nombre y apellido.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'marca' => $schema->string()->required(),
            'modelo' => $schema->string()->required(),
            'anio' => $schema->integer()->required(),
            'kilometros' => $schema->integer()->required(),
            'detalles' => $schema->string()->description('Lo que contó del estado del auto, si contó algo.'),
            'nombre' => $schema->string()->description('Si no tiene ficha.'),
            'apellido' => $schema->string()->description('Si no tiene ficha.'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $cliente = $this->conversacion->cliente;
        $nombre = $cliente->nombre ?? trim((string) ($request['nombre'] ?? ''));
        $apellido = $cliente->apellido ?? trim((string) ($request['apellido'] ?? ''));
        $anio = (int) ($request['anio'] ?? 0);
        $kilometros = (int) ($request['kilometros'] ?? -1);

        if ($nombre === '' || $apellido === '') {
            return $this->json(['error' => 'Faltan nombre y apellido: pedíselos.']);
        }

        if (blank($request['marca'] ?? null) || blank($request['modelo'] ?? null)
            || $anio < CotizacionRequest::ANIO_MINIMO || $anio > now()->year + 1 || $kilometros < 0) {
            return $this->json(['error' => 'Faltan datos o no son válidos: marca, modelo, año ('.CotizacionRequest::ANIO_MINIMO.' en adelante) y kilómetros.']);
        }

        $fotos = $this->conversacion->mensajes()
            ->where('direccion', DireccionMensaje::Entrante)
            ->where('tipo', TipoMensaje::Imagen)
            ->whereNotNull('media_ruta')
            ->where('created_at', '>=', now()->subHours(self::HORAS_DE_FOTOS))
            ->reorder()
            ->latest('id')
            ->limit(count(Cotizacion::FOTOS))
            ->pluck('media_ruta')
            ->reverse()
            ->values()
            ->all();

        $cotizacion = Cotizacion::recibir([
            'marca' => (string) $request['marca'],
            'modelo' => (string) $request['modelo'],
            'anio' => $anio,
            'kilometros' => $kilometros,
            'detalles' => filled($request['detalles'] ?? null) ? (string) $request['detalles'] : null,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'celular' => ReservarTurno::celular($this->conversacion),
        ], array_combine(
            array_slice(array_keys(Cotizacion::FOTOS), 0, count($fotos)),
            $fotos,
        ) ?: []);

        $this->conversacion->cliente()->associate($cotizacion->cliente_id);
        $this->conversacion->saveQuietly();

        return $this->json([
            'registrada' => true,
            'fotos' => count($fotos),
            'nota' => 'Avisale que el equipo la revisa y le escribe con la cotización. No des ningún precio.'
                .(count($fotos) === 0 ? ' Podés decirle que si manda fotos ayuda a cotizar mejor.' : ''),
        ]);
    }
}
