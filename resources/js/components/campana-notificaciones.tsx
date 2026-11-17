import { Bell, CalendarDays, CheckCheck, HandCoins } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Notificaciones } from '@/hooks/use-notificaciones';
import { cn } from '@/lib/utils';

const formatoHora = new Intl.DateTimeFormat('es-UY', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
});

/**
 * La campana del encabezado del panel: las cotizaciones y los turnos que
 * entraron, con las no leídas en el badge. Tocar un aviso lo marca leído y
 * abre lo que avisa.
 */
export function CampanaNotificaciones({
    notificaciones,
}: {
    notificaciones: Notificaciones;
}) {
    const { resumen, abrir, marcarTodas } = notificaciones;
    const noLeidas = resumen?.noLeidas ?? 0;
    const avisos = resumen?.avisos ?? [];

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label={
                        noLeidas > 0 ? `Avisos: ${noLeidas} sin leer` : 'Avisos'
                    }
                    data-test="campana-notificaciones"
                >
                    <Bell />
                    {noLeidas > 0 && (
                        <Badge className="absolute -top-1 -right-1 h-5 min-w-5 rounded-full px-1 tabular-nums">
                            {noLeidas > 99 ? '99+' : noLeidas}
                        </Badge>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
                <DropdownMenuLabel className="flex items-center justify-between gap-2">
                    Avisos
                    {noLeidas > 0 && (
                        <span className="text-xs font-normal text-muted-foreground">
                            {noLeidas} sin leer
                        </span>
                    )}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {avisos.length === 0 ? (
                    <p className="px-2 py-6 text-center text-sm text-muted-foreground">
                        Todavía no hay avisos.
                    </p>
                ) : (
                    <DropdownMenuGroup>
                        {avisos.map((aviso) => (
                            <DropdownMenuItem
                                key={aviso.id}
                                onSelect={() => abrir(aviso)}
                                className="items-start gap-3"
                            >
                                {aviso.tipo === 'turno' ? (
                                    <CalendarDays className="mt-0.5" />
                                ) : (
                                    <HandCoins className="mt-0.5" />
                                )}
                                <div className="grid min-w-0 flex-1 gap-0.5">
                                    <span
                                        className={cn(
                                            'truncate',
                                            !aviso.leida && 'font-semibold',
                                        )}
                                    >
                                        {aviso.titulo}
                                    </span>
                                    <span className="truncate text-xs text-muted-foreground">
                                        {aviso.detalle}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {formatoHora.format(new Date(aviso.at))}
                                    </span>
                                </div>
                                {!aviso.leida && (
                                    <span
                                        className="mt-1.5 size-2 shrink-0 rounded-full bg-primary"
                                        aria-label="Sin leer"
                                    />
                                )}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuGroup>
                )}
                {noLeidas > 0 && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onSelect={marcarTodas}>
                            <CheckCheck />
                            Marcar todas como leídas
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
