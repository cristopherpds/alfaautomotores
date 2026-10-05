import { IconoWhatsapp, TarjetaWhatsapp } from 'alfa-ui';

const contactos = [
    { nombre: 'Ventas', detalle: 'Autos usados', numero: '59899000000', mensaje: 'Hola, quería consultar por un auto del catálogo.' },
    { nombre: 'Taller', detalle: 'Turnos y presupuestos', numero: '59899000001', mensaje: 'Hola, quería agendar un turno en el taller.' },
    { nombre: 'Lavadero', detalle: null, numero: '59899000002', mensaje: 'Hola, quería consultar por un lavado.' },
];

export const EnHorario = () => (
    <div className="alfa" style={{ width: 400 }}>
        <div className="wpp wpp--vista-previa">
            <TarjetaWhatsapp titulo="¡Hola!" subtitulo="¿En qué te podemos ayudar?" fueraDeHorario={false} contactos={contactos} />
            <span className="wpp__boton">
                <IconoWhatsapp className="wpp__icono" />
            </span>
        </div>
    </div>
);

export const FueraDeHorario = () => (
    <div className="alfa" style={{ width: 400 }}>
        <div className="wpp wpp--vista-previa">
            <TarjetaWhatsapp
                titulo="¡Hola!"
                subtitulo="Ahora estamos cerrados: dejanos tu mensaje y te respondemos."
                fueraDeHorario
                contactos={contactos.slice(0, 1)}
            />
            <span className="wpp__boton">
                <IconoWhatsapp className="wpp__icono" />
            </span>
        </div>
    </div>
);
