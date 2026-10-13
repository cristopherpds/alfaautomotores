import { Form, Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { politicaPrivacidad } from '@/routes';
import { store } from '@/routes/cotizacion';
import type { OpcionSelect, SiteInfo } from '@/types';

type Props = {
    site: SiteInfo;
    anios: number[];
    fotos: OpcionSelect[];
};

/** Sugerencias para el campo de marca: se puede escribir cualquier otra. */
const MARCAS = [
    'Audi',
    'BMW',
    'BYD',
    'Chery',
    'Chevrolet',
    'Citroën',
    'Fiat',
    'Ford',
    'Geely',
    'Honda',
    'Hyundai',
    'Jeep',
    'Kia',
    'Mercedes-Benz',
    'Mitsubishi',
    'Nissan',
    'Peugeot',
    'Renault',
    'Suzuki',
    'Toyota',
    'Volkswagen',
];

/**
 * «Cotizá tu auto»: el formulario para ofrecer un usado. La solicitud se
 * guarda con sus fotos y la atiende el equipo desde el panel.
 */
export default function CotizaTuAuto({ site, anios, fotos }: Props) {
    /* Las vistas previas de las fotos elegidas, por lado. Las URLs de objeto
       se liberan al reemplazarlas y al salir de la página. */
    const [previas, setPrevias] = useState<Record<string, string>>({});
    const previasRef = useRef(previas);

    useEffect(() => {
        previasRef.current = previas;
    }, [previas]);

    useEffect(
        () => () =>
            Object.values(previasRef.current).forEach((url) =>
                URL.revokeObjectURL(url),
            ),
        [],
    );

    const elegirFoto = (lado: string, archivo: File | undefined) => {
        const siguientes = { ...previas };

        if (previas[lado]) {
            URL.revokeObjectURL(previas[lado]);
        }

        if (archivo) {
            siguientes[lado] = URL.createObjectURL(archivo);
        } else {
            delete siguientes[lado];
        }

        setPrevias(siguientes);
    };

    const limpiarFotos = () => {
        Object.values(previas).forEach((url) => URL.revokeObjectURL(url));
        setPrevias({});
    };

    return (
        <>
            <Head title="Cotizá tu auto">
                <meta
                    name="description"
                    content={`Cotizá tu auto usado en ${site.nombre}, ${site.ciudad}. Completá los datos y un asesor te contacta con la cotización. Recibimos vehículos de todas las marcas.`}
                />
            </Head>

            <section className="shell catalog-intro">
                <p className="eyebrow">Tasación sin cargo</p>
                <h1>Cotizá tu auto de forma rápida, fácil y segura</h1>
                <p>
                    Completá el formulario para que hagamos el estudio previo y
                    un asesor te contacta con la cotización. Recibimos usados de
                    todas las marcas.
                </p>
            </section>

            <section className="turno cotizacion">
                <div className="shell">
                    <Form
                        {...store.form()}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        onSuccess={limpiarFotos}
                        className="turno__form"
                    >
                        {({ processing, errors }) => (
                            <>
                                <fieldset className="cotizacion__bloque">
                                    <legend>Datos del vehículo</legend>

                                    <div className="turno__grid">
                                        <Campo
                                            id="marca"
                                            label="Marca"
                                            error={errors.marca}
                                        >
                                            <input
                                                id="marca"
                                                name="marca"
                                                className="turno__input"
                                                list="marcas"
                                                autoComplete="off"
                                                placeholder="Toyota"
                                                maxLength={60}
                                                required
                                            />
                                            <datalist id="marcas">
                                                {MARCAS.map((marca) => (
                                                    <option
                                                        key={marca}
                                                        value={marca}
                                                    />
                                                ))}
                                            </datalist>
                                        </Campo>

                                        <Campo
                                            id="modelo"
                                            label="Modelo"
                                            error={errors.modelo}
                                        >
                                            <input
                                                id="modelo"
                                                name="modelo"
                                                className="turno__input"
                                                placeholder="Corolla XEi"
                                                maxLength={60}
                                                required
                                            />
                                        </Campo>

                                        <Campo
                                            id="anio"
                                            label="Año"
                                            error={errors.anio}
                                        >
                                            <select
                                                id="anio"
                                                name="anio"
                                                className="turno__input"
                                                defaultValue=""
                                                required
                                            >
                                                <option value="">
                                                    Elegí el año
                                                </option>
                                                {anios.map((anio) => (
                                                    <option
                                                        key={anio}
                                                        value={anio}
                                                    >
                                                        {anio}
                                                    </option>
                                                ))}
                                            </select>
                                        </Campo>

                                        <Campo
                                            id="kilometros"
                                            label="Kilómetros"
                                            error={errors.kilometros}
                                        >
                                            <input
                                                id="kilometros"
                                                name="kilometros"
                                                type="number"
                                                inputMode="numeric"
                                                min={0}
                                                className="turno__input"
                                                placeholder="85000"
                                                required
                                            />
                                        </Campo>
                                    </div>

                                    <div className="turno__field">
                                        <span className="field-label">
                                            Fotos (opcional)
                                        </span>
                                        <p className="cotizacion__ayuda">
                                            Con buena luz y el auto entero en
                                            cuadro. JPG, PNG o WebP de hasta 4
                                            MB cada una.
                                        </p>

                                        <div className="cotizacion__fotos">
                                            {fotos.map((foto) => {
                                                const campo = `foto_${foto.value}`;
                                                const previa =
                                                    previas[foto.value];
                                                const error = errors[campo];

                                                return (
                                                    <div
                                                        key={foto.value}
                                                        className="cotizacion__foto"
                                                    >
                                                        <label
                                                            className="cotizacion__casilla"
                                                            data-con-foto={
                                                                previa
                                                                    ? true
                                                                    : undefined
                                                            }
                                                        >
                                                            <input
                                                                type="file"
                                                                name={campo}
                                                                accept="image/jpeg,image/png,image/webp"
                                                                className="cotizacion__archivo"
                                                                onChange={(
                                                                    evento,
                                                                ) =>
                                                                    elegirFoto(
                                                                        foto.value,
                                                                        evento
                                                                            .target
                                                                            .files?.[0],
                                                                    )
                                                                }
                                                            />
                                                            {previa ? (
                                                                <img
                                                                    src={previa}
                                                                    alt=""
                                                                />
                                                            ) : (
                                                                <span
                                                                    className="cotizacion__mas"
                                                                    aria-hidden="true"
                                                                >
                                                                    +
                                                                </span>
                                                            )}
                                                            <span className="cotizacion__lado">
                                                                {foto.label}
                                                            </span>
                                                        </label>
                                                        {error && (
                                                            <p className="turno__error">
                                                                {error}
                                                            </p>
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>

                                    <Campo
                                        id="detalles"
                                        label="Detalles (opcional)"
                                        error={errors.detalles}
                                    >
                                        <textarea
                                            id="detalles"
                                            name="detalles"
                                            className="turno__input"
                                            rows={3}
                                            maxLength={1000}
                                            placeholder="Único dueño, service al día, un raspón en el paragolpes…"
                                        />
                                    </Campo>
                                </fieldset>

                                <fieldset className="cotizacion__bloque">
                                    <legend>Tus datos</legend>

                                    <div className="turno__grid">
                                        <Campo
                                            id="nombre"
                                            label="Nombre"
                                            error={errors.nombre}
                                        >
                                            <input
                                                id="nombre"
                                                name="nombre"
                                                className="turno__input"
                                                autoComplete="given-name"
                                                maxLength={80}
                                                required
                                            />
                                        </Campo>

                                        <Campo
                                            id="apellido"
                                            label="Apellido"
                                            error={errors.apellido}
                                        >
                                            <input
                                                id="apellido"
                                                name="apellido"
                                                className="turno__input"
                                                autoComplete="family-name"
                                                maxLength={80}
                                                required
                                            />
                                        </Campo>

                                        <Campo
                                            id="celular"
                                            label="Teléfono"
                                            error={errors.celular}
                                        >
                                            <input
                                                id="celular"
                                                name="celular"
                                                type="tel"
                                                inputMode="tel"
                                                className="turno__input"
                                                autoComplete="tel"
                                                placeholder="099 123 456"
                                                maxLength={25}
                                                required
                                            />
                                        </Campo>
                                    </div>
                                </fieldset>

                                <div className="turno__enviar">
                                    <button
                                        type="submit"
                                        className="btn"
                                        disabled={processing}
                                        data-test="enviar-cotizacion-button"
                                    >
                                        {processing
                                            ? 'Enviando…'
                                            : 'Pedir cotización'}
                                    </button>

                                    <p className="turno__nota">
                                        Sin costo ni compromiso. La oferta final
                                        se confirma tras la inspección en el
                                        local. Al enviar aceptás nuestra{' '}
                                        <a
                                            href={politicaPrivacidad().url}
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            Política de privacidad
                                        </a>
                                        .
                                    </p>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </section>
        </>
    );
}

/** Etiqueta, control y error de un campo del formulario. */
function Campo({
    id,
    label,
    error,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="turno__field">
            <label className="field-label" htmlFor={id}>
                {label}
            </label>
            {children}
            {error && <p className="turno__error">{error}</p>}
        </div>
    );
}
