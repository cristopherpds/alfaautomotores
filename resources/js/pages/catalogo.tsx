import { Head } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { VehicleGrid } from '@/components/alfa/vehicle-card';
import { fmtPrecio } from '@/lib/alfa';
import {
    filtrar,
    filtrosIniciales,
    opcionesDe,
    ORDENES,
    PASO,
} from '@/lib/catalogo';
import type { Filtros, Orden } from '@/lib/catalogo';
import type { Vehiculo } from '@/types';

/**
 * Cuatro filas de tres tarjetas en pantalla ancha. Doce divide exacto por 3, 2
 * y 1, así que ninguna de las tres grillas queda con una fila coja.
 */
const POR_PAGINA = 12;

export default function Catalogo({ vehiculos }: { vehiculos: Vehiculo[] }) {
    /* Marcas, años y extremos de precio salen del stock que llegó del servidor. */
    const opciones = useMemo(() => opcionesDe(vehiculos), [vehiculos]);
    const [filtros, setFiltros] = useState<Filtros>(() =>
        filtrosIniciales(opciones),
    );
    const [pagina, setPagina] = useState(1);
    const grilla = useRef<HTMLElement>(null);
    /** Distingue el cambio de página del reinicio por filtro, que no desplaza. */
    const saltando = useRef(false);

    const lista = useMemo(
        () => filtrar(vehiculos, filtros),
        [vehiculos, filtros],
    );

    const paginas = Math.max(1, Math.ceil(lista.length / POR_PAGINA));
    /* Filtrar puede dejar menos páginas de las que había: nunca pasarse. */
    const actual = Math.min(pagina, paginas);
    const desde = (actual - 1) * POR_PAGINA;
    const visibles = lista.slice(desde, desde + POR_PAGINA);

    /* Cualquier cambio de filtro devuelve el listado a la primera página. */
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
        /* Sin cambio de página React no repinta y el efecto nunca limpia la
           marca: quedaría armada para el próximo reinicio por filtro. */
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
            <Head title="Catálogo">
                <meta
                    name="description"
                    content={`Stock disponible en Rivera: ${vehiculos.length} vehículos revisados, con precios en dólares y financiación.`}
                />
            </Head>

            <section className="shell catalog-intro">
                <p className="eyebrow">Stock disponible</p>
                <h1>Catálogo</h1>
                <p>Precios en dólares. Consultá financiación por WhatsApp.</p>
            </section>

            <section className="shell" style={{ paddingBlock: '22px 0' }}>
                <div className="filters">
                    <label>
                        <span className="field-label">Marca</span>
                        <select
                            value={filtros.marca}
                            onChange={(e) => set('marca', e.target.value)}
                        >
                            {opciones.marcas.map((marca) => (
                                <option key={marca} value={marca}>
                                    {marca}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label>
                        <span className="field-label">Tipo</span>
                        <select
                            value={filtros.tipo}
                            onChange={(e) => set('tipo', e.target.value)}
                        >
                            {opciones.tipos.map((tipo) => (
                                <option key={tipo} value={tipo}>
                                    {tipo}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label>
                        <span className="field-label">Combustible</span>
                        <select
                            value={filtros.comb}
                            onChange={(e) => set('comb', e.target.value)}
                        >
                            {opciones.combustibles.map((comb) => (
                                <option key={comb} value={comb}>
                                    {comb}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label>
                        <span className="field-label">Año desde</span>
                        <select
                            value={filtros.anio}
                            onChange={(e) => set('anio', e.target.value)}
                        >
                            {opciones.anios.map((anio) => (
                                <option key={anio} value={anio}>
                                    {anio}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label className="is-wide">
                        <span className="field-label">
                            Precio hasta {fmtPrecio(filtros.precio, 'USD')}
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

                    <label>
                        <span className="field-label">Ordenar por</span>
                        <select
                            value={filtros.orden}
                            onChange={(e) =>
                                set('orden', e.target.value as Orden)
                            }
                        >
                            {ORDENES.map((orden) => (
                                <option key={orden.value} value={orden.value}>
                                    {orden.label}
                                </option>
                            ))}
                        </select>
                    </label>
                </div>

                <div className="filters-summary">
                    <span className="filters-summary__count" aria-live="polite">
                        {lista.length === 0
                            ? 'Sin vehículos'
                            : lista.length <= POR_PAGINA
                              ? `${lista.length} ${lista.length === 1 ? 'vehículo' : 'vehículos'}`
                              : `${desde + 1}–${desde + visibles.length} de ${lista.length} vehículos`}
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
                        <VehicleGrid vehiculos={visibles} />

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
                            Probá ampliar el rango de precio o quitar algún
                            filtro.
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
