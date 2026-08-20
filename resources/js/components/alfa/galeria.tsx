import { useCallback, useEffect, useRef, useState } from 'react';
import { Photo } from '@/components/alfa/photo';

/** Marcadores de las secundarias mientras el vehículo no tenga fotos cargadas. */
const VISTAS = ['Interior', 'Trasera', 'Motor'];

/** Desplazamiento horizontal mínimo, en píxeles, para que un toque cuente como swipe. */
const SWIPE_MINIMO = 50;

type GaleriaProps = {
    fotos: string[];
    nombre: string;
    anio: number;
};

/**
 * Galería de la ficha: la portada arriba y el resto en miniaturas, todas
 * ampliables. El visor se monta sólo cuando hay una foto abierta.
 */
export function Galeria({ fotos, nombre, anio }: GaleriaProps) {
    const [abierta, setAbierta] = useState<number | null>(null);
    const cerrar = useCallback(() => setAbierta(null), []);

    if (fotos.length === 0) {
        return (
            <div className="detail__gallery">
                <div className="detail__frame">
                    <Photo
                        alt={nombre}
                        placeholder={nombre}
                        detalle={String(anio)}
                    />
                </div>

                <div className="detail__thumbs">
                    {VISTAS.map((vista) => (
                        <div className="detail__frame" key={vista}>
                            <Photo
                                alt={`${nombre} — ${vista.toLowerCase()}`}
                                placeholder={vista}
                            />
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    const [portada, ...secundarias] = fotos;

    return (
        <div className="detail__gallery">
            <button
                type="button"
                className="detail__frame detail__shot"
                onClick={() => setAbierta(0)}
                aria-label={`Ampliar la foto 1 de ${fotos.length}`}
            >
                <Photo
                    src={portada}
                    alt={nombre}
                    placeholder={nombre}
                    detalle={String(anio)}
                />
            </button>

            {secundarias.length > 0 && (
                <div className="detail__thumbs">
                    {secundarias.map((foto, indice) => (
                        <button
                            type="button"
                            key={foto}
                            className="detail__frame detail__shot"
                            onClick={() => setAbierta(indice + 1)}
                            aria-label={`Ampliar la foto ${indice + 2} de ${fotos.length}`}
                        >
                            <Photo
                                src={foto}
                                alt={`${nombre} — foto ${indice + 2}`}
                                placeholder={nombre}
                            />
                        </button>
                    ))}
                </div>
            )}

            {abierta !== null && (
                <Lightbox
                    fotos={fotos}
                    nombre={nombre}
                    indice={abierta}
                    onCambiar={setAbierta}
                    onCerrar={cerrar}
                />
            )}
        </div>
    );
}

type LightboxProps = {
    fotos: string[];
    nombre: string;
    indice: number;
    onCambiar: (indice: number) => void;
    onCerrar: () => void;
};

/**
 * Visor a pantalla completa. Usa el `<dialog>` nativo en vez de un overlay
 * propio: el top-layer lo deja fuera del alcance de cualquier `overflow` de la
 * ficha, y el foco atrapado y el cierre con Escape vienen de fábrica.
 */
function Lightbox({
    fotos,
    nombre,
    indice,
    onCambiar,
    onCerrar,
}: LightboxProps) {
    const dialogo = useRef<HTMLDialogElement>(null);
    const toqueInicial = useRef<number | null>(null);

    const mover = useCallback(
        (pasos: number) =>
            onCambiar((indice + pasos + fotos.length) % fotos.length),
        [indice, fotos.length, onCambiar],
    );

    useEffect(() => {
        const visor = dialogo.current;

        if (!visor) {
            return;
        }

        /* El guard es por StrictMode: en desarrollo el efecto corre dos veces,
           y `showModal()` sobre un dialog ya abierto tira InvalidStateError. */
        if (!visor.open) {
            visor.showModal();
        }

        /* El listener va nativo y no como `onClose` de React: el evento `close`
           no burbujea, y la delegación de React no lo entrega de forma fiable.
           Sin esto el Escape cierra el dialog pero deja el componente montado. */
        visor.addEventListener('close', onCerrar);

        /* El `<dialog>` modal no frena el scroll del fondo en Chrome. */
        const scrollPrevio = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            visor.removeEventListener('close', onCerrar);
            document.body.style.overflow = scrollPrevio;
        };
    }, [onCerrar]);

    useEffect(() => {
        const teclado = (evento: KeyboardEvent) => {
            if (evento.key === 'ArrowLeft') {
                mover(-1);
            }

            if (evento.key === 'ArrowRight') {
                mover(1);
            }
        };

        window.addEventListener('keydown', teclado);

        return () => window.removeEventListener('keydown', teclado);
    }, [mover]);

    const terminarToque = (evento: React.TouchEvent) => {
        if (toqueInicial.current === null) {
            return;
        }

        const recorrido =
            evento.changedTouches[0].clientX - toqueInicial.current;
        toqueInicial.current = null;

        if (Math.abs(recorrido) >= SWIPE_MINIMO) {
            mover(recorrido < 0 ? 1 : -1);
        }
    };

    return (
        <dialog
            ref={dialogo}
            className="lightbox"
            /* Clic fuera de la foto = cerrar. Se mira el destino y no el
               dialog: la figura ocupa todo el visor, así que ningún clic
               llega al dialog mismo. */
            onClick={(evento) => {
                const destino = evento.target as HTMLElement;

                if (!destino.closest('.lightbox__img, button')) {
                    dialogo.current?.close();
                }
            }}
        >
            <figure
                className="lightbox__figure"
                onTouchStart={(evento) => {
                    toqueInicial.current = evento.touches[0].clientX;
                }}
                onTouchEnd={terminarToque}
            >
                <img
                    src={fotos[indice]}
                    alt={`${nombre} — foto ${indice + 1} de ${fotos.length}`}
                    className="lightbox__img"
                />
            </figure>

            <button
                type="button"
                className="lightbox__close"
                onClick={() => dialogo.current?.close()}
                aria-label="Cerrar la vista ampliada"
            >
                ×
            </button>

            {fotos.length > 1 && (
                <>
                    <button
                        type="button"
                        className="lightbox__nav lightbox__nav--prev"
                        onClick={() => mover(-1)}
                        aria-label="Foto anterior"
                    >
                        ‹
                    </button>

                    <button
                        type="button"
                        className="lightbox__nav lightbox__nav--next"
                        onClick={() => mover(1)}
                        aria-label="Foto siguiente"
                    >
                        ›
                    </button>

                    <p className="lightbox__counter" aria-live="polite">
                        {indice + 1} / {fotos.length}
                    </p>
                </>
            )}
        </dialog>
    );
}
