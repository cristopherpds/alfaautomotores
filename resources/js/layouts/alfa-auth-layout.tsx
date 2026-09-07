import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { BrandLockup } from '@/components/alfa/brand';
import { home } from '@/routes';

/**
 * Chrome de las pantallas de acceso: el contenedor `.alfa` y el sistema de
 * diseño del sitio público, pero sin cabecera ni pie.
 *
 * La marca va arriba del bloque y enlaza a la portada: es la única salida al
 * sitio que le queda a quien llega acá por error.
 */
export default function AlfaAuthLayout({ children }: PropsWithChildren) {
    return (
        <div className="alfa">
            <main className="auth-page">
                <div className="shell auth auth-page__marca">
                    <Link
                        href={home()}
                        className="brand"
                        aria-label="Alfa Automotores, inicio"
                    >
                        <BrandLockup size={44} />
                    </Link>
                </div>

                {children}
            </main>
        </div>
    );
}
