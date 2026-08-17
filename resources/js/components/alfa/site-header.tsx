import { Link } from '@inertiajs/react';
import { BrandLockup } from '@/components/alfa/brand';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { whatsapp } from '@/lib/alfa';
import { catalogo, home } from '@/routes';
import type { SiteInfo } from '@/types';

export function SiteHeader({ site }: { site: SiteInfo }) {
    const { currentUrl } = useCurrentUrl();

    /* La ficha de un vehículo también cuenta como "estoy en el catálogo". */
    const enCatalogo =
        currentUrl.startsWith('/catalogo') ||
        currentUrl.startsWith('/vehiculos');

    return (
        <header className="header">
            <div className="shell header__inner">
                <Link
                    href={home()}
                    className="brand"
                    aria-label="Alfa Automotores, inicio"
                >
                    <BrandLockup />
                </Link>

                <nav className="nav">
                    <Link
                        href={catalogo()}
                        className="nav__link"
                        data-active={enCatalogo}
                        aria-current={enCatalogo ? 'page' : undefined}
                    >
                        Catálogo
                    </Link>

                    <a
                        href={whatsapp(
                            site.whatsapp,
                            'Hola, quiero cotizar mi auto con Alfa Automotores.',
                        )}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="nav__link"
                    >
                        Cotizá tu auto
                    </a>

                    <a href="#contacto" className="nav__link">
                        Contacto
                    </a>

                    <a
                        href={whatsapp(site.whatsapp)}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="nav__wa"
                    >
                        <span className="dot" />
                        WhatsApp
                    </a>
                </nav>
            </div>
        </header>
    );
}
