<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\Rubro;
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
 * @property int $id
 * @property string $nombre
 * @property string $apellido
 * @property string|null $email
 * @property string $celular
 * @property string $celular_normalizado
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
     * @param  array{busqueda?: string|null, rubro?: string|null, novedades?: bool|null}  $filtros
     * @return Builder<self>
     */
    public static function filtrados(array $filtros): Builder
    {
        $busqueda = trim((string) ($filtros['busqueda'] ?? ''));
        $digitos = self::normalizarCelular($busqueda);

        return self::query()
            ->when($busqueda !== '', function (Builder $query) use ($busqueda, $digitos): void {
                $query->where(function (Builder $query) use ($busqueda, $digitos): void {
                    $query->whereRaw("lower(nombre || ' ' || apellido) like ?", ['%'.mb_strtolower($busqueda).'%'])
                        ->orWhere('email', 'like', '%'.mb_strtolower($busqueda).'%')
                        ->orWhereHas('turnos', fn (Builder $turnos) => $turnos->whereRaw('upper(matricula) like ?', ['%'.mb_strtoupper($busqueda).'%']));

                    // Sólo si hay dígitos suficientes: «a» no tiene que
                    // traer a todos los celulares.
                    if (strlen($digitos) >= 3) {
                        $query->orWhere('celular_normalizado', 'like', '%'.$digitos.'%');
                    }
                });
            })
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
     * Un celular sin nada que no sea dígito.
     */
    public static function normalizarCelular(string $celular): string
    {
        return (string) preg_replace('/\D+/', '', $celular);
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
        $email = $datos['email'] === null ? null : mb_strtolower(trim($datos['email']));

        $cliente = self::where('celular_normalizado', self::normalizarCelular($datos['celular']))->first()
            ?? ($email === null ? null : self::where('email', $email)->first())
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
