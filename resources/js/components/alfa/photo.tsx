type PhotoProps = {
    src?: string | null;
    alt: string;
    /** Línea principal del marcador cuando todavía no hay foto cargada. */
    placeholder: string;
    /** Línea secundaria del marcador: el año del vehículo, o la vista. */
    detalle?: string;
};

/**
 * Foto que ocupa su contenedor, que debe tener `position: relative` y
 * `container-type: inline-size` (el marcador dimensiona su tipografía en `cqw`
 * contra ese contenedor).
 *
 * Sin archivo cargado cae en el marcador del sistema de diseño en vez de dejar
 * un hueco: mantiene la grilla estable y le dice a quien carga el stock qué
 * foto falta.
 */
export function Photo({ src, alt, placeholder, detalle }: PhotoProps) {
    if (!src) {
        /* Una sola etiqueta para las tres líneas: leídas por separado sonarían
           a tres imágenes distintas. */
        const etiqueta = [placeholder, detalle, 'foto pendiente']
            .filter(Boolean)
            .join(' · ');

        return (
            <div className="photo-empty" aria-label={etiqueta} role="img">
                <p className="photo-empty__nombre" aria-hidden="true">
                    {placeholder}
                </p>

                {detalle && (
                    <p className="photo-empty__detalle" aria-hidden="true">
                        {detalle}
                    </p>
                )}

                <p className="photo-empty__estado" aria-hidden="true">
                    Foto pendiente
                </p>
            </div>
        );
    }

    return <img src={src} alt={alt} className="photo" />;
}
