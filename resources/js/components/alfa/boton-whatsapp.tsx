import { useEffect, useId, useRef, useState } from 'react';
import { whatsapp } from '@/lib/alfa';
import type { ContactoWhatsapp } from '@/types';

/**
 * El ícono de WhatsApp de Font Awesome Free 6 (`fab fa-whatsapp`),
 * CC BY 4.0 — https://fontawesome.com/license/free.
 */
export function IconoWhatsapp({ className }: { className?: string }) {
    return (
        <svg
            className={className}
            viewBox="0 0 448 512"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
            focusable="false"
        >
            <path
                fill="currentColor"
                d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"
            />
        </svg>
    );
}

type TarjetaProps = {
    id?: string;
    titulo: string;
    subtitulo: string;
    /** Nadie en horario: la lista trae sólo el contacto de respaldo. */
    fueraDeHorario: boolean;
    contactos: ContactoWhatsapp[];
    /** Cerrada queda en el DOM pero oculta: el botón la nombra en `aria-controls`. */
    oculta?: boolean;
};

/**
 * La tarjeta de contactos: encabezado con el saludo y un renglón por cada
 * contacto disponible, que abre su chat. La usan el botón flotante y la
 * vista previa del panel.
 */
export function TarjetaWhatsapp({
    id,
    titulo,
    subtitulo,
    fueraDeHorario,
    contactos,
    oculta = false,
}: TarjetaProps) {
    return (
        <div
            id={id}
            className="wpp__tarjeta"
            role="dialog"
            aria-label="Contactos de WhatsApp"
            hidden={oculta}
        >
            <div className="wpp__encabezado">
                <p className="wpp__titulo">{titulo}</p>
                <p className="wpp__subtitulo">{subtitulo}</p>
            </div>

            <div className="wpp__cuerpo">
                {fueraDeHorario && (
                    <p className="wpp__aviso">
                        Fuera de horario: te respondemos apenas abramos.
                    </p>
                )}

                <ul className="wpp__contactos">
                    {contactos.map((contacto) => (
                        <li key={`${contacto.numero}-${contacto.nombre}`}>
                            <a
                                className="wpp__contacto"
                                href={whatsapp(
                                    contacto.numero,
                                    contacto.mensaje,
                                )}
                                target="_blank"
                                rel="noopener noreferrer"
                                data-test="wpp-contacto-link"
                            >
                                <IconoWhatsapp className="wpp__icono-contacto" />
                                <span>
                                    <span className="wpp__contacto-nombre">
                                        {contacto.nombre}
                                    </span>
                                    {contacto.detalle && (
                                        <span className="wpp__contacto-detalle">
                                            {contacto.detalle}
                                        </span>
                                    )}
                                </span>
                            </a>
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}

type BotonProps = Omit<TarjetaProps, 'id' | 'oculta'> & { nombre: string };

/**
 * El botón flotante de WhatsApp del sitio público, abajo a la derecha.
 *
 * Al tocarlo abre la tarjeta de contactos y se convierte en una X. Se cierra
 * con la X, con Escape o con un click afuera. Lo monta `AlfaLayout` una sola
 * vez; qué contactos trae lo decide el servidor según la hora
 * (`Ajuste::whatsappDisponible()`).
 */
export default function BotonWhatsapp({ nombre, ...tarjeta }: BotonProps) {
    const [abierto, setAbierto] = useState(false);
    const contenedor = useRef<HTMLDivElement>(null);
    const idTarjeta = useId();

    useEffect(() => {
        if (!abierto) {
            return;
        }

        const alTeclear = (evento: KeyboardEvent) => {
            if (evento.key === 'Escape') {
                setAbierto(false);
            }
        };

        const alClickear = (evento: MouseEvent) => {
            if (
                contenedor.current &&
                !contenedor.current.contains(evento.target as Node)
            ) {
                setAbierto(false);
            }
        };

        document.addEventListener('keydown', alTeclear);
        document.addEventListener('mousedown', alClickear);

        return () => {
            document.removeEventListener('keydown', alTeclear);
            document.removeEventListener('mousedown', alClickear);
        };
    }, [abierto]);

    return (
        <div ref={contenedor} className="wpp" data-abierto={abierto}>
            <TarjetaWhatsapp {...tarjeta} id={idTarjeta} oculta={!abierto} />

            <button
                type="button"
                className="wpp__boton"
                aria-controls={idTarjeta}
                aria-expanded={abierto}
                aria-label={
                    abierto
                        ? 'Cerrar los contactos de WhatsApp'
                        : `Escribirle a ${nombre} por WhatsApp`
                }
                onClick={() => setAbierto((previo) => !previo)}
                data-test="wpp-boton"
            >
                {abierto ? (
                    <svg
                        viewBox="0 0 24 24"
                        className="wpp__icono"
                        aria-hidden="true"
                    >
                        <path
                            d="M6 6l12 12M18 6L6 18"
                            stroke="currentColor"
                            strokeWidth="2.5"
                            strokeLinecap="round"
                        />
                    </svg>
                ) : (
                    <IconoWhatsapp className="wpp__icono" />
                )}
            </button>
        </div>
    );
}
