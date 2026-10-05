import { useEffect } from 'react';
import { Toaster, toast } from 'alfa-ui';

const Avisos = () => {
    useEffect(() => {
        toast.success('Vehículo guardado');
        toast.error('No se pudo exportar el CSV', { description: 'Probá de nuevo en unos minutos.' });
        toast('Turno confirmado por WhatsApp');
    }, []);

    return null;
};

export const Avisos3 = () => (
    <div className="h-72 w-[28rem]">
        <Toaster position="top-center" expand />
        <Avisos />
    </div>
);
