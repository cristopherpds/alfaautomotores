import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Pause, Play } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { HeroBoton, HeroSlide } from '@/types';

/** Cuánto se queda cada slide antes de pasar al siguiente. */
const INTERVALO_MS = 7000;

/** Una ruta del sitio (`/catalogo`), que navega con Inertia sin recargar. */
function esInterna(url: string): boolean {
    return url.startsWith('/') && !url.startsWith('//');
}

function BotonHero({ boton, clase }: { boton: HeroBoton; clase: string }) {
    if (esInterna(boton.url)) {
        return (
            <Link href={boton.url} className={clase}>
                {boton.texto}
            </Link>
        );
    }

    /* `mailto:` y `tel:` abren su aplicación; sólo la web va a otra pestaña. */
    const otraPestana = /^https?:\/\//i.test(boton.url);

    return (
        <a
            href={boton.url}
            className={clase}
            {...(otraPestana && {
                target: '_blank',
                rel: 'noopener noreferrer',
                'aria-label': `${boton.texto} (se abre en una pestaña nueva)`,
            })}
        >
            {boton.texto}
        </a>
    );
}

type SlideProps = {
    slide: HeroSlide;
    activo: boolean;
    /** El título del slide visible es el `h1` de la página; el resto, no. */
    esTitular: boolean;
};

/**
 * Un slide: el fondo, el velo y los textos. Lo usan la portada y la vista
 * previa del panel, así lo que se ve al cargarlo es lo que sale publicado.
 */
export function HeroSlideView({ slide, activo, esTitular }: SlideProps) {
    const video = useRef<HTMLVideoElement>(null);

    /* Sólo corre el video del slide visible: los demás quedan en pausa para
       no gastar datos ni batería en algo que nadie ve. */
    useEffect(() => {
        const elemento = video.current;

        if (!elemento) {
            return;
        }

        if (activo) {
            elemento.play().catch(() => {
                /* El navegador puede negar el autoplay (ahorro de datos):
                   queda el primer fotograma, que también sirve de fondo. */
            });
        } else {
            elemento.pause();
        }
    }, [activo, slide.fondo]);

    const Titulo = esTitular ? 'h1' : 'p';

    /* `abajo-izquierda` → fila y columna de la grilla de 3×3; `alfa.css` lee
       los dos atributos para alinear el bloque y orientar el velo. */
    const [vertical, horizontal] = slide.posicion.split('-');

    return (
        <div
            className={['hero__slide', activo && 'is-activo']
                .filter(Boolean)
                .join(' ')}
            data-vertical={vertical}
            data-horizontal={horizontal}
            aria-hidden={!activo}
            inert={!activo}
        >
            {/* Fondo decorativo: sin texto alternativo ni controles porque no
                aporta contenido. `muted` + `playsInline` son los dos
                requisitos para que el navegador (sobre todo iOS) deje arrancar
                el autoplay. */}
            {!slide.fondo ? null : slide.tipoFondo === 'video' ? (
                <video
                    ref={video}
                    key={slide.fondo}
                    className="hero__fondo"
                    src={slide.fondo}
                    muted
                    loop
                    playsInline
                    preload={activo ? 'auto' : 'metadata'}
                    aria-hidden="true"
                    tabIndex={-1}
                />
            ) : (
                <img className="hero__fondo" src={slide.fondo} alt="" />
            )}
            <div className="hero__velo" aria-hidden="true" />

            <div className="shell hero__inner">
                {slide.eyebrow && <p className="eyebrow">{slide.eyebrow}</p>}

                <Titulo className="hero__titulo">{slide.titulo}</Titulo>

                {slide.bajada && <p className="lede">{slide.bajada}</p>}

                {slide.botones.length > 0 && (
                    <div className="hero__actions">
                        {slide.botones.map((boton, indice) => (
                            <BotonHero
                                key={indice}
                                boton={boton}
                                clase={
                                    indice === 0
                                        ? 'btn btn--light'
                                        : 'btn btn--outline-light'
                                }
                            />
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

function prefiereMenosMovimiento(): boolean {
    return (
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );
}

/**
 * El hero de la portada: uno o varios slides que se alternan con un fundido.
 *
 * Pasa solo cada siete segundos y se detiene mientras el mouse o el foco
 * están adentro, con la pestaña oculta, o si quien visita pidió menos
 * movimiento. Como se mueve solo por más de cinco segundos, lleva su propio
 * botón de pausa (WCAG 2.2.2): no sacarlo, frenar con el mouse encima no
 * alcanza para quien navega con teclado o con un lector de pantalla.
 */
export function Hero({ slides }: { slides: HeroSlide[] }) {
    const [actual, setActual] = useState(0);
    const [pausado, setPausado] = useState(prefiereMenosMovimiento);
    const [encima, setEncima] = useState(false);
    const [oculta, setOculta] = useState(false);

    const total = slides.length;
    const esCarrusel = total > 1;

    const ir = useCallback(
        (indice: number) => setActual((indice + total) % total),
        [total],
    );

    useEffect(() => {
        const alCambiar = () => setOculta(document.hidden);

        document.addEventListener('visibilitychange', alCambiar);

        return () =>
            document.removeEventListener('visibilitychange', alCambiar);
    }, []);

    useEffect(() => {
        if (!esCarrusel || pausado || encima || oculta) {
            return;
        }

        const temporizador = window.setTimeout(
            () => ir(actual + 1),
            INTERVALO_MS,
        );

        return () => window.clearTimeout(temporizador);
    }, [actual, esCarrusel, pausado, encima, oculta, ir]);

    return (
        <section
            className={['hero', esCarrusel && 'hero--carrusel']
                .filter(Boolean)
                .join(' ')}
            {...(esCarrusel && {
                'aria-roledescription': 'carrusel',
                'aria-label': 'Destacados',
            })}
            onMouseEnter={() => setEncima(true)}
            onMouseLeave={() => setEncima(false)}
            onFocus={() => setEncima(true)}
            onBlur={(evento) => {
                if (!evento.currentTarget.contains(evento.relatedTarget)) {
                    setEncima(false);
                }
            }}
        >
            {slides.map((slide, indice) => (
                <HeroSlideView
                    key={slide.id ?? `por-defecto-${indice}`}
                    slide={slide}
                    activo={indice === actual}
                    esTitular={indice === actual}
                />
            ))}

            {esCarrusel && (
                <div className="shell hero__controles">
                    <button
                        type="button"
                        className="hero__control"
                        onClick={() => setPausado((valor) => !valor)}
                        aria-label={
                            pausado
                                ? 'Reanudar el carrusel'
                                : 'Pausar el carrusel'
                        }
                    >
                        {pausado ? <Play /> : <Pause />}
                    </button>

                    <button
                        type="button"
                        className="hero__control"
                        onClick={() => ir(actual - 1)}
                        aria-label="Slide anterior"
                    >
                        <ChevronLeft />
                    </button>

                    <div className="hero__puntos">
                        {slides.map((slide, indice) => (
                            <button
                                key={slide.id ?? indice}
                                type="button"
                                className="hero__punto"
                                onClick={() => ir(indice)}
                                aria-label={`Ir al slide ${indice + 1} de ${total}`}
                                aria-current={indice === actual}
                            />
                        ))}
                    </div>

                    <button
                        type="button"
                        className="hero__control"
                        onClick={() => ir(actual + 1)}
                        aria-label="Slide siguiente"
                    >
                        <ChevronRight />
                    </button>
                </div>
            )}

            {/* Para lectores de pantalla: anuncia el cambio sólo cuando lo
                pide la persona (con el pase automático sería ruido). */}
            {esCarrusel && (
                <p className="hero__estado" aria-live="polite">
                    {pausado || encima ? `Slide ${actual + 1} de ${total}` : ''}
                </p>
            )}
        </section>
    );
}
