<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\EstadoCotizacion;
use App\Enums\OrigenCotizacion;
use App\Notifications\NuevaCotizacion;
use Database\Factories\CotizacionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Una solicitud de «Cotizá tu auto»: del formulario del sitio o de una
 * conversación de WhatsApp con el agente (`origen`, y `conversacion_id` para
 * volver al hilo).
 *
 * Las fotos son opcionales y van en el disco `local` (privado), bajo
 * `cotizaciones/{id}/`: son del auto de un particular y sólo se ven desde el
 * panel. La solicitud guarda su propia copia del contacto y además suma a la
 * persona como lead en Clientes.
 *
 * @property int $id
 * @property int|null $cliente_id
 * @property int|null $conversacion_id
 * @property string $marca
 * @property string $modelo
 * @property int $anio
 * @property int $kilometros
 * @property string|null $detalles
 * @property string|null $foto_derecha
 * @property string|null $foto_izquierda
 * @property string|null $foto_interior
 * @property string|null $foto_tablero
 * @property string $nombre
 * @property string $apellido
 * @property string $celular
 * @property EstadoCotizacion $estado
 * @property OrigenCotizacion $origen
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Cliente|null $cliente
 * @property-read Conversacion|null $conversacion
 */
#[Fillable([
    'marca', 'modelo', 'anio', 'kilometros', 'detalles',
    'nombre', 'apellido', 'celular', 'estado',
])]
class Cotizacion extends Model
{
    /** @use HasFactory<CotizacionFactory> */
    use Auditable, HasFactory;

    protected $table = 'cotizaciones';

    /**
     * Las cuatro fotos que se piden, en el orden del formulario. La clave es
     * el sufijo de la columna (`foto_{lado}`) y el segmento de la URL.
     *
     * @var array<string, string>
     */
    public const FOTOS = [
        'derecha' => 'Lado derecho',
        'izquierda' => 'Lado izquierdo',
        'interior' => 'Interior',
        'tablero' => 'Tablero y kilómetros',
    ];

    /**
     * Carpeta de las fotos dentro del disco `local`.
     *
     * @var string
     */
    public const CARPETA = 'cotizaciones';

    /**
     * Al borrar la solicitud se van sus fotos.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $cotizacion): void {
            Storage::disk('local')->deleteDirectory(self::CARPETA.'/'.$cotizacion->id);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'kilometros' => 'integer',
            'detalles' => 'encrypted',
            'nombre' => 'encrypted',
            'apellido' => 'encrypted',
            'celular' => 'encrypted',
            'estado' => EstadoCotizacion::class,
            'origen' => OrigenCotizacion::class,
        ];
    }

    /**
     * La clave del registro en la auditoría.
     */
    public function tipoDeAuditoria(): string
    {
        return 'cotizacion';
    }

    /**
     * Cómo figura la solicitud en la auditoría: el auto y quién lo ofrece.
     */
    public function etiquetaDeAuditoria(): string
    {
        return $this->vehiculo().' de '.$this->nombreCompleto();
    }

    /**
     * La ficha del cliente que se sumó como lead.
     *
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * La conversación de WhatsApp de la que salió, si vino por ahí.
     *
     * @return BelongsTo<Conversacion, $this>
     */
    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(Conversacion::class);
    }

    public function nombreCompleto(): string
    {
        return $this->nombre.' '.$this->apellido;
    }

    /**
     * El auto en una línea: «Toyota Corolla 2018».
     */
    public function vehiculo(): string
    {
        return $this->marca.' '.$this->modelo.' '.$this->anio;
    }

    /**
     * Guardar una solicitud con sus fotos y su lead.
     *
     * Todo o nada: si falla una foto no queda la fila a medias. Al final se
     * avisa al equipo (campana y email), sea de la web o de WhatsApp.
     *
     * @param  array{marca: string, modelo: string, anio: int, kilometros: int, detalles: string|null, nombre: string, apellido: string, celular: string}  $datos
     *                                                                                                                                                             Las fotos llegan como archivo subido (el formulario del sitio) o como
     *                                                                                                                                                             ruta ya guardada en el disco `local` (las que mandaron por WhatsApp):
     *                                                                                                                                                             en los dos casos quedan copiadas en la carpeta de la cotización.
     * @param  array<string, UploadedFile|string|null>  $fotos  Por lado, con las claves de `FOTOS`.
     * @param  Conversacion|null  $conversacion  La de WhatsApp si la registró el agente; sin ella, vino del sitio.
     */
    public static function recibir(array $datos, array $fotos, ?Conversacion $conversacion = null): self
    {
        $cotizacion = DB::transaction(function () use ($datos, $fotos, $conversacion): self {
            $cliente = Cliente::desdeReserva([
                'nombre' => $datos['nombre'],
                'apellido' => $datos['apellido'],
                'email' => null,
                'celular' => $datos['celular'],
            ]);

            $cotizacion = new self([...$datos, 'estado' => EstadoCotizacion::Nueva]);
            $cotizacion->origen = $conversacion === null ? OrigenCotizacion::Web : OrigenCotizacion::Whatsapp;
            $cotizacion->cliente()->associate($cliente);
            $cotizacion->conversacion()->associate($conversacion);
            $cotizacion->save();

            foreach (array_keys(self::FOTOS) as $lado) {
                $archivo = $fotos[$lado] ?? null;

                if ($archivo instanceof UploadedFile) {
                    $cotizacion->{'foto_'.$lado} = $archivo->store(self::CARPETA.'/'.$cotizacion->id, 'local');
                } elseif (is_string($archivo) && Storage::disk('local')->exists($archivo)) {
                    $destino = self::CARPETA.'/'.$cotizacion->id.'/'.$lado.'.'.(pathinfo($archivo, PATHINFO_EXTENSION) ?: 'jpg');
                    Storage::disk('local')->copy($archivo, $destino);
                    $cotizacion->{'foto_'.$lado} = $destino;
                }
            }

            $cotizacion->saveQuietly();

            return $cotizacion;
        });

        Notification::send(User::equipoDeAvisos(), new NuevaCotizacion($cotizacion));

        return $cotizacion;
    }

    /**
     * La ruta en disco de la foto de un lado, o null si no la mandó.
     */
    public function rutaDeFoto(string $lado): ?string
    {
        if (! array_key_exists($lado, self::FOTOS)) {
            return null;
        }

        return $this->{'foto_'.$lado};
    }

    /**
     * Los lados que trajeron foto.
     *
     * @return list<string>
     */
    public function ladosConFoto(): array
    {
        return array_values(array_filter(
            array_keys(self::FOTOS),
            fn (string $lado): bool => $this->rutaDeFoto($lado) !== null,
        ));
    }
}
