import { Alert, AlertDescription, AlertTitle, CircleAlert, Info } from 'alfa-ui';

export const Default = () => (
    <Alert className="w-[28rem]">
        <Info />
        <AlertTitle>El botón de WhatsApp está activo</AlertTitle>
        <AlertDescription>Los cambios se ven en el sitio público apenas guardás.</AlertDescription>
    </Alert>
);

export const Destructive = () => (
    <Alert variant="destructive" className="w-[28rem]">
        <CircleAlert />
        <AlertTitle>No se pudo guardar el vehículo</AlertTitle>
        <AlertDescription>Revisá el precio y el kilometraje: tienen que ser números mayores a cero.</AlertDescription>
    </Alert>
);

export const SoloTitulo = () => (
    <Alert className="w-[28rem]">
        <Info />
        <AlertTitle>Hay 3 fichas sin fotos en el catálogo.</AlertTitle>
    </Alert>
);
