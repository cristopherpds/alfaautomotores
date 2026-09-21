import { Form, Head, router } from '@inertiajs/react';
import { useMemo, useRef, useState } from 'react';
import { whatsapp } from '@/lib/alfa';
import { store } from '@/routes/taller/turnos';
import type { OpcionSelect, ServicioTaller, SiteInfo } from '@/types';

type Props = {
    site: SiteInfo;
    servicios: ServicioTaller[];
    areas: OpcionSelect[];
    ventana: { desde: string; hasta: string };
    huecos: string[];
};

/** El chip que muestra todos los servicios juntos. */
const TODAS = 'todas';

/**
 * La página del taller.
 *
 * Los horarios libres los calcula el servidor: cada vez que cambia el servicio
 * o el día se pide una recarga parcial de `huecos`. Así la disponibilidad tiene
 * una sola definición —`Puesto::huecosDelDia()`— y el navegador no adivina.
 */
export default function Taller({
    site,
    servicios,
    areas,
    ventana,
    huecos,
}: Props) {
    const [area, setArea] = useState(TODAS);
    const [servicio, setServicio] = useState('');
    const [fecha, setFecha] = useState('');
    const [hora, setHora] = useState('');

    const reserva = useRef<HTMLElement>(null);

    const agendables = useMemo(
        () => servicios.filter((uno) => uno.agendable),
        [servicios],
    );

    const visibles = useMemo(
        () =>
            area === TODAS
                ? servicios
                : servicios.filter((uno) => uno.area === area),
        [servicios, area],
    );

    const elegido = servicios.find((uno) => uno.slug === servicio) ?? null;

    /* Los horarios los calcula el servidor: cada cambio de servicio o de día
       los vuelve a pedir. Se limpia la hora elegida porque puede no existir en
       la combinación nueva. */
    const pedirHuecos = (nuevoServicio: string, nuevaFecha: string) => {
        setHora('');

        if (!nuevoServicio || !nuevaFecha) {
            return;
        }

        router.reload({
            only: ['huecos'],
            data: { servicio: nuevoServicio, fecha: nuevaFecha },
        });
    };

    const elegirServicio = (slug: string) => {
        setServicio(slug);
        pedirHuecos(slug, fecha);
    };

    const elegirFecha = (dia: string) => {
        setFecha(dia);
        pedirHuecos(servicio, dia);
    };

    const agendar = (slug: string) => {
        elegirServicio(slug);
        reserva.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    return (
        <>
            <Head title="Taller mecánico multimarca">
                <meta
                    name="description"
                    content="Service, frenos, motor, diagnóstico y clima en Rivera. Reservá tu turno online y te confirmamos el presupuesto por WhatsApp."
                />
            </Head>

            <section className="taller-hero">
                {/* Fondo decorativo: no aporta contenido, así que va con
                    `aria-hidden` y sin texto alternativo. */}
                <img
                    className="taller-hero__foto"
                    src="/assets/hero-taller.jpg"
                    alt=""
                    aria-hidden="true"
                    fetchPriority="high"
                />
                <div className="taller-hero__velo" aria-hidden="true" />

                <div className="shell taller-hero__inner">
                    <h1>
                        El taller de confianza
                        <br />
                        para tu auto.
                    </h1>

                    <p className="lede">
                        Service, frenos, motor, diagnóstico y clima en{' '}
                        {site.direccion}, {site.ciudad}. Reservás online y te
                        confirmamos el presupuesto por WhatsApp.
                    </p>

                    <div className="hero__actions">
                        <a href="#reservar" className="btn btn--light">
                            Reservar turno
                        </a>
                        <a href="#servicios" className="btn btn--outline-light">
                            Ver servicios
                        </a>
                    </div>
                </div>
            </section>

            <section className="shell section" id="servicios">
                <div className="section__head">
                    <div>
                        <p className="eyebrow">Servicios</p>
                        <h2>Todo lo que resolvemos</h2>
                    </div>
                </div>

                <div className="taller-areas" role="group" aria-label="Áreas">
                    <button
                        type="button"
                        className="taller-areas__chip"
                        data-activo={area === TODAS}
                        onClick={() => setArea(TODAS)}
                    >
                        Todos
                    </button>

                    {areas.map((una) => (
                        <button
                            key={una.value}
                            type="button"
                            className="taller-areas__chip"
                            data-activo={area === una.value}
                            onClick={() => setArea(una.value)}
                        >
                            {una.label}
                        </button>
                    ))}
                </div>

                {visibles.length === 0 ? (
                    <div className="empty">
                        <p className="empty__title">Nada por acá</p>
                        <p className="empty__text">
                            No hay servicios cargados en esa área.
                        </p>
                    </div>
                ) : (
                    <div className="grid">
                        {visibles.map((uno) => (
                            <article
                                className="card card--servicio"
                                key={uno.slug}
                            >
                                <div className="card__media">
                                    {uno.foto ? (
                                        <img
                                            src={uno.foto}
                                            alt={uno.nombre}
                                            className="photo"
                                            loading="lazy"
                                        />
                                    ) : (
                                        <div
                                            className="photo-empty"
                                            role="img"
                                            aria-label={`${uno.nombre} · foto pendiente`}
                                        >
                                            <p
                                                className="photo-empty__nombre"
                                                aria-hidden="true"
                                            >
                                                {uno.nombre}
                                            </p>
                                            <p
                                                className="photo-empty__estado"
                                                aria-hidden="true"
                                            >
                                                Foto pendiente
                                            </p>
                                        </div>
                                    )}
                                </div>

                                <div className="card__body">
                                    <p className="card__meta">
                                        {uno.areaLabel}
                                    </p>
                                    <h3 className="card__title">
                                        {uno.nombre}
                                    </h3>
                                    <p className="card__desc">
                                        {uno.descripcion}
                                    </p>

                                    {/* El pie va dentro del cuerpo, como en la
                                        tarjeta de vehículo: es de ahí que saca
                                        el padding lateral. */}
                                    <div className="card__foot">
                                        <span className="card__duracion">
                                            {uno.duracionLegible}
                                        </span>

                                        {uno.agendable ? (
                                            <button
                                                type="button"
                                                className="card__cta"
                                                onClick={() =>
                                                    agendar(uno.slug)
                                                }
                                                data-test={`agendar-${uno.slug}`}
                                            >
                                                Agendar
                                            </button>
                                        ) : (
                                            <a
                                                className="card__cta"
                                                href={whatsapp(
                                                    site.whatsapp,
                                                    `Hola, quiero consultar por ${uno.nombre}.`,
                                                )}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                Consultar
                                            </a>
                                        )}
                                    </div>
                                </div>
                            </article>
                        ))}
                    </div>
                )}
            </section>

            <section className="turno" id="reservar" ref={reserva}>
                <div className="shell">
                    <div className="section__head">
                        <div>
                            <p className="eyebrow">Reservá tu turno</p>
                            <h2>Elegí el servicio y el horario</h2>
                        </div>
                    </div>

                    <Form
                        {...store.form()}
                        options={{ preserveScroll: true, preserveState: true }}
                        onSuccess={() => setHora('')}
                        className="turno__form"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="turno__grid">
                                    <div className="turno__field">
                                        <label
                                            className="field-label"
                                            htmlFor="servicio"
                                        >
                                            Servicio
                                        </label>

                                        <select
                                            id="servicio"
                                            name="servicio"
                                            className="turno__input"
                                            value={servicio}
                                            onChange={(evento) =>
                                                elegirServicio(
                                                    evento.target.value,
                                                )
                                            }
                                            required
                                        >
                                            <option value="">
                                                Elegí un servicio
                                            </option>
                                            {agendables.map((uno) => (
                                                <option
                                                    key={uno.slug}
                                                    value={uno.slug}
                                                >
                                                    {uno.nombre} ·{' '}
                                                    {uno.duracionLegible}
                                                </option>
                                            ))}
                                        </select>

                                        {errors.servicio && (
                                            <p className="turno__error">
                                                {errors.servicio}
                                            </p>
                                        )}
                                    </div>

                                    <div className="turno__field">
                                        <label
                                            className="field-label"
                                            htmlFor="fecha"
                                        >
                                            Día
                                        </label>

                                        <input
                                            id="fecha"
                                            name="fecha"
                                            type="date"
                                            className="turno__input"
                                            value={fecha}
                                            min={ventana.desde}
                                            max={ventana.hasta}
                                            onChange={(evento) =>
                                                elegirFecha(evento.target.value)
                                            }
                                            required
                                        />

                                        {errors.fecha && (
                                            <p className="turno__error">
                                                {errors.fecha}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div className="turno__field">
                                    <p className="field-label">
                                        Horarios libres
                                    </p>

                                    {!elegido || !fecha ? (
                                        <p className="turno__aviso">
                                            Elegí el servicio y el día para ver
                                            qué horarios quedan.
                                        </p>
                                    ) : huecos.length === 0 ? (
                                        <p className="turno__aviso">
                                            No queda ningún horario ese día.
                                            Probá con otro.
                                        </p>
                                    ) : (
                                        <div className="turno__horarios">
                                            {huecos.map((libre) => (
                                                <button
                                                    key={libre}
                                                    type="button"
                                                    className="turno__hueco"
                                                    aria-pressed={
                                                        hora === libre
                                                    }
                                                    onClick={() =>
                                                        setHora(libre)
                                                    }
                                                    data-test={`hueco-${libre}`}
                                                >
                                                    {libre}
                                                </button>
                                            ))}
                                        </div>
                                    )}

                                    <input
                                        type="hidden"
                                        name="hora"
                                        value={hora}
                                    />

                                    {errors.hora && (
                                        <p className="turno__error">
                                            {errors.hora}
                                        </p>
                                    )}
                                </div>

                                <div className="turno__grid">
                                    <div className="turno__field">
                                        <label
                                            className="field-label"
                                            htmlFor="nombre"
                                        >
                                            Nombre
                                        </label>
                                        <input
                                            id="nombre"
                                            name="nombre"
                                            className="turno__input"
                                            autoComplete="given-name"
                                            required
                                        />
                                        {errors.nombre && (
                                            <p className="turno__error">
                                                {errors.nombre}
                                            </p>
                                        )}
                                    </div>

                                    <div className="turno__field">
                                        <label
                                            className="field-label"
                                            htmlFor="apellido"
                                        >
                                            Apellido
                                        </label>
                                        <input
                                            id="apellido"
                                            name="apellido"
                                            className="turno__input"
                                            autoComplete="family-name"
                                            required
                                        />
                                        {errors.apellido && (
                                            <p className="turno__error">
                                                {errors.apellido}
                                            </p>
                                        )}
                                    </div>

                                    <div className="turno__field">
                                        <label
                                            className="field-label"
                                            htmlFor="email"
                                        >
                                            Correo
                                        </label>
                                        <input
                                            id="email"
                                            name="email"
                                            type="email"
                                            className="turno__input"
                                            autoComplete="email"
                                            required
                                        />
                                        {errors.email && (
                                            <p className="turno__error">
                                                {errors.email}
                                            </p>
                                        )}
                                    </div>

                                    <div className="turno__field">
                                        <label
                                            className="field-label"
                                            htmlFor="celular"
                                        >
                                            Celular
                                        </label>
                                        <input
                                            id="celular"
                                            name="celular"
                                            type="tel"
                                            className="turno__input"
                                            autoComplete="tel"
                                            placeholder="099 000 000"
                                            required
                                        />
                                        {errors.celular && (
                                            <p className="turno__error">
                                                {errors.celular}
                                            </p>
                                        )}
                                    </div>

                                    <div className="turno__field">
                                        <label
                                            className="field-label"
                                            htmlFor="vehiculo_marca"
                                        >
                                            Marca
                                        </label>
                                        <input
                                            id="vehiculo_marca"
                                            name="vehiculo_marca"
                                            className="turno__input"
                                            placeholder="Fiat"
                                        />
                                    </div>

                                    <div className="turno__field">
                                        <label
                                            className="field-label"
                                            htmlFor="vehiculo_modelo"
                                        >
                                            Modelo
                                        </label>
                                        <input
                                            id="vehiculo_modelo"
                                            name="vehiculo_modelo"
                                            className="turno__input"
                                            placeholder="Cronos"
                                        />
                                    </div>

                                    <div className="turno__field">
                                        <label
                                            className="field-label"
                                            htmlFor="vehiculo_anio"
                                        >
                                            Año
                                        </label>
                                        <input
                                            id="vehiculo_anio"
                                            name="vehiculo_anio"
                                            type="number"
                                            inputMode="numeric"
                                            className="turno__input"
                                            placeholder="2019"
                                        />
                                        {errors.vehiculo_anio && (
                                            <p className="turno__error">
                                                {errors.vehiculo_anio}
                                            </p>
                                        )}
                                    </div>

                                    <div className="turno__field">
                                        <label
                                            className="field-label"
                                            htmlFor="matricula"
                                        >
                                            Matrícula
                                        </label>
                                        <input
                                            id="matricula"
                                            name="matricula"
                                            className="turno__input"
                                            placeholder="ABC 1234"
                                        />
                                        {errors.matricula && (
                                            <p className="turno__error">
                                                {errors.matricula}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div className="turno__field">
                                    <label
                                        className="field-label"
                                        htmlFor="comentario"
                                    >
                                        ¿Qué le pasa? (opcional)
                                    </label>
                                    <textarea
                                        id="comentario"
                                        name="comentario"
                                        className="turno__input"
                                        rows={3}
                                        placeholder="Hace un ruido al frenar…"
                                    />
                                    {errors.comentario && (
                                        <p className="turno__error">
                                            {errors.comentario}
                                        </p>
                                    )}
                                </div>

                                <div className="turno__enviar">
                                    <button
                                        type="submit"
                                        className="btn"
                                        disabled={processing || !hora}
                                        data-test="reservar-turno-button"
                                    >
                                        {processing
                                            ? 'Reservando…'
                                            : 'Confirmar turno'}
                                    </button>

                                    <p className="turno__nota">
                                        Sin costo de reserva. Te confirmamos por
                                        WhatsApp con el presupuesto antes de que
                                        traigas el auto.
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
