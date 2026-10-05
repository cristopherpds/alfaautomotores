import { Link, usePage } from '@inertiajs/react';
import { IconoWhatsapp } from '@/components/alfa/boton-whatsapp';
import { BrandLockup } from '@/components/alfa/brand';
import { whatsapp } from '@/lib/alfa';
import { dashboard, login, politicaPrivacidad } from '@/routes';
import type { SiteInfo } from '@/types';

/**
 * Íconos de Font Awesome Free 6 (`fab fa-instagram`, `far fa-envelope`),
 * CC BY 4.0 — https://fontawesome.com/license/free. Mismo origen que
 * `IconoWhatsapp`, para que los tres de la columna Contacto hagan juego.
 */
function IconoInstagram({ className }: { className?: string }) {
    return (
        <svg
            className={className}
            viewBox="0 0 448 512"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
            focusable="false"
        >
            <path
                fill="currentColor"
                d="M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37-2.1-147.9-2.1-184.9 0-35.8 1.7-67.6 9.9-93.9 36.1s-34.4 58-36.2 93.9c-2.1 37-2.1 147.9 0 184.9 1.7 35.9 9.9 67.7 36.2 93.9s58 34.4 93.9 36.2c37 2.1 147.9 2.1 184.9 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37 2.1-147.8 0-184.8zM398.8 388c-7.8 19.6-22.9 34.7-42.6 42.6-29.5 11.7-99.5 9-132.1 9s-102.7 2.6-132.1-9c-19.6-7.8-34.7-22.9-42.6-42.6-11.7-29.5-9-99.5-9-132.1s-2.6-102.7 9-132.1c7.8-19.6 22.9-34.7 42.6-42.6 29.5-11.7 99.5-9 132.1-9s102.7-2.6 132.1 9c19.6 7.8 34.7 22.9 42.6 42.6 11.7 29.5 9 99.5 9 132.1s2.7 102.7-9 132.1z"
            />
        </svg>
    );
}

function IconoEmail({ className }: { className?: string }) {
    return (
        <svg
            className={className}
            viewBox="0 0 512 512"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
            focusable="false"
        >
            <path
                fill="currentColor"
                d="M64 112c-8.8 0-16 7.2-16 16v22.1L220.5 291.7c20.7 17 50.4 17 71.1 0L464 150.1V128c0-8.8-7.2-16-16-16H64zM48 212.2V384c0 8.8 7.2 16 16 16H448c8.8 0 16-7.2 16-16V212.2L322 328.8c-38.4 31.5-93.7 31.5-132 0L48 212.2zM0 128C0 92.7 28.7 64 64 64H448c35.3 0 64 28.7 64 64V384c0 35.3-28.7 64-64 64H64c-35.3 0-64-28.7-64-64V128z"
            />
        </svg>
    );
}

export function SiteFooter({ site }: { site: SiteInfo }) {
    const { auth } = usePage().props;

    return (
        <footer className="footer">
            <div className="shell footer__grid">
                <div>
                    <div className="brand" style={{ marginBottom: 16 }}>
                        <BrandLockup size={40} compact />
                    </div>

                    <p className="footer__blurb">
                        Servicio automotivo en {site.ciudad}. Compra, venta y
                        financiación de vehículos.
                    </p>
                </div>

                <div>
                    <h2 className="footer__heading">Local</h2>
                    <address
                        className="footer__body"
                        style={{ fontStyle: 'normal' }}
                    >
                        {site.direccion}
                        <br />
                        {site.ciudad}, {site.pais} {site.codigoPostal}
                    </address>
                </div>

                <div>
                    <h2 className="footer__heading">Horarios</h2>
                    <p className="footer__body">
                        Lunes a viernes
                        <br />
                        {site.horarios.semana}
                        <br />
                        Sábados
                        <br />
                        {site.horarios.sabado}
                    </p>
                </div>

                <div>
                    <h2 className="footer__heading">Contacto</h2>
                    <div className="footer__links">
                        <a
                            href={whatsapp(site.whatsapp)}
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="WhatsApp (se abre en una pestaña nueva)"
                        >
                            <IconoWhatsapp className="footer__icono" />
                            WhatsApp
                        </a>
                        <a
                            href={site.instagram}
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="Instagram (se abre en una pestaña nueva)"
                        >
                            <IconoInstagram className="footer__icono" />
                            Instagram
                        </a>
                        <a href={`mailto:${site.email}`}>
                            <IconoEmail className="footer__icono" />
                            {site.email}
                        </a>
                    </div>
                </div>
            </div>

            <div className="footer__legal">
                <div className="shell footer__legal-inner">
                    <span>
                        © {new Date().getFullYear()} {site.nombre}
                    </span>
                    <span>
                        Precios en dólares, sujetos a cambio sin previo aviso
                    </span>
                    <Link href={politicaPrivacidad()}>
                        Política de privacidad
                    </Link>
                    <Link
                        href={auth.user ? dashboard() : login()}
                        className="footer__panel"
                    >
                        Panel
                    </Link>
                </div>
            </div>
        </footer>
    );
}
