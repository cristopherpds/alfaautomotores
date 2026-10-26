<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\Rubro;
use App\Support\IndiceCiego;
use Database\Factories\ClienteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Un cliente del taller o el lavadero.
 *
 * Nace de una reserva (`desdeReserva()`) o del alta manual del panel, para
 * los leads que llaman sin reservar. La identidad es el celular sin espacios
 * ni guiones: dos reservas con «099 123 456» y «099123456» son la misma
 * persona. Cada turno guarda igual su propia copia de los datos, que es el
 * registro de esa reserva.
 *
 * Los datos personales van cifrados (casts `encrypted`): no se buscan con
 * `where`, sino por su índice ciego (`celular_hash`, `email_hash`, ver
 * `App\Support\IndiceCiego`), que el hook `saving` mantiene al día.
 *
 * @property int $id
 * @property string $nombre
 * @property string $apellido
 * @property string|null $email
 * @property string $celular
 * @property string $celular_normalizado
 * @property string $celular_hash
 * @property string|null $email_hash
 * @property bool $acepta_novedades
 * @property Carbon|null $acepta_novedades_at
 * @property string|null $notas
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Turno> $turnos
 */
#[Fillable(['nombre', 'apellido', 'email', 'celular', 'acepta_novedades', 'notas'])]
class Cliente extends Model
{
    /** @use HasFactory<ClienteFactory> */
    use Auditable, HasFactory;

    /**
     * Dos invariantes que valen se guarde desde donde se guarde.
     *
     * La clave de identidad sale siempre del celular, y la fecha del
     * consentimiento sigue al consentimiento: se pone al darlo y se borra al
     * retirarlo.
     */
    protected static function booted(): void
    {
        static::saving(function (self $cliente): void {
            $cliente->celular_normalizado = self::normalizarCelular($cliente->celular);
            $cliente->email = $cliente->email === null || trim($cliente->email) === ''
                ? null
                : mb_strtolower(trim($cliente->email));
            $cliente->celular_hash = IndiceCiego::de($cliente->celular_normalizado);
            $cliente->email_hash = IndiceCiego::deEmail($cliente->email);

            if ($cliente->isDirty('acepta_novedades')) {
                $cliente->acepta_novedades_at = $cliente->acepta_novedades ? now() : null;
            }
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
            'nombre' => 'encrypted',
            'apellido' => 'encrypted',
            'email' => 'encrypted',
            'celular' => 'encrypted',
            'celular_normalizado' => 'encrypted',
            'notas' => 'encrypted',
            'acepta_novedades' => 'boolean',
            'acepta_novedades_at' => 'datetime',
        ];
    }

    /**
     * La clave del registro en la auditoría.
     */
    public function tipoDeAuditoria(): string
    {
        return 'cliente';
    }

    /**
     * Cómo figura el cliente en la auditoría.
     */
    public function etiquetaDeAuditoria(): string
    {
        return $this->nombreCompleto();
    }

    public function nombreCompleto(): string
    {
        return $this->nombre.' '.$this->apellido;
    }

    /**
     * Los turnos del cliente, del más nuevo al más viejo.
     *
     * @return HasMany<Turno, $this>
     */
    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class)->orderByDesc('inicia_at')->orderByDesc('id');
    }

    /**
     * Los vehículos que trajo, sacados de sus turnos: no hay tabla aparte.
     *
     * Uno por matrícula, o por marca, modelo y año cuando no la dejó.
     *
     * @return Collection<int, array{marca: string|null, modelo: string|null, anio: int|null, matricula: string|null}>
     */
    public function vehiculos(): Collection
    {
        return $this->turnos
            ->filter(fn (Turno $turno): bool => $turno->vehiculo_marca !== null
                || $turno->vehiculo_modelo !== null
                || $turno->matricula !== null)
            ->map(fn (Turno $turno): array => [
                'marca' => $turno->vehiculo_marca,
                'modelo' => $turno->vehiculo_modelo,
                'anio' => $turno->vehiculo_anio,
                'matricula' => $turno->matricula === null ? null : mb_strtoupper($turno->matricula),
            ])
            ->unique(fn (array $vehiculo): string => $vehiculo['matricula']
                ?? mb_strtolower(($vehiculo['marca'] ?? '').'|'.($vehiculo['modelo'] ?? '').'|'.($vehiculo['anio'] ?? '')))
            ->values();
    }

    /**
     * Los clientes que cumplen los filtros del panel, del más activo al
     * menos. La usan el listado y el CSV, así lo que se baja es exactamente
     * lo que se ve.
     *
     * Con los datos cifrados la búsqueda no puede ser un `like`: ver
     * `idsQueCoinciden()`.
     *
     * @param  array{busqueda?: string|null, rubro?: string|null, novedades?: bool|null}  $filtros
     * @return Builder<self>
     */
    public static function filtrados(array $filtros): Builder
    {
        $busqueda = trim((string) ($filtros['busqueda'] ?? ''));

        return self::query()
            ->when($busqueda !== '', fn (Builder $query) => $query->whereIn('id', self::idsQueCoinciden($busqueda)))
            ->when(
                Rubro::tryFrom((string) ($filtros['rubro'] ?? '')),
                fn (Builder $query, Rubro $rubro) => $query->whereHas('turnos.servicio', fn (Builder $servicios) => $servicios->where('rubro', $rubro)),
            )
            ->when($filtros['novedades'] ?? false, fn (Builder $query) => $query->where('acepta_novedades', true))
            // Por actividad: la última reserva, o el alta para los leads que
            // nunca reservaron.
            ->orderByRaw('coalesce((select max(turnos.created_at) from turnos where turnos.cliente_id = clientes.id), clientes.created_at) desc')
            ->orderByDesc('id');
    }

    /**
     * Los ids de los clientes que coinciden con lo que se buscó.
     *
     * Un celular, un email o una matrícula exactos se buscan por el índice
     * ciego, sin descifrar nada. Si no aparece así (un nombre, un pedazo de
     * número), se descifra y se compara en PHP: son pocos clientes, y es la
     * única forma de buscar parcial sobre datos cifrados.
     *
     * @return list<int>
     */
    public static function idsQueCoinciden(string $busqueda): array
    {
        $digitos = self::normalizarCelular($busqueda);
        $matricula = IndiceCiego::deMatricula($busqueda);

        $exactos = self::query()
            ->where(function (Builder $query) use ($busqueda, $digitos, $matricula): void {
                $query->where('email_hash', IndiceCiego::deEmail($busqueda))
                    ->when($matricula !== null, fn (Builder $query) => $query
                        ->orWhereHas('turnos', fn (Builder $turnos) => $turnos->where('matricula_hash', $matricula)));

                if (strlen($digitos) >= 6) {
                    $query->orWhereIn('celular_hash', array_map(
                        IndiceCiego::de(...),
                        array_unique([$digitos, ...self::celularesPosiblesDeWaId($digitos)]),
                    ));
                }
            })
            ->pluck('id')
            ->all();

        if ($exactos !== []) {
            return $exactos;
        }

        $texto = mb_strtolower($busqueda);
        $placa = IndiceCiego::normalizarMatricula($busqueda);
        $ids = [];

        foreach (self::query()->with('turnos:id,cliente_id,matricula')->lazyById(200) as $cliente) {
            $coincide = str_contains(mb_strtolower($cliente->nombreCompleto()), $texto)
                || str_contains((string) $cliente->email, $texto)
                // Sólo si hay dígitos suficientes: «a» no tiene que traer a
                // todos los celulares.
                || (strlen($digitos) >= 3 && str_contains($cliente->celular_normalizado, $digitos))
                || ($placa !== '' && $cliente->turnos->contains(
                    fn (Turno $turno): bool => str_contains(IndiceCiego::normalizarMatricula($turno->matricula), $placa),
                ));

            if ($coincide) {
                $ids[] = $cliente->id;
            }
        }

        return $ids;
    }

    /**
     * Un celular sin nada que no sea dígito.
     */
    public static function normalizarCelular(string $celular): string
    {
        return (string) preg_replace('/\D+/', '', $celular);
    }

    /**
     * Cómo puede haber quedado guardado el celular de un número de WhatsApp.
     *
     * Meta manda el número internacional sin «+» (`59899123456`) y en las
     * fichas se anota a la uruguaya (`099 123 456` → `099123456`): sin esta
     * traducción no coincidirían nunca. Un número de otro país (Brasil, en
     * Rivera, es lo común) se compara tal cual.
     *
     * @return list<string>
     */
    public static function celularesPosiblesDeWaId(string $waId): array
    {
        $digitos = self::normalizarCelular($waId);
        $posibles = [$digitos];

        if (str_starts_with($digitos, '598') && strlen($digitos) > 3) {
            $posibles[] = '0'.substr($digitos, 3);
        }

        return $posibles;
    }

    /**
     * El cliente de un número de WhatsApp, buscado por el índice ciego de
     * cada forma en que pudo quedar anotado su celular.
     */
    public static function delWaId(string $waId): ?self
    {
        return self::query()
            ->whereIn('celular_hash', array_map(IndiceCiego::de(...), self::celularesPosiblesDeWaId($waId)))
            ->first();
    }

    /**
     * El número de WhatsApp de un celular de ficha: el camino inverso de
     * `celularesPosiblesDeWaId()`.
     *
     * Un celular uruguayo se anota local (`099 123 456`, o `99123456` sin el
     * cero) y Meta lo quiere con el 598 adelante. Uno que ya trae el 598, o
     * uno de otro país guardado en internacional, va tal cual.
     */
    public static function waIdDelCelular(string $celular): string
    {
        $digitos = self::normalizarCelular($celular);

        if (str_starts_with($digitos, '598')) {
            return $digitos;
        }

        if (str_starts_with($digitos, '0')) {
            return '598'.substr($digitos, 1);
        }

        return strlen($digitos) <= 8 ? '598'.$digitos : $digitos;
    }

    /**
     * El cliente de una reserva: el que ya existe o uno nuevo.
     *
     * Busca por celular y, si no aparece, por email. Si lo encuentra, se
     * queda con los datos de contacto más nuevos. El consentimiento sólo se
     * suma: una reserva sin la casilla tildada no le quita a nadie el que
     * dio antes; eso se hace desde el panel.
     *
     * @param  array{nombre: string, apellido: string, email: string|null, celular: string}  $datos
     */
    public static function desdeReserva(array $datos, bool $aceptaNovedades = false): self
    {
        $emailHash = IndiceCiego::deEmail($datos['email']);

        $cliente = self::where('celular_hash', IndiceCiego::deCelular($datos['celular']))->first()
            ?? ($emailHash === null ? null : self::where('email_hash', $emailHash)->first())
            ?? new self;

        $cliente->fill([
            'nombre' => $datos['nombre'],
            'apellido' => $datos['apellido'],
            'email' => $datos['email'],
            'celular' => $datos['celular'],
        ]);

        if ($aceptaNovedades) {
            $cliente->acepta_novedades = true;
        }

        $cliente->save();

        return $cliente;
    }
}
