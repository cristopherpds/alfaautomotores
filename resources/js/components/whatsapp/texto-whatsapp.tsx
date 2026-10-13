import { Fragment } from 'react';
import type { ReactNode } from 'react';

/** Las URL sueltas, con el mismo criterio que `FormatoWhatsapp` del lado PHP. */
const URL = /(https?:\/\/[^\s<>"']+[^\s<>"'.,;:!?)\]])/g;

/** `*negrita*`, `_cursiva_` y `~tachado~`, como los pinta WhatsApp. */
const MARCAS = /(\*[^*\n]+\*|_[^_\n]+_|~[^~\n]+~)/g;

function conMarcas(texto: string, clave: string): ReactNode[] {
    return texto.split(MARCAS).map((parte, indice) => {
        const id = `${clave}-${indice}`;
        const interior = parte.slice(1, -1);

        if (parte.length > 2 && interior.trim() === interior) {
            if (parte.startsWith('*') && parte.endsWith('*')) {
                return <strong key={id}>{interior}</strong>;
            }

            if (parte.startsWith('_') && parte.endsWith('_')) {
                return <em key={id}>{interior}</em>;
            }

            if (parte.startsWith('~') && parte.endsWith('~')) {
                return <s key={id}>{interior}</s>;
            }
        }

        return <Fragment key={id}>{parte}</Fragment>;
    });
}

/**
 * Un cuerpo de WhatsApp como lo ve el cliente en el celular: con negritas,
 * cursivas, tachados y los links que se pueden tocar. Las URL se separan
 * primero para que un `_` adentro de un link no se tome por cursiva.
 */
export function TextoWhatsapp({ texto }: { texto: string }) {
    return (
        <>
            {texto.split(URL).map((parte, indice) =>
                indice % 2 === 1 ? (
                    <a
                        key={indice}
                        href={parte}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="underline underline-offset-2"
                    >
                        {parte}
                    </a>
                ) : (
                    <Fragment key={indice}>
                        {conMarcas(parte, String(indice))}
                    </Fragment>
                ),
            )}
        </>
    );
}
