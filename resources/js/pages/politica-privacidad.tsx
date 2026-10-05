import { Head } from '@inertiajs/react';
import { whatsapp } from '@/lib/alfa';
import type { SiteInfo } from '@/types';

/**
 * Política de privacidad del sitio público, en los términos de la Ley 18.331
 * de Protección de Datos Personales y su Decreto 414/009.
 *
 * Los datos que lista son los que piden los formularios de turno (taller y
 * lavadero, ver `TurnoPublicoRequest`): si se suma un campo o un formulario
 * nuevo, este texto tiene que acompañarlo.
 */
const ACTUALIZADA = '4 de octubre de 2026';

export default function PoliticaPrivacidad({ site }: { site: SiteInfo }) {
    return (
        <>
            <Head title="Política de privacidad">
                <meta
                    name="description"
                    content={`Cómo ${site.nombre} recoge, usa y protege los datos personales que nos dejás al reservar un turno o contactarnos.`}
                />
            </Head>

            <section className="shell catalog-intro">
                <p className="eyebrow">Legal</p>
                <h1>Política de privacidad</h1>
                <p>Última actualización: {ACTUALIZADA}</p>
            </section>

            <article className="shell legal">
                <p>
                    En {site.nombre} cuidamos la información que nos confiás.
                    Esta política explica qué datos personales recogemos a
                    través de este sitio, para qué los usamos y qué derechos
                    tenés sobre ellos, conforme a la Ley N.º 18.331 de
                    Protección de Datos Personales y su Decreto reglamentario
                    N.º 414/009.
                </p>

                <h2>1. Responsable de los datos</h2>
                <p>
                    El responsable de la base de datos es {site.nombre}, con
                    domicilio en {site.direccion}, {site.ciudad}, {site.pais}.
                    Por cualquier consulta podés escribirnos a{' '}
                    <a href={`mailto:${site.email}`}>{site.email}</a>.
                </p>

                <h2>2. Qué datos recogemos</h2>
                <p>
                    Llamamos «información personal» a todo dato que te
                    identifique o permita contactarte. Recogemos solo la que nos
                    das voluntariamente:
                </p>
                <ul>
                    <li>
                        Al reservar un turno en el taller o el lavadero: nombre,
                        apellido, email, celular, marca, modelo y año del
                        vehículo, matrícula y el comentario que quieras agregar.
                    </li>
                    <li>
                        Si tildás la casilla de novedades, tu consentimiento
                        para recibirlas y la fecha en que lo diste.
                    </li>
                    <li>
                        Cuando nos escribís por WhatsApp, Instagram o email, los
                        datos que incluyas en el mensaje.
                    </li>
                </ul>

                <h2>3. Para qué los usamos</h2>
                <ul>
                    <li>
                        Agendar, confirmar y gestionar tu turno, y enviarte el
                        presupuesto.
                    </li>
                    <li>
                        Responder tus consultas sobre vehículos, productos y
                        servicios.
                    </li>
                    <li>
                        Llevar el historial de los trabajos hechos a tu vehículo
                        y mejorar nuestra atención.
                    </li>
                    <li>
                        Enviarte novedades, promociones y recordatorios por
                        WhatsApp o email, <strong>solo</strong> si lo aceptaste
                        expresamente. Podés darte de baja cuando quieras.
                    </li>
                </ul>

                <h2>4. Seguridad</h2>
                <p>
                    El sitio transmite la información cifrada (HTTPS) y los
                    datos se guardan en sistemas a los que solo accede personal
                    autorizado de {site.nombre} con usuario y contraseña. Aun
                    así, ningún método de transmisión o almacenamiento por
                    Internet es 100 % seguro, por lo que no podemos garantizar
                    una seguridad absoluta.
                </p>

                <h2>5. Con quién los compartimos</h2>
                <p>
                    <strong>No vendemos ni cedemos</strong> tus datos a otras
                    empresas para fines comerciales. Solo pueden acceder a
                    ellos:
                </p>
                <ul>
                    <li>
                        Proveedores que nos prestan servicios (alojamiento del
                        sitio, mensajería), obligados a protegerlos y a usarlos
                        únicamente para ese fin.
                    </li>
                    <li>
                        Autoridades, cuando una ley u orden judicial lo
                        requiera.
                    </li>
                </ul>

                <h2>6. Cuánto tiempo los guardamos</h2>
                <p>
                    Conservamos tus datos mientras sean necesarios para las
                    finalidades indicadas o mientras exista una relación
                    comercial, y luego por los plazos que exijan las normas
                    vigentes. Cumplido eso, los eliminamos.
                </p>

                <h2>7. Cookies</h2>
                <p>
                    Usamos únicamente cookies técnicas, imprescindibles para que
                    el sitio funcione: mantener tu sesión y proteger los
                    formularios contra envíos fraudulentos. No usamos cookies de
                    publicidad ni de seguimiento. Podés bloquearlas desde tu
                    navegador, pero algunos formularios podrían dejar de
                    funcionar.
                </p>

                <h2>8. Tus derechos</h2>
                <p>
                    Como titular de los datos, podés en cualquier momento y sin
                    costo ejercer tus derechos de{' '}
                    <strong>
                        acceso, rectificación, actualización, inclusión y
                        supresión
                    </strong>
                    , y retirar el consentimiento para recibir novedades. Para
                    hacerlo, escribinos a{' '}
                    <a href={`mailto:${site.email}`}>{site.email}</a>, por{' '}
                    <a
                        href={whatsapp(site.whatsapp)}
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        WhatsApp
                    </a>{' '}
                    o acercate al local. Te respondemos dentro de los plazos que
                    fija la ley.
                </p>
                <p>
                    Si considerás que no atendimos tu pedido, podés presentar un
                    reclamo ante la{' '}
                    <a
                        href="https://www.gub.uy/unidad-reguladora-control-datos-personales/"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Unidad Reguladora y de Control de Datos Personales
                    </a>{' '}
                    (URCDP).
                </p>

                <h2>9. Cambios a esta política</h2>
                <p>
                    Podemos actualizar esta política para reflejar cambios en el
                    sitio o en la normativa. La versión vigente es siempre la
                    publicada en esta página, con su fecha de última
                    actualización.
                </p>

                <h2>10. Contacto</h2>
                <p>
                    {site.nombre}
                    <br />
                    {site.direccion}, {site.ciudad}, {site.pais}{' '}
                    {site.codigoPostal}
                    <br />
                    <a href={`mailto:${site.email}`}>{site.email}</a>
                </p>
            </article>
        </>
    );
}
