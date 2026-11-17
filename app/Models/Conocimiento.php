<?php

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\ConocimientoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Una entrada de lo que el agente de WhatsApp sabe del negocio.
 *
 * Dos formas, como en Vocero: `pregunta` + `respuesta` para lo que se
 * pregunta siempre, o `contenido` libre para un bloque (garantía,
 * financiación, cómo llegar). Va al prompt como DATOS, no como órdenes.
 *
 * @property int $id
 * @property string $tipo
 * @property string|null $pregunta
 * @property string|null $respuesta
 * @property string|null $contenido
 * @property int $orden
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tipo', 'pregunta', 'respuesta', 'contenido', 'orden'])]
class Conocimiento extends Model
{
    /** @use HasFactory<ConocimientoFactory> */
    use Auditable, HasFactory;

    public const PREGUNTA = 'pregunta';

    public const BLOQUE = 'bloque';

    public function tipoDeAuditoria(): string
    {
        return 'conocimiento';
    }

    public function etiquetaDeAuditoria(): string
    {
        return Str::limit((string) ($this->pregunta ?? $this->contenido), 60);
    }

    /**
     * @return Builder<self>
     */
    public static function ordenados(): Builder
    {
        return self::query()->orderBy('orden')->orderBy('id');
    }

    /**
     * Todo el conocimiento en texto, para el prompt del agente.
     */
    public static function comoTexto(): string
    {
        return self::ordenados()
            ->get()
            ->map(fn (self $entrada): string => $entrada->tipo === self::PREGUNTA
                ? 'P: '.$entrada->pregunta."\nR: ".$entrada->respuesta
                : (string) $entrada->contenido)
            ->implode("\n\n");
    }
}
