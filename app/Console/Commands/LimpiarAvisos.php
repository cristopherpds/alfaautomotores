<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Borra los avisos de la campana que ya se leyeron hace más de un mes. La
 * campana muestra sólo los últimos, así que los viejos sólo engordan la
 * tabla. Los no leídos no se tocan: siguen sumando al contador.
 */
class LimpiarAvisos extends Command
{
    /**
     * Días que se guarda un aviso después de leído.
     */
    public const DIAS = 30;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'avisos:limpiar';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Borrar los avisos de la campana leídos hace más de '.self::DIAS.' días';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $borrados = DatabaseNotification::query()
            ->whereNotNull('read_at')
            ->where('read_at', '<', now()->subDays(self::DIAS))
            ->delete();

        $this->info("Avisos borrados: {$borrados}.");

        return self::SUCCESS;
    }
}
