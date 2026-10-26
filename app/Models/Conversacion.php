<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\EstadoTurno;
use App\Enums\MotivoDerivacion;
use App\Jobs\DescargarMediaWhatsapp;
use App\Jobs\ResponderConversacion;
use App\Support\IndiceCiego;
use Carbon\CarbonInterface;
use Database\Factories\ConversacionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Una conversación de WhatsApp: una por número.
 *
 * Nace sola con el primer mensaje que entra por el webhook. Se engancha a la
 * ficha de `Cliente` cuando el número coincide con un celular cargado
 * (`Cliente::celularDesdeWaId()`), y si no, queda suelta hasta que haya nombre
 * y apellido. Los adjuntos viven en el disco `local` bajo
 * `whatsapp/{id}/` y se borran con ella.
 *
 * Audita lo que hace el equipo (tomarla, devolverla a la IA, archivarla). La
 * ingesta toca los contadores con `saveQuietly()`: cada mensaje que entra no
 * es una modificación que valga una fila en la auditoría.
 *
 * @property int $id
 * @property string $wa_id
 * @property string $wa_id_hash
 * @property string|null $nombre_perfil
 * @property int|null $cliente_id
 * @property bool $ia_activa
 * @property Carbon|null $derivada_at
 * @property MotivoDerivacion|null $motivo_derivacion
 * @property Carbon|null $ultimo_entrante_at
 * @property Carbon|null $ultimo_mensaje_at
 * @property int $no_leidos
 * @property array<string, mixed>|null $ficha
 * @property Carbon|null $archivada_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Cliente|null $cliente
 * @property-read Collection<int, MensajeWhatsapp> $mensajes
 * @property-read MensajeWhatsapp|null $ultimoMensaje
 */
#[Fillable(['wa_id', 'nombre_perfil', 'ia_activa', 'ficha'])]
class Conversacion extends Model
{
    /** @use HasFactory<ConversacionFactory> */
    use Auditable, HasFactory;

    protected $table = 'conversaciones';

    /**
     * Horas que Meta deja contestar con texto libre después del último
     * mensaje del cliente. Pasado eso sólo se puede mandar una plantilla.
     */
    public const HORAS_DE_VENTANA = 24;

    /**
     * Carpeta de los adjuntos dentro del disco `local`.
     *
     * @var string
     */
    public const CARPETA = 'whatsapp';

    /**
     * La carpeta de adjuntos se borra recién cuando la baja queda firme: si
     * la transacción se deshace, los archivos tienen que seguir ahí. Fuera de
     * una transacción, `afterCommit` corre enseguida.
     */
    protected static function booted(): void
    {
        /* El número va cifrado: se lo encuentra por su índice ciego. */
        static::saving(function (self $conversacion): void {
            $conversacion->wa_id_hash = IndiceCiego::deCelular($conversacion->wa_id);
        });

        static::deleting(function (self $conversacion): void {
            $carpeta = self::CARPETA.'/'.$conversacion->id;

            DB::afterCommit(fn () => Storage::disk('local')->deleteDirectory($carpeta));
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
            'wa_id' => 'encrypted',
            'nombre_perfil' => 'encrypted',
            'ia_activa' => 'boolean',
            'derivada_at' => 'datetime',
            'motivo_derivacion' => MotivoDerivacion::class,
            'ultimo_entrante_at' => 'datetime',
            'ultimo_mensaje_at' => 'datetime',
            'ficha' => 'encrypted:array',
            'archivada_at' => 'datetime',
        ];
    }

    public function tipoDeAuditoria(): string
    {
        return 'conversacion';
    }

    public function etiquetaDeAuditoria(): string
    {
        return 'WhatsApp de '.$this->nombreVisible();
    }

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Los mensajes, del más viejo al más nuevo.
     *
     * @return HasMany<MensajeWhatsapp, $this>
     */
    public function mensajes(): HasMany
    {
        return $this->hasMany(MensajeWhatsapp::class)->orderBy('id');
    }

    /**
     * @return HasOne<MensajeWhatsapp, $this>
     */
    public function ultimoMensaje(): HasOne
    {
        return $this->hasOne(MensajeWhatsapp::class)->latestOfMany();
    }

    /**
     * Cómo se la nombra en la bandeja: el cliente, el perfil de WhatsApp o el
     * número, en ese orden.
     */
    public function nombreVisible(): string
    {
        return $this->cliente?->nombreCompleto()
            ?? $this->nombre_perfil
            ?? '+'.$this->wa_id;
    }

    /**
     * Si todavía se le puede escribir texto libre (ventana de 24 h de Meta).
     */
    public function ventanaAbierta(): bool
    {
        return $this->ultimo_entrante_at !== null
            && $this->ultimo_entrante_at->greaterThan(now()->subHours(self::HORAS_DE_VENTANA));
    }

    /**
     * Cuándo se cierra la ventana, o null si ya está cerrada.
     */
    public function ventanaCierraAt(): ?CarbonInterface
    {
        return $this->ventanaAbierta()
            ? $this->ultimo_entrante_at?->copy()->addHours(self::HORAS_DE_VENTANA)
            : null;
    }

    /**
     * Si la IA puede contestar: encendida en la conversación y sin derivar.
     */
    public function atiendeLaIa(): bool
    {
        return $this->ia_activa && $this->derivada_at === null;
    }

    /**
     * Pasar la conversación a una persona: la IA se calla hasta que la
     * devuelvan.
     */
    public function derivar(MotivoDerivacion $motivo): void
    {
        $this->forceFill([
            'derivada_at' => now(),
            'motivo_derivacion' => $motivo,
        ])->save();
    }

    /**
     * Devolverle la conversación a la IA.
     */
    public function devolverALaIa(): void
    {
        $this->forceFill([
            'ia_activa' => true,
            'derivada_at' => null,
            'motivo_derivacion' => null,
        ])->save();
    }

    /**
     * Engancharla a la ficha del cliente con el mismo número, si existe.
     */
    public function vincularCliente(): void
    {
        if ($this->cliente_id !== null) {
            return;
        }

        $cliente = Cliente::delWaId($this->wa_id);

        if ($cliente !== null) {
            $this->cliente()->associate($cliente);
            $this->saveQuietly();
        }
    }

    /**
     * La conversación de un número: la que existe o una nueva.
     */
    public static function delNumero(string $waId, ?string $nombrePerfil = null): self
    {
        $conversacion = self::firstOrCreate(
            ['wa_id_hash' => IndiceCiego::deCelular($waId)],
            ['wa_id' => $waId, 'nombre_perfil' => $nombrePerfil],
        );

        if ($nombrePerfil !== null && $conversacion->nombre_perfil !== $nombrePerfil) {
            $conversacion->nombre_perfil = $nombrePerfil;
            $conversacion->saveQuietly();
        }

        return $conversacion;
    }

    /**
     * La conversación para escribirle a un cliente desde el panel.
     *
     * La que ya está enganchada a su ficha, o la de su número; si nunca
     * escribió, una nueva y vacía: sin mensajes no hay ventana de 24 h, así
     * que la bandeja ofrece una plantilla para arrancar.
     */
    public static function paraCliente(Cliente $cliente): self
    {
        $conversacion = self::where('cliente_id', $cliente->id)->latest('ultimo_mensaje_at')->first()
            ?? self::delNumero(Cliente::waIdDelCelular($cliente->celular));

        if ($conversacion->cliente_id === null) {
            $conversacion->cliente()->associate($cliente);
            $conversacion->save();
        }

        return $conversacion;
    }

    /**
     * Borrar la conversación y olvidar a la persona («borrar y olvidar»).
     *
     * El agente no tiene memoria aparte: el historial, la ficha y los
     * horarios ofrecidos viven en la base y se van con la fila (los dos
     * últimos en cascada). Con `$tambienElCliente` se borra además la ficha
     * de `Cliente` con todas sus otras conversaciones; sus turnos y
     * cotizaciones quedan, con `cliente_id` en null, porque son el registro
     * del taller y guardan su propia copia de los datos.
     *
     * No se borra si el cliente tiene un turno por venir: primero hay que
     * cancelarlo. La auditoría registra cada baja como siempre.
     *
     * @throws ValidationException
     */
    public function olvidar(bool $tambienElCliente): void
    {
        DB::transaction(function () use ($tambienElCliente): void {
            $conversacion = self::query()->lockForUpdate()->findOrFail($this->id);
            $cliente = $conversacion->cliente_id === null
                ? null
                : Cliente::query()->lockForUpdate()->find($conversacion->cliente_id);

            if ($cliente !== null) {
                self::exigirSinTurnosPorVenir($cliente);
            }

            $conversaciones = $tambienElCliente && $cliente !== null
                ? self::query()->where('cliente_id', $cliente->id)->lockForUpdate()->get()
                : new Collection([$conversacion]);

            self::descartarTrabajosPendientes($conversaciones->modelKeys());

            $conversaciones->each(fn (self $borrable) => $borrable->delete());

            if ($tambienElCliente) {
                $cliente?->delete();
            }
        });
    }

    /**
     * Las conversaciones que esperan a una persona: derivadas o con la IA
     * apagada, sin archivar. Es el contador de la pestaña «Atención humana».
     *
     * @return Builder<self>
     */
    public static function queAtiendeElEquipo(): Builder
    {
        return self::query()
            ->whereNull('archivada_at')
            ->where(fn (Builder $query) => $query->whereNotNull('derivada_at')->orWhere('ia_activa', false));
    }

    /**
     * Cortar el borrado si el cliente tiene un turno pendiente o confirmado
     * que todavía no pasó.
     *
     * @throws ValidationException
     */
    private static function exigirSinTurnosPorVenir(Cliente $cliente): void
    {
        $turno = Turno::query()
            ->where('cliente_id', $cliente->id)
            ->whereIn('estado', [EstadoTurno::Pendiente, EstadoTurno::Confirmado])
            ->where('inicia_at', '>', now())
            ->orderBy('inicia_at')
            ->lockForUpdate()
            ->first();

        if ($turno !== null) {
            throw ValidationException::withMessages([
                'alcance' => __('Tiene un turno el :fecha. Cancelalo antes de eliminar.', [
                    'fecha' => $turno->inicia_at->format('d/m/Y \a \l\a\s H:i'),
                ]),
            ]);
        }
    }

    /**
     * Sacar de la cola los trabajos que todavía no arrancaron y son de estas
     * conversaciones: el turno del agente y la descarga de adjuntos.
     *
     * Sólo con la cola `database`, la del proyecto: se leen los payloads sin
     * deserializarlos. Lo que no se reconozca (otra cola, un payload cifrado,
     * un trabajo que el worker ya tomó) no hace daño: `ResponderConversacion`
     * no hace nada si la conversación no existe y `DescargarMediaWhatsapp` se
     * descarta solo si el mensaje ya no está.
     *
     * @param  list<int>  $conversacionIds
     */
    private static function descartarTrabajosPendientes(array $conversacionIds): void
    {
        $conexion = (string) config('queue.default');

        if ($conversacionIds === [] || config("queue.connections.{$conexion}.driver") !== 'database') {
            return;
        }

        $cola = DB::connection(config("queue.connections.{$conexion}.connection"))
            ->table((string) config("queue.connections.{$conexion}.table", 'jobs'));
        $mensajeIds = MensajeWhatsapp::query()->whereIn('conversacion_id', $conversacionIds)->pluck('id')->all();

        $descartables = (clone $cola)
            ->whereNull('reserved_at')
            ->where(fn ($query) => $query
                ->where('payload', 'like', '%ResponderConversacion%')
                ->orWhere('payload', 'like', '%DescargarMediaWhatsapp%'))
            ->get(['id', 'payload'])
            ->filter(function (object $trabajo) use ($conversacionIds, $mensajeIds): bool {
                $payload = json_decode((string) $trabajo->payload, true);
                $comando = is_array($payload) ? ($payload['data']['command'] ?? null) : null;

                if (! is_string($comando)) {
                    return false;
                }

                return match ($payload['displayName'] ?? null) {
                    ResponderConversacion::class => preg_match('/s:14:"conversacionId";i:(\d+);/', $comando, $id) === 1
                        && in_array((int) $id[1], $conversacionIds, true),
                    DescargarMediaWhatsapp::class => preg_match('/MensajeWhatsapp";s:2:"id";i:(\d+);/', $comando, $id) === 1
                        && in_array((int) $id[1], $mensajeIds, true),
                    default => false,
                };
            });

        if ($descartables->isNotEmpty()) {
            (clone $cola)->whereIn('id', $descartables->pluck('id'))->delete();
        }
    }

    /**
     * Las conversaciones de la bandeja, de la más reciente a la más vieja.
     *
     * «Todas» es lo que atiende la IA: lo derivado o con la IA apagada pasa a
     * «Atención humana» (`humano`) y vuelve al devolvérsela a la IA.
     *
     * @param  array{busqueda?: string|null, filtro?: string|null}  $filtros
     * @return Builder<self>
     */
    public static function deLaBandeja(array $filtros): Builder
    {
        $busqueda = trim((string) ($filtros['busqueda'] ?? ''));
        $digitos = Cliente::normalizarCelular($busqueda);

        return self::query()
            ->with(['cliente', 'ultimoMensaje'])
            ->when(($filtros['filtro'] ?? null) === 'archivadas',
                fn (Builder $query) => $query->whereNotNull('archivada_at'),
                fn (Builder $query) => $query->whereNull('archivada_at'),
            )
            ->when(($filtros['filtro'] ?? null) === 'sin_leer', fn (Builder $query) => $query->where('no_leidos', '>', 0))
            ->when(($filtros['filtro'] ?? '') === '', fn (Builder $query) => $query
                ->whereNull('derivada_at')
                ->where('ia_activa', true))
            ->when(($filtros['filtro'] ?? null) === 'humano', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->whereNotNull('derivada_at')->orWhere('ia_activa', false),
            ))
            ->when($busqueda !== '', fn (Builder $query) => $query->whereIn('id', self::idsQueCoinciden($busqueda, $digitos)))
            ->orderByDesc('ultimo_mensaje_at')
            ->orderByDesc('id');
    }

    /**
     * Los ids de las conversaciones que coinciden con lo que se buscó.
     *
     * Un número completo se busca por el índice ciego (como lo manda Meta o
     * como se anota en la ficha). Si no aparece así, se descifra y se compara
     * en PHP el perfil, el nombre del cliente y un pedazo del número.
     *
     * @return list<int>
     */
    private static function idsQueCoinciden(string $busqueda, string $digitos): array
    {
        if (strlen($digitos) >= 8) {
            $exactos = self::query()
                ->whereIn('wa_id_hash', array_map(IndiceCiego::de(...), array_unique([$digitos, Cliente::waIdDelCelular($digitos)])))
                ->pluck('id')
                ->all();

            if ($exactos !== []) {
                return $exactos;
            }
        }

        $texto = mb_strtolower($busqueda);
        $numero = ltrim($digitos, '0');
        $ids = [];

        foreach (self::query()->with('cliente:id,nombre,apellido')->lazyById(200) as $conversacion) {
            $coincide = str_contains(mb_strtolower((string) $conversacion->nombre_perfil), $texto)
                || ($conversacion->cliente !== null && str_contains(mb_strtolower($conversacion->cliente->nombreCompleto()), $texto))
                || (strlen($digitos) >= 3 && str_contains($conversacion->wa_id, $numero));

            if ($coincide) {
                $ids[] = $conversacion->id;
            }
        }

        return $ids;
    }

    /**
     * Los contadores de los avisos del panel (menú lateral y título de la
     * pestaña): mensajes sin leer y conversaciones que esperan a una persona,
     * sin contar las archivadas.
     *
     * @return array{noLeidos: int, atencionHumana: int}
     */
    public static function contadoresDeAvisos(): array
    {
        return [
            'noLeidos' => (int) self::query()->whereNull('archivada_at')->sum('no_leidos'),
            'atencionHumana' => self::queAtiendeElEquipo()->count(),
        ];
    }
}
