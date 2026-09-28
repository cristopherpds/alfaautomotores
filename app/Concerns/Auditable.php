<?php

namespace App\Concerns;

use App\Enums\AccionAuditoria;
use App\Models\Auditoria;

/**
 * Deja en la auditoría cada alta, modificación y baja del modelo.
 *
 * El modelo que lo usa define `tipoDeAuditoria()` (la clave que filtra la
 * página: `vehiculo`, `turno`…) y `etiquetaDeAuditoria()` (cómo se llamaba el
 * registro en ese momento). El resto sale de los eventos de Eloquent.
 *
 * Ojo: una escritura masiva (`query()->update()`, `->delete()` sobre un
 * builder) no dispara eventos y no queda registrada; se anota a mano con
 * `Auditoria::registrar()`, como el reordenamiento de fotos.
 */
trait Auditable
{
    /**
     * Columnas que nunca se guardan: los timestamps no dicen nada y el resto
     * son secretos o ruido (`remember_token` cambia en cada login con
     * «recordarme»).
     *
     * @var list<string>
     */
    private static array $camposSinAuditar = [
        'id', 'created_at', 'updated_at', 'remember_token',
        'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
    ];

    /**
     * Engancha el registro a los eventos del modelo.
     */
    protected static function bootAuditable(): void
    {
        static::created(function (self $registro): void {
            Auditoria::registrar($registro, AccionAuditoria::Creado, $registro->cambiosDeAlta());
        });

        static::updated(function (self $registro): void {
            $cambios = $registro->cambiosDeModificacion();

            if ($cambios !== []) {
                Auditoria::registrar($registro, AccionAuditoria::Modificado, $cambios);
            }
        });

        static::deleted(function (self $registro): void {
            Auditoria::registrar($registro, AccionAuditoria::Eliminado, $registro->cambiosDeBaja());
        });
    }

    /**
     * La clave del tipo de registro, la misma que usa el filtro de la página.
     */
    abstract public function tipoDeAuditoria(): string;

    /**
     * Cómo se llama el registro, para leer la entrada aunque después se borre.
     */
    abstract public function etiquetaDeAuditoria(): string;

    /**
     * En el alta, con qué valores nació.
     *
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function cambiosDeAlta(): array
    {
        $cambios = [];

        foreach ($this->atributosAuditables() as $campo => $valor) {
            if ($campo !== 'password') {
                $cambios[$campo] = [null, $valor];
            }
        }

        return $cambios;
    }

    /**
     * En una modificación, sólo lo que cambió, con el valor anterior.
     *
     * Corre en el evento `updated`, antes de que Eloquent sincronice el
     * original: `getRawOriginal()` todavía tiene lo que había.
     *
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function cambiosDeModificacion(): array
    {
        $cambios = [];

        foreach (array_keys($this->getChanges()) as $campo) {
            if (in_array($campo, self::$camposSinAuditar, true)) {
                continue;
            }

            $cambios[$campo] = $campo === 'password'
                ? [null, 'cambiada']
                : [$this->valorAuditable($campo, $this->getRawOriginal($campo)), $this->valorAuditable($campo, $this->getAttributes()[$campo] ?? null)];
        }

        return $cambios;
    }

    /**
     * En la baja, los últimos valores: es lo único que queda del registro.
     *
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function cambiosDeBaja(): array
    {
        $cambios = [];

        foreach ($this->atributosAuditables() as $campo => $valor) {
            if ($campo !== 'password') {
                $cambios[$campo] = [$valor, null];
            }
        }

        return $cambios;
    }

    /**
     * Los atributos crudos que se pueden guardar, con los JSON ya decodificados.
     *
     * @return array<string, mixed>
     */
    private function atributosAuditables(): array
    {
        $atributos = [];

        foreach ($this->getAttributes() as $campo => $valor) {
            if (! in_array($campo, self::$camposSinAuditar, true)) {
                $atributos[$campo] = $this->valorAuditable($campo, $valor);
            }
        }

        return $atributos;
    }

    /**
     * Un valor crudo, tal como se guarda: las columnas JSON (ficha técnica,
     * colores) se decodifican para que la entrada no guarde texto escapado.
     */
    private function valorAuditable(string $campo, mixed $valor): mixed
    {
        if (is_string($valor) && $this->hasCast($campo, ['array', 'json', 'collection', 'object'])) {
            return json_decode($valor, true);
        }

        return $valor;
    }
}
