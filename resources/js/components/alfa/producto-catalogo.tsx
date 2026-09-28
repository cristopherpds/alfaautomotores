import { useEffect, useMemo, useRef, useState } from 'react';
import { ProductoGrid } from '@/components/alfa/producto-card';
import {
    filtrar,
    filtrosIniciales,
    montoLegible,
    opcionesDe,
    ORDENES,
    PASO,
} from '@/lib/productos';
import type { Filtros, Orden } from '@/lib/productos';
import type { Producto } from '@/types';

/**
 * Cuatro filas de tres tarjetas en pantalla ancha. Doce divide exacto por 3, 2
 * y 1, así que ninguna de las tres grillas queda con una fila coja.
 */
const POR_PAGINA = 12;

const TODO = 'Todo';

/**
 * La grilla con filtros que comparten `/movilidad` y `/bicicletas`.
 *
 * Las dos páginas son la misma tabla partida por familia, así que la mecánica
 * —chips, filtros, paginación de navegador— vive acá una sola vez. Lo que
 * cambia lo deciden los datos: con más de una familia los chips filtran por
 * familia y con una sola (bicicletas) filtran por rodado, y el deslizador de
 * precio no se dibuja cuando la sección no publica precios.
 */
export function ProductoCatalogo({ productos }: { productos: Producto[] }) {
    const opciones = useMemo(() => opcionesDe(productos), [productos]);
    const [filtros, setFiltros] = useState<Filtros>(() =>
        filtrosIniciales(opciones),
    );
    const [pagina, setPagina] = useState(1);
    const grilla = useRef<HTMLElement>(null);
    /** Distingue el cambio de página del reinicio por filtro, que no desplaza. */
    const saltando = useRef(false);

    const lista = useMemo(
        () => filtrar(productos, filtros),
        [productos, filtros],
    );

    const paginas = Math.max(1, Math.ceil(lista.length / POR_PAGINA));
    /* Filtrar puede dejar menos páginas de las que había: nunca pasarse. */
    const actual = Math.min(pagina, paginas);
    const desde = (actual - 1) * POR_PAGINA;
    const visibles = lista.slice(desde, desde + POR_PAGINA);

    /* Los chips filtran por familia donde hay varias y por rodado donde no:
       en bicicletas la familia es una sola y lo que ordena el catálogo es el
       tamaño de la rueda. */
    const porFamilia = opciones.familias.length > 1;

    const chips: [string, string][] = porFamilia
        ? opciones.familias
        : opciones.rodados.map((rodado) => [rodado, `Rodado ${rodado}`]);

    const chipActivo = porFamilia ? filtros.familia : filtros.rodado;

    const set = <K extends keyof Filtros>(clave: K, valor: Filtros[K]) => {
        setFiltros((previos) => ({ ...previos, [clave]: valor }));
        setPagina(1);
    };

    const limpiar = () => {
        setFiltros(filtrosIniciales(opciones));
        setPagina(1);
    };

    /* Al cambiar de página se vuelve al principio de la grilla: si no, se
       aterriza en mitad de la tanda nueva. */
    const irA = (destino: number) => {
        if (destino === actual) {
            return;
        }

        saltando.current = true;
        setPagina(destino);
    };

    /* El desplazamiento va después del repintado. Hacerlo dentro de `irA` lo
       arranca contra el listado viejo y el anclaje del navegador lo deshace. */
    useEffect(() => {
        if (!saltando.current) {
            return;
        }

        saltando.current = false;

        grilla.current?.scrollIntoView({
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)')
                .matches
                ? 'auto'
                : 'smooth',
            block: 'start',
        });
    }, [pagina]);

    return (
        <>
            <section className="shell" style={{ paddingBlock: '22px 0' }}>
                {chips.length > 1 && (
                    <div className="taller-areas">
                        <button
                            type="button"
                            className="taller-areas__chip"
                            data-activo={chipActivo === TODO}
                            onClick={() =>
                                set(porFamilia ? 'familia' : 'rodado', TODO)
                            }
                        >
                            {porFamilia ? 'Todo' : 'Todos los rodados'}
                        </button>

                        {chips.map(([valor, etiqueta]) => (
                            <button
                                key={valor}
                                type="button"
                                className="taller-areas__chip"
                                data-activo={chipActivo === valor}
                                onClick={() =>
                                    set(
                                        porFamilia ? 'familia' : 'rodado',
                                        valor,
                                    )
                                }
                            >
                                {etiqueta}
                            </button>
                        ))}
                    </div>
                )}

                <div className="filters">
                    <label>
                        <span className="field-label">Disponibilidad</span>
                        <select
                            value={filtros.disponibilidad}
                            onChange={(e) =>
                                set('disponibilidad', e.target.value)
                            }
                        >
                            <option value={TODO}>Todo el catálogo</option>
                            {opciones.disponibilidades.map(
                                ([valor, etiqueta]) => (
                                    <option key={valor} value={valor}>
                                        {etiqueta}
                                    </option>
                                ),
                            )}
                        </select>
                    </label>

                    {porFamilia && opciones.rodados.length > 1 && (
                        <label>
                            <span className="field-label">Rodado</span>
                            <select
                                value={filtros.rodado}
                                onChange={(e) => set('rodado', e.target.value)}
                            >
                                <option value={TODO}>Todos</option>
                                {opciones.rodados.map((rodado) => (
                                    <option key={rodado} value={rodado}>
                                        {rodado}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}

                    {/* Sin precios publicados el deslizador no filtra nada: la
                        sección de bicicletas se cotiza entera por WhatsApp. */}
                    {opciones.hayPrecios && (
                        <label className="is-wide">
                            <span className="field-label">
                                Precio hasta {montoLegible(filtros.precio)}
                            </span>
                            <input
                                type="range"
                                min={opciones.precioMin}
                                max={opciones.precioMax}
                                step={PASO}
                                value={filtros.precio}
                                onChange={(e) =>
                                    set('precio', Number(e.target.value))
                                }
                            />
                        </label>
                    )}

                    {opciones.hayPrecios && (
                        <label>
                            <span className="field-label">Ordenar por</span>
                            <select
                                value={filtros.orden}
                                onChange={(e) =>
                                    set('orden', e.target.value as Orden)
                                }
                            >
                                {ORDENES.map((orden) => (
                                    <option
                                        key={orden.value}
                                        value={orden.value}
                                    >
                                        {orden.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}
                </div>

                <div className="filters-summary">
                    <span className="filters-summary__count" aria-live="polite">
                        {lista.length === 0
                            ? 'Sin resultados'
                            : lista.length <= POR_PAGINA
                              ? `${lista.length} ${lista.length === 1 ? 'producto' : 'productos'}`
                              : `${desde + 1}–${desde + visibles.length} de ${lista.length} productos`}
                    </span>

                    <button
                        type="button"
                        className="filters-summary__reset"
                        onClick={limpiar}
                    >
                        Limpiar filtros
                    </button>
                </div>
            </section>

            <section
                className="shell catalog-grid"
                style={{ paddingBlock: '22px clamp(56px, 7vw, 96px)' }}
                ref={grilla}
            >
                {lista.length > 0 ? (
                    <>
                        <ProductoGrid productos={visibles} />

                        {paginas > 1 && (
                            <nav
                                className="paginacion"
                                aria-label="Paginación del catálogo"
                            >
                                <button
                                    type="button"
                                    className="paginacion__salto"
                                    onClick={() => irA(actual - 1)}
                                    disabled={actual === 1}
                                >
                                    ← Anterior
                                </button>

                                <div className="paginacion__numeros">
                                    {Array.from(
                                        { length: paginas },
                                        (_, i) => i + 1,
                                    ).map((n) => (
                                        <button
                                            key={n}
                                            type="button"
                                            className="paginacion__numero"
                                            aria-current={
                                                n === actual
                                                    ? 'page'
                                                    : undefined
                                            }
                                            aria-label={`Página ${n} de ${paginas}`}
                                            onClick={() => irA(n)}
                                        >
                                            {n}
                                        </button>
                                    ))}
                                </div>

                                <button
                                    type="button"
                                    className="paginacion__salto"
                                    onClick={() => irA(actual + 1)}
                                    disabled={actual === paginas}
                                >
                                    Siguiente →
                                </button>
                            </nav>
                        )}
                    </>
                ) : (
                    <div className="empty">
                        <p className="empty__title">Sin resultados</p>
                        <p className="empty__text">
                            Probá quitar algún filtro o mirar el catálogo
                            completo.
                        </p>
                        <button
                            type="button"
                            className="btn"
                            style={{ padding: '14px 26px' }}
                            onClick={limpiar}
                        >
                            Limpiar filtros
                        </button>
                    </div>
                )}
            </section>
        </>
    );
}
