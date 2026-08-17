import { Link, usePage } from '@inertiajs/react';
import { BrandLockup } from '@/components/alfa/brand';
import { whatsapp } from '@/lib/alfa';
import { dashboard, login } from '@/routes';
import type { SiteInfo } from '@/types';

export function SiteFooter({ site }: { site: SiteInfo }) {
    const { auth } = usePage().props;

    return (
        <footer className="footer" id="contacto">
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
                        >
                            WhatsApp
                        </a>
                        <a
                            href={site.instagram}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Instagram
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
