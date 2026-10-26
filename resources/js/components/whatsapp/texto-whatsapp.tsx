import { Fragment } from 'react';
import type { ReactNode } from 'react';

/**
 * Las URL sueltas, con el mismo criterio que `FormatoWhatsapp` del lado PHP.
 * También las que empiezan con `www.` sin protocolo, como las linkea
 * WhatsApp en el celular (y `linkify.ts` de Vocero).
 */
const URL =
    /((?:https?:\/\/|(?<![\w./@-])www\.)[^\s<>"']+[^\s<>"'.,;:!?)\]])/gi;

/** El destino del link: a un `www.` suelto se le antepone `https://`. */
function hrefDe(url: string): string {
    return /^https?:\/\//i.test(url) ? url : `https://${url}`;
}

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
                        href={hrefDe(parte)}
                        target="_blank"
                        rel="noopener noreferrer nofollow"
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
