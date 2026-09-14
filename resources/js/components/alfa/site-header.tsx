import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { BrandLockup } from '@/components/alfa/brand';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { whatsapp } from '@/lib/alfa';
import { catalogo, home, taller } from '@/routes';
import type { SiteInfo } from '@/types';

/** El `aria-controls` del botón hamburguesa apunta acá. */
const MENU_ID = 'menu-principal';

/**
 * Cabecera del sitio público.
 *
 * El mismo `<nav>` sirve para las dos formas del header: en escritorio va en
 * línea junto a la marca y por debajo del corte de `alfa.css` el CSS lo
 * convierte en un panel desplegable bajo la cabecera, que abre el botón
 * hamburguesa. El markup no se duplica: `data-abierto` es lo único que cambia.
 */
export function SiteHeader({ site }: { site: SiteInfo }) {
    const { currentUrl } = useCurrentUrl();
    const [menuAbierto, setMenuAbierto] = useState(false);

    /* La ficha de un vehículo también cuenta como "estoy en el catálogo". */
    const enCatalogo =
        currentUrl.startsWith('/catalogo') ||
        currentUrl.startsWith('/vehiculos');

    const enTaller = currentUrl.startsWith('/taller');

    const cerrarMenu = () => setMenuAbierto(false);

    /*
     * Inertia no desmonta el layout al navegar: sin esto el panel seguiría
     * abierto sobre la página nueva —los links lo cierran al tocarlos, pero el
     * botón "atrás" del navegador no pasa por ahí—. Se ajusta en el render y no
     * en un efecto, que es lo que recomienda React para "resetear estado cuando
     * cambia una prop" y evita el repintado intermedio con el menú abierto.
     */
    const [urlDelMenu, setUrlDelMenu] = useState(currentUrl);

    if (urlDelMenu !== currentUrl) {
        setUrlDelMenu(currentUrl);
        setMenuAbierto(false);
    }

    useEffect(() => {
        if (!menuAbierto) {
            return;
        }

        const alPresionarTecla = (evento: KeyboardEvent) => {
            if (evento.key === 'Escape') {
                setMenuAbierto(false);
            }
        };

        document.addEventListener('keydown', alPresionarTecla);

        return () => document.removeEventListener('keydown', alPresionarTecla);
    }, [menuAbierto]);

    return (
        <>
            <header className="header">
                <div className="shell header__inner">
                    <Link
                        href={home()}
                        className="brand"
                        aria-label="Alfa Automotores, inicio"
                    >
                        <BrandLockup />
                    </Link>

                    <button
                        type="button"
                        className="nav__toggle"
                        aria-expanded={menuAbierto}
                        aria-controls={MENU_ID}
                        aria-label={menuAbierto ? 'Cerrar menú' : 'Abrir menú'}
                        onClick={() => setMenuAbierto((abierto) => !abierto)}
                    >
                        <span className="nav__toggle-icon" aria-hidden="true" />
                    </button>

                    {/* Cerrado en móvil el panel queda en `display: none`, así que
                    ni recibe foco ni lo anuncia el lector de pantalla. */}
                    <nav
                        id={MENU_ID}
                        className="nav"
                        data-abierto={menuAbierto}
                    >
                        <Link
                            href={catalogo()}
                            className="nav__link"
                            data-active={enCatalogo}
                            aria-current={enCatalogo ? 'page' : undefined}
                            onClick={cerrarMenu}
                        >
                            Catálogo
                        </Link>

                        <Link
                            href={taller()}
                            className="nav__link"
                            data-active={enTaller}
                            aria-current={enTaller ? 'page' : undefined}
                            onClick={cerrarMenu}
                        >
                            Taller
                        </Link>

                        <a
                            href={whatsapp(
                                site.whatsapp,
                                'Hola, quiero cotizar mi auto con Alfa Automotores.',
                            )}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="nav__link"
                            onClick={cerrarMenu}
                        >
                            Cotizá tu auto
                        </a>

                        <a
                            href="#contacto"
                            className="nav__link"
                            onClick={cerrarMenu}
                        >
                            Contacto
                        </a>

                        <a
                            href={whatsapp(site.whatsapp)}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="nav__wa"
                            onClick={cerrarMenu}
                        >
                            <span className="dot" />
                            WhatsApp
                        </a>
                    </nav>
                </div>
            </header>

            {/* Fuera del `<header>` a propósito: su `backdrop-filter` lo
                convierte en bloque contenedor y un `position: fixed` adentro se
                mediría contra la cabecera en vez de contra la ventana. */}
            {menuAbierto && (
                <button
                    type="button"
                    className="nav__scrim"
                    tabIndex={-1}
                    aria-hidden="true"
                    onClick={cerrarMenu}
                />
            )}
        </>
    );
}
