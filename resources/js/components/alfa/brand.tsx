/**
 * Marca de Alfa Automotores.
 *
 * `public/assets/logo-alfa.jpg` es el lockup completo, pero en la cabecera y el
 * pie sólo se usa el isotipo: el texto se compone con la tipografía del sitio
 * para que quede nítido a cualquier tamaño.
 *
 * Geometría del isotipo dentro del archivo: en el lienzo de 1024×1024 el
 * círculo ocupa un cuadrado de 215px con su esquina superior izquierda en
 * (144, 404). Todo el recorte se deriva de ahí, así que si cambia el archivo
 * alcanza con volver a medir esa caja.
 */
const LOGO = { lienzo: 1024, x: 144, y: 404, lado: 215 };

/** Cuánto hay que agrandar la imagen para que el isotipo mida `size`. */
const ESCALA = LOGO.lienzo / LOGO.lado;
const OFFSET_X = LOGO.x / LOGO.lado;
const OFFSET_Y = LOGO.y / LOGO.lado;

const LOGO_SRC = '/assets/logo-alfa.jpg';

export function Brand({ size = 44 }: { size?: number }) {
    return (
        <span className="brand__mark" style={{ width: size, height: size }}>
            <img
                src={LOGO_SRC}
                alt=""
                style={{
                    width: size * ESCALA,
                    height: size * ESCALA,
                    /* El preflight de Tailwind pone `img { max-width: 100% }`,
                       que aplastaría el ancho contra los `size` px del marco y
                       dejaría el recorte deformado. */
                    maxWidth: 'none',
                    display: 'block',
                    margin: `${-size * OFFSET_Y}px 0 0 ${-size * OFFSET_X}px`,
                    /* El logo viene sobre fondo blanco: con `multiply` ese
                       fondo desaparece contra el de la página. */
                    mixBlendMode: 'multiply',
                }}
            />
        </span>
    );
}

/** Isotipo + logotipo, tal como aparece en cabecera y pie. */
export function BrandLockup({
    size = 44,
    compact = false,
}: {
    size?: number;
    compact?: boolean;
}) {
    return (
        <>
            <Brand size={size} />
            <span className="brand__name">
                <strong style={compact ? { fontSize: 17 } : undefined}>
                    ALFA
                </strong>
                <span style={compact ? { fontSize: 8 } : undefined}>
                    AUTOMOTORES
                </span>
            </span>
        </>
    );
}
