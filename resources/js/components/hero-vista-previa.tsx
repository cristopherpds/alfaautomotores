import { Monitor, Smartphone } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { CSSProperties } from 'react';
import { HeroSlideView } from '@/components/alfa/hero';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { HeroSlide } from '@/types';

type Dispositivo = 'escritorio' | 'movil';

/**
 * Las dos pantallas que se simulan, con lo que mide el hero real en cada una.
 *
 * El hero del sitio calcula sus tamaños con `vw`/`vh` (ver `.hero` en
 * `alfa.css`); dentro del panel esas unidades medirían la ventana del panel.
 * Así que acá van los valores ya resueltos para esa pantalla —el alto es el
 * de la ventana menos el header (73px)— y el conjunto se escala entero.
 */
const PANTALLAS: Record<
    Dispositivo,
    { ancho: number; alto: number; variables: Record<string, string> }
> = {
    /* Laptop de 1366×768, la más baja que tiene que entrar en una vista. */
    escritorio: {
        ancho: 1366,
        alto: 695,
        variables: {
            '--vista-titulo': '69px',
            '--vista-margen': '18px',
            '--vista-lede': '16px',
            '--vista-margen-lede': '28px',
            '--vista-padding': '61px',
            '--vista-gutter': '48px',
        },
    },
    /* Un teléfono de 390×844. */
    movil: {
        ancho: 390,
        alto: 771,
        variables: {
            '--vista-titulo': '34px',
            '--vista-margen': '20px',
            '--vista-lede': '15px',
            '--vista-margen-lede': '30px',
            '--vista-padding': '40px',
            '--vista-gutter': '18px',
        },
    },
};

/** El ancho máximo del marco del teléfono, para que no ocupe toda la columna. */
const ANCHO_MOVIL = 240;

/**
 * La vista previa de un slide del hero, con el mismo componente que usa la
 * portada. Va `inert` y oculta a lectores de pantalla: es una imagen de cómo
 * queda, no algo para usar.
 */
export function HeroVistaPrevia({ slide }: { slide: HeroSlide }) {
    const [dispositivo, setDispositivo] = useState<Dispositivo>('escritorio');
    const [anchoDisponible, setAnchoDisponible] = useState(0);
    const medidor = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const elemento = medidor.current;

        if (!elemento) {
            return;
        }

        const medir = () =>
            setAnchoDisponible(elemento.getBoundingClientRect().width);

        /* Una primera medida a mano: después de hidratar la página del
           servidor, el observador puede no volver a avisar si el ancho no
           cambia. */
        medir();

        const observador = new ResizeObserver(medir);

        observador.observe(elemento);

        return () => observador.disconnect();
    }, []);

    const pantalla = PANTALLAS[dispositivo];
    const anchoMarco =
        dispositivo === 'movil'
            ? Math.min(anchoDisponible, ANCHO_MOVIL)
            : anchoDisponible;
    const escala = anchoMarco / pantalla.ancho;

    return (
        /* `grid-cols-1` (minmax(0, 1fr)) y `min-w-0`: sin ellos la columna crece
           hasta el ancho fijo del marco y, al achicar la ventana, el medidor
           queda trabado en el ancho viejo. */
        <div className="grid grid-cols-1 gap-3">
            <ToggleGroup
                type="single"
                variant="outline"
                size="sm"
                value={dispositivo}
                onValueChange={(valor) =>
                    valor && setDispositivo(valor as Dispositivo)
                }
                aria-label="Pantalla de la vista previa"
            >
                <ToggleGroupItem value="escritorio">
                    <Monitor />
                    Escritorio
                </ToggleGroupItem>
                <ToggleGroupItem value="movil">
                    <Smartphone />
                    Móvil
                </ToggleGroupItem>
            </ToggleGroup>

            <div ref={medidor} className="w-full min-w-0">
                {anchoMarco > 0 && (
                    <div
                        className="mx-auto overflow-hidden rounded-md border"
                        style={{
                            width: anchoMarco,
                            height: pantalla.alto * escala,
                        }}
                    >
                        <div
                            className="alfa alfa--vista-previa"
                            aria-hidden="true"
                            inert
                            style={
                                {
                                    width: pantalla.ancho,
                                    height: pantalla.alto,
                                    transform: `scale(${escala})`,
                                    transformOrigin: 'top left',
                                    ...pantalla.variables,
                                } as CSSProperties
                            }
                        >
                            <section className="hero hero--vista-previa">
                                <HeroSlideView
                                    slide={slide}
                                    activo
                                    esTitular={false}
                                />
                            </section>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
