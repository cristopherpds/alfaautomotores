import type { ImgHTMLAttributes } from 'react';

import { cn } from '@/lib/utils';

/**
 * Isotipo de Alfa Automotores.
 *
 * `public/assets/isotipo-alfa.png` viene con el fondo transparente, así que el
 * anillo —gris oscuro a negro— se perdería contra el sidebar en modo oscuro:
 * por eso la marca va sobre un disco blanco, que es el fondo con el que está
 * diseñado el logo.
 */
export default function AppLogoIcon({
    className,
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img
            {...props}
            src="/assets/isotipo-alfa.png"
            alt=""
            className={cn(
                'aspect-square rounded-full bg-white object-contain',
                className,
            )}
        />
    );
}
