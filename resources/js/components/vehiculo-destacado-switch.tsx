import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import VehiculoController from '@/actions/App/Http/Controllers/Panel/VehiculoController';
import { Switch } from '@/components/ui/switch';

type VehiculoDestacadoSwitchProps = {
    vehiculoId: number;
    destacado: boolean;
    disabled?: boolean;
    'aria-label': string;
};

/**
 * Fija o saca el vehículo de la portada.
 *
 * El tope de destacados lo valida el servidor, así que el switch se pinta
 * optimista y vuelve solo a su estado si la petición se rechaza.
 */
export default function VehiculoDestacadoSwitch({
    vehiculoId,
    destacado,
    disabled = false,
    'aria-label': ariaLabel,
}: VehiculoDestacadoSwitchProps) {
    const [marcado, setMarcado] = useState(destacado);
    const [enviando, setEnviando] = useState(false);

    const alternar = (valor: boolean) => {
        setMarcado(valor);
        setEnviando(true);

        router.patch(
            VehiculoController.destacado.url(vehiculoId),
            { destacado: valor },
            {
                preserveScroll: true,
                onError: (errors) => {
                    setMarcado(!valor);

                    if (errors.destacado) {
                        toast.error(errors.destacado);
                    }
                },
                onFinish: () => setEnviando(false),
            },
        );
    };

    return (
        <Switch
            checked={marcado}
            onCheckedChange={alternar}
            disabled={disabled || enviando}
            aria-label={ariaLabel}
            data-test={`destacar-vehiculo-${vehiculoId}-switch`}
        />
    );
}
