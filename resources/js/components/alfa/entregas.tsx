import { Link } from '@inertiajs/react';
import { catalogo } from '@/routes';
import type { Entrega } from '@/types';

/**
 * Segundos que tarda cada foto en cruzar la tira. Multiplicado por la cantidad
 * de fotos da la duración de la vuelta completa, así que la velocidad aparente
 * no cambia al sumar entregas.
 */
const SEGUNDOS_POR_FOTO = 4;

/**
 * Fotos que se piden de entrada, sin esperar a que entren en pantalla. Son las
 * que caben en la primera pasada del riel en un monitor ancho; el resto llega
 * por `loading="lazy"` a medida que se acercan al borde. Mientras una foto no
 * está, su recuadro se ve del gris del fondo y no como un hueco.
 */
const ADELANTADAS = 8;

type EntregasProps = {
    entregas: Entrega[];
};

/**
 * Prueba social de la portada: una tira de fotos de entregas que corre sola.
 *
 * El riel lleva el mismo juego de fotos dos veces y se desplaza exactamente la
 * mitad: al terminar, el segundo juego quedó donde arrancó el primero y el
 * corte no se ve. Para que la cuenta cierre, la separación entre recuadros va
 * como margen de cada uno y no como `gap` del riel —si fuera `gap`, entre los
 * dos juegos habría una separación de más y la vuelta saltaría medio hueco.
 *
 * El segundo juego va con `aria-hidden` para que no se lea dos veces.
 */
export function Entregas({ entregas }: EntregasProps) {
    if (entregas.length === 0) {
        return null;
    }

    const toma = (entrega: Entrega, indice: number, copia: boolean) => (
        <div
            className="entregas__toma"
            key={copia ? `${entrega.url}-copia` : entrega.url}
            aria-hidden={copia || undefined}
        >
            <img
                src={entrega.url}
                alt={
                    copia
                        ? ''
                        : `Entrega de un vehículo en el local, ${entrega.legible}`
                }
                loading={indice < ADELANTADAS ? 'eager' : 'lazy'}
                decoding="async"
            />

            <time className="entregas__fecha" dateTime={entrega.fecha}>
                {entrega.etiqueta}
            </time>
        </div>
    );

    return (
        <section className="entregas">
            <div className="shell entregas__head">
                <p className="eyebrow">Nuestros clientes</p>

                <div className="entregas__titular">
                    <h2>Cientos de llaves entregadas en todo el país.</h2>
                    <p className="lede">
                        Cada foto es una entrega real en nuestro local. Gracias
                        por la confianza.
                    </p>
                </div>
            </div>

            <div className="entregas__pista">
                <div
                    className="entregas__riel"
                    style={{
                        animationDuration: `${entregas.length * SEGUNDOS_POR_FOTO}s`,
                    }}
                >
                    {entregas.map((entrega, indice) =>
                        toma(entrega, indice, false),
                    )}
                    {entregas.map((entrega, indice) =>
                        toma(entrega, indice, true),
                    )}
                </div>
            </div>

            <div className="shell entregas__pie">
                <Link href={catalogo()} className="section__link">
                    Quiero ser el próximo
                </Link>
            </div>
        </section>
    );
}
