import { usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import BotonWhatsapp from '@/components/alfa/boton-whatsapp';
import { SiteFooter } from '@/components/alfa/site-footer';
import { SiteHeader } from '@/components/alfa/site-header';
import type { SiteInfo } from '@/types';

/**
 * Chrome del sitio público: cabecera, pie y el contenedor `.alfa` del que
 * cuelga todo el sistema de diseño.
 *
 * Cada página pública sirve `site` desde su controlador (ver
 * `App\Concerns\ProvidesSiteInfo`).
 */
export default function AlfaLayout({ children }: PropsWithChildren) {
    const { site } = usePage<{ site: SiteInfo }>().props;

    return (
        <div
            className={
                site.botonWhatsapp.activo ? 'alfa alfa--con-wpp' : 'alfa'
            }
        >
            <SiteHeader site={site} />

            <main>{children}</main>

            <SiteFooter site={site} />

            {site.botonWhatsapp.activo && (
                <BotonWhatsapp
                    nombre={site.nombre}
                    titulo={site.botonWhatsapp.titulo}
                    subtitulo={site.botonWhatsapp.subtitulo}
                    fueraDeHorario={site.botonWhatsapp.fueraDeHorario}
                    contactos={site.botonWhatsapp.contactos}
                />
            )}
        </div>
    );
}
