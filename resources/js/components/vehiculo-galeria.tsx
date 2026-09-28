import VehiculoImagenController from '@/actions/App/Http/Controllers/Panel/VehiculoImagenController';
import GaleriaFotos from '@/components/galeria-fotos';
import type { FotoVehiculo } from '@/types';

type VehiculoGaleriaProps = {
    vehiculoId: number;
    fotos: FotoVehiculo[];
    maxImagenes: number;
};

/** La galería compartida, apuntada a las rutas de `VehiculoImagenController`. */
export default function VehiculoGaleria({
    vehiculoId,
    fotos,
    maxImagenes,
}: VehiculoGaleriaProps) {
    return (
        <GaleriaFotos
            fotos={fotos}
            maxImagenes={maxImagenes}
            subir={VehiculoImagenController.store.form(vehiculoId)}
            ordenUrl={VehiculoImagenController.orden.url(vehiculoId)}
            eliminarUrl={(fotoId) =>
                VehiculoImagenController.destroy.url([vehiculoId, fotoId])
            }
            sujeto="vehículo"
        />
    );
}
