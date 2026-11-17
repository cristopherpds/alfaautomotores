<?php

namespace App\Models;

use App\Enums\EstadoPlantilla;
use Database\Factories\PlantillaWhatsappFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Una plantilla de la cuenta de WhatsApp.
 *
 * Se crean y se aprueban en Meta; acá es una copia que se sincroniza
 * (`sincronizar()` y el webhook `message_template_status_update`). Son lo
 * único que se puede mandar con la ventana de 24 h cerrada.
 *
 * @property int $id
 * @property string|null $wa_template_id
 * @property string $nombre
 * @property string $idioma
 * @property string|null $categoria
 * @property string|null $cuerpo
 * @property int $variables
 * @property EstadoPlantilla $estado
 * @property string|null $motivo_rechazo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['wa_template_id', 'nombre', 'idioma', 'categoria', 'cuerpo', 'variables', 'estado', 'motivo_rechazo'])]
class PlantillaWhatsapp extends Model
{
    /** @use HasFactory<PlantillaWhatsappFactory> */
    use HasFactory;

    protected $table = 'plantillas_whatsapp';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'variables' => 'integer',
            'estado' => EstadoPlantilla::class,
        ];
    }

    /**
     * @return Builder<self>
     */
    public static function aprobadas(): Builder
    {
        return self::query()->where('estado', EstadoPlantilla::Aprobada)->orderBy('nombre');
    }

    /**
     * El cuerpo con las variables `{{1}}…{{n}}` reemplazadas.
     *
     * @param  list<string>  $valores
     */
    public function renderizar(array $valores): string
    {
        return (string) preg_replace_callback(
            '/\{\{(\d+)\}\}/',
            fn (array $coincidencia): string => $valores[(int) $coincidencia[1] - 1] ?? $coincidencia[0],
            (string) $this->cuerpo,
        );
    }

    /**
     * Cuántas variables `{{n}}` trae un cuerpo.
     */
    public static function contarVariables(?string $cuerpo): int
    {
        preg_match_all('/\{\{(\d+)\}\}/', (string) $cuerpo, $coincidencias);

        return count(array_unique($coincidencias[1]));
    }

    /**
     * Guardar las plantillas que devuelve Meta (`message_templates`).
     *
     * @param  list<array<string, mixed>>  $deMeta
     */
    public static function sincronizar(array $deMeta): int
    {
        foreach ($deMeta as $plantilla) {
            $cuerpo = collect($plantilla['components'] ?? [])
                ->firstWhere('type', 'BODY')['text'] ?? null;

            self::updateOrCreate(
                ['nombre' => $plantilla['name'], 'idioma' => $plantilla['language']],
                [
                    'wa_template_id' => $plantilla['id'] ?? null,
                    'categoria' => $plantilla['category'] ?? null,
                    'cuerpo' => $cuerpo,
                    'variables' => self::contarVariables($cuerpo),
                    'estado' => EstadoPlantilla::desdeMeta((string) ($plantilla['status'] ?? '')),
                    'motivo_rechazo' => $plantilla['rejected_reason'] ?? null,
                ],
            );
        }

        return count($deMeta);
    }
}
