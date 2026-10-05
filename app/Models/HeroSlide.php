<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\PosicionTexto;
use App\Enums\TipoFondo;
use Database\Factories\HeroSlideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Un slide del hero de la portada: un fondo (foto o video), sus textos y hasta
 * dos botones.
 *
 * Qué slides ve el público lo decide una sola consulta, `vigentes()`: los
 * activos cuya vigencia (`desde`/`hasta`, las dos opcionales e inclusivas)
 * incluye el día de hoy, en el orden del panel. `estado()` es la misma regla
 * vista desde el panel; si se toca una, se toca la otra.
 *
 * @property int $id
 * @property TipoFondo $tipo_fondo
 * @property string $fondo
 * @property string|null $eyebrow
 * @property string $titulo
 * @property string|null $bajada
 * @property PosicionTexto $posicion
 * @property string|null $boton1_texto
 * @property string|null $boton1_url
 * @property string|null $boton2_texto
 * @property string|null $boton2_url
 * @property bool $activo
 * @property Carbon|null $desde
 * @property Carbon|null $hasta
 * @property int $orden
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tipo_fondo', 'fondo', 'eyebrow', 'titulo', 'bajada', 'posicion',
    'boton1_texto', 'boton1_url', 'boton2_texto', 'boton2_url',
    'activo', 'desde', 'hasta', 'orden',
])]
class HeroSlide extends Model
{
    /** @use HasFactory<HeroSlideFactory> */
    use Auditable, HasFactory;

    /**
     * Carpeta del disco `public` donde viven los fondos.
     */
    public const CARPETA = 'hero';

    /**
     * La clave del registro en la auditoría.
     */
    public function tipoDeAuditoria(): string
    {
        return 'hero';
    }

    /**
     * Cómo figura el slide en la auditoría.
     */
    public function etiquetaDeAuditoria(): string
    {
        return str($this->titulo)->squish()->limit(60)->value();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_fondo' => TipoFondo::class,
            'posicion' => PosicionTexto::class,
            'activo' => 'boolean',
            'desde' => 'date',
            'hasta' => 'date',
            'orden' => 'integer',
        ];
    }

    /**
     * Los slides que ve el público hoy, ya ordenados.
     *
     * @return Collection<int, static>
     */
    public static function vigentes(?Carbon $hoy = null): Collection
    {
        $dia = ($hoy ?? now())->toDateString();

        return static::ordenados()
            ->where('activo', true)
            ->where(fn (Builder $query) => $query->whereNull('desde')->orWhereDate('desde', '<=', $dia))
            ->where(fn (Builder $query) => $query->whereNull('hasta')->orWhereDate('hasta', '>=', $dia))
            ->get();
    }

    /**
     * El orden del panel: la única definición, la usan la portada y la tabla.
     *
     * @return Builder<static>
     */
    public static function ordenados(): Builder
    {
        return static::query()->orderBy('orden')->orderBy('id');
    }

    /**
     * Cómo está el slide respecto del público: el estado que muestra el panel.
     *
     * @return 'activo'|'programado'|'vencido'|'inactivo'
     */
    public function estado(?Carbon $hoy = null): string
    {
        $dia = ($hoy ?? now())->copy()->startOfDay();

        return match (true) {
            ! $this->activo => 'inactivo',
            $this->desde !== null && $this->desde->gt($dia) => 'programado',
            $this->hasta !== null && $this->hasta->lt($dia) => 'vencido',
            default => 'activo',
        };
    }

    /**
     * URL pública del fondo.
     */
    public function url(): string
    {
        return Storage::disk('public')->url($this->fondo);
    }

    /**
     * Lo que dibuja el hero: el mismo payload para la portada y para la vista
     * previa del panel.
     *
     * @return array{id: int|null, tipoFondo: string, fondo: string, eyebrow: string|null, titulo: string, bajada: string|null, posicion: string, botones: array<int, array{texto: string, url: string}>}
     */
    public function datos(): array
    {
        return [
            'id' => $this->id,
            'tipoFondo' => $this->tipo_fondo->value,
            'fondo' => $this->url(),
            'eyebrow' => $this->eyebrow,
            'titulo' => $this->titulo,
            'bajada' => $this->bajada,
            'posicion' => $this->posicion->value,
            'botones' => array_values(array_filter([
                $this->boton(1),
                $this->boton(2),
            ])),
        ];
    }

    /**
     * El slide que se muestra cuando no hay ninguno vigente: el hero que tuvo
     * la portada antes de que fuera administrable. Así una base vacía nunca
     * deja la primera vista en negro.
     *
     * El número de WhatsApp llega de afuera porque depende del horario de los
     * contactos (`ProvidesSiteInfo`); sin él, el slide va sólo con el catálogo.
     *
     * @return array{id: int|null, tipoFondo: string, fondo: string, eyebrow: string|null, titulo: string, bajada: string|null, posicion: string, botones: array<int, array{texto: string, url: string}>}
     */
    public static function porDefecto(?string $whatsapp = null): array
    {
        return [
            'id' => null,
            'tipoFondo' => TipoFondo::Video->value,
            'fondo' => '/assets/hero-ruta.mp4',
            'eyebrow' => config('alfa.ciudad').' · '.config('alfa.pais'),
            'titulo' => "Tu elegís el destino,\nnosotros ponemos\nel vehículo.",
            'bajada' => 'Vehículos seleccionados, revisados y listos para entregar. Financiación disponible y recibimos tu usado como parte de pago.',
            'posicion' => PosicionTexto::AbajoIzquierda->value,
            'botones' => array_values(array_filter([
                ['texto' => 'Ver catálogo', 'url' => '/catalogo'],
                $whatsapp === null ? null : [
                    'texto' => 'Cotizá tu auto',
                    'url' => 'https://wa.me/'.$whatsapp.'?text='.rawurlencode('Hola, quiero cotizar mi auto con Alfa Automotores.'),
                ],
            ])),
        ];
    }

    /**
     * Borrar el archivo del disco junto con la fila.
     */
    public function borrarConArchivo(): void
    {
        Storage::disk('public')->delete($this->fondo);

        $this->delete();
    }

    /**
     * Un botón, si tiene texto y destino.
     *
     * @return array{texto: string, url: string}|null
     */
    private function boton(int $numero): ?array
    {
        $texto = $this->getAttribute("boton{$numero}_texto");
        $url = $this->getAttribute("boton{$numero}_url");

        return filled($texto) && filled($url) ? ['texto' => $texto, 'url' => $url] : null;
    }
}
