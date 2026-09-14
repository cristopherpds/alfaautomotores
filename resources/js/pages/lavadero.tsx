import { Form, Head, router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { store } from '@/routes/lavadero/turnos';
import type { ServicioLavadero, SiteInfo } from '@/types';

type Props = {
    site: SiteInfo;
    servicios: ServicioLavadero[];
    ventana: { desde: string; hasta: string };
    huecos: string[];
};

/**
 * La página del lavadero.
 *
 * Es la hermana de `taller.tsx` y comparte con ella el formulario de reserva:
 * los horarios libres los calcula el servidor y cada cambio de servicio o de
 * día pide una recarga parcial de `huecos`, así la disponibilidad tiene una
 * sola definición —`Puesto::huecosDelDia()`— y el navegador no adivina.
 *
 * Lo propio de acá es que los servicios son tamaños de vehículo: no se filtran
 * por área y lo que los distingue es el precio.
 */
export default function Lavadero({ site, servicios, ventana, huecos }: Props) {
    const [servicio, setServicio] = useState('');
    const [fecha, setFecha] = useState('');
    const [hora, setHora] = useState('');

    const reserva = useRef<HTMLElement>(null);

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
            <Head title="Lavadero">
                <meta
                    name="description"
                    content="Lavado exterior y aspirado interior en Rivera. Reservá el horario online y dejá el auto sólo el rato que lleva el trabajo."
                />
            </Head>

            <section className="lavadero-hero">
                <div className="shell lavadero-hero__inner">
                    <div>
                        <h1>
                            Tu auto limpio,
                            <br />
                            sin perder el día.
                        </h1>

                        <p className="lede">
                            Lavado exterior y aspirado interior en{' '}
                            {site.direccion}, {site.ciudad}. Reservás el horario
                            online y lo dejás solamente el rato que lleva el
                            trabajo.
                        </p>

                        <div className="hero__actions">
                            <a href="#reservar" className="btn">
                                Reservar lavado
                            </a>
                            <a href="#precios" className="btn btn--ghost">
                                Ver precios
                            </a>
                        </div>
                    </div>

                    <aside className="lavadero-hero__nota">
                        <p className="eyebrow">Mientras esperás</p>
                        <p>
                            Lo podés esperar acá o dejarlo y seguir con tus
                            cosas: te avisamos por WhatsApp apenas está pronto.
                        </p>
                        <p className="lavadero-hero__horario">
                            {site.horarios.corto}
                        </p>
                    </aside>
                </div>
            </section>

            <section className="shell section" id="precios">
                <div className="section__head">
                    <div>
                        <p className="eyebrow">Precios</p>
                        <h2>Según el tamaño del vehículo</h2>
                    </div>
                </div>

                {servicios.length === 0 ? (
                    <div className="empty">
                        <p className="empty__title">Nada por acá</p>
                        <p className="empty__text">
                            Todavía no hay lavados cargados.
                        </p>
                    </div>
                ) : (
                    <div className="grid--duo grid">
                        {servicios.map((uno) => (
                            <article
                                className="card card--lavado"
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
                                    <h3 className="card__title">
                                        {uno.nombre}
                                    </h3>

                                    {uno.precioLegible && (
                                        <p className="card__precio">
                                            {uno.precioLegible}
                                        </p>
                                    )}

                                    <p className="card__desc">
                                        {uno.descripcion}
                                    </p>

                                    <div className="card__foot">
                                        <span className="card__duracion">
                                            {uno.duracionLegible}
                                        </span>

                                        <button
                                            type="button"
                                            className="card__cta"
                                            onClick={() => agendar(uno.slug)}
                                            data-test={`agendar-${uno.slug}`}
                                        >
                                            Reservar lavado
                                        </button>
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
                            <h2>Elegí el vehículo y el horario</h2>
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
                                            Vehículo
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
                                                Elegí el tamaño
                                            </option>
                                            {servicios.map((uno) => (
                                                <option
                                                    key={uno.slug}
                                                    value={uno.slug}
                                                >
                                                    {uno.nombre}
                                                    {uno.precioLegible
                                                        ? ` · ${uno.precioLegible}`
                                                        : ''}
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
                                            Elegí el vehículo y el día para ver
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
                                        ¿Algo para avisar? (opcional)
                                    </label>
                                    <textarea
                                        id="comentario"
                                        name="comentario"
                                        className="turno__input"
                                        rows={3}
                                        placeholder="Tiene pelo de perro en el baúl…"
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
                                        WhatsApp y se paga al retirar el auto.
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
