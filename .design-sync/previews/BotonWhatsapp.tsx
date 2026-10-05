import { BotonWhatsapp } from 'alfa-ui';

const contactos = [
    { nombre: 'Ventas', detalle: 'Autos usados', numero: '59899000000', mensaje: 'Hola, quería consultar por un auto del catálogo.' },
    { nombre: 'Taller', detalle: 'Turnos y presupuestos', numero: '59899000001', mensaje: 'Hola, quería agendar un turno en el taller.' },
];

export const Flotante = () => (
    <div className="alfa" style={{ position: 'relative', width: 420, height: 480 }}>
        <BotonWhatsapp nombre="Alfa Automotores" titulo="¡Hola!" subtitulo="¿En qué te podemos ayudar?" fueraDeHorario={false} contactos={contactos} />
    </div>
);
