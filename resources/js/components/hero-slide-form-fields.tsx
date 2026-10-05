import {
    ArrowDown,
    ArrowDownLeft,
    ArrowDownRight,
    ArrowLeft,
    ArrowRight,
    ArrowUp,
    ArrowUpLeft,
    ArrowUpRight,
    Circle,
    Image,
    Video,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { Campo } from '@/components/form-campo';
import { HeroVistaPrevia } from '@/components/hero-vista-previa';
import InputError from '@/components/input-error';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type {
    HeroBoton,
    HeroSlide,
    ManagedHeroSlide,
    PosicionTexto,
} from '@/types';

type TipoFondo = HeroSlide['tipoFondo'];

type Props = {
    errors: Partial<Record<string, string>>;
    slide?: ManagedHeroSlide;
    /** Los botones de guardar y cancelar, al pie de la columna del formulario. */
    acciones: ReactNode;
};

const ACEPTA: Record<TipoFondo, string> = {
    imagen: 'image/jpeg,image/png,image/webp',
    video: 'video/mp4,video/webm',
};

const AYUDA_FONDO: Record<TipoFondo, string> = {
    imagen: 'JPG, PNG o WebP, hasta 4 MB. Horizontal y de al menos 1920 px de ancho; en móvil se recorta a lo alto, así que lo importante va al centro.',
    video: 'MP4 o WebM, hasta 20 MB y sin sonido (se reproduce silenciado y en bucle). Unos 10 a 20 segundos alcanzan.',
};

/** Las nueve casillas, en el orden en que se leen: de arriba a abajo. */
const POSICIONES: { valor: PosicionTexto; label: string; icono: LucideIcon }[] =
    [
        {
            valor: 'arriba-izquierda',
            label: 'Arriba a la izquierda',
            icono: ArrowUpLeft,
        },
        { valor: 'arriba-centro', label: 'Arriba al centro', icono: ArrowUp },
        {
            valor: 'arriba-derecha',
            label: 'Arriba a la derecha',
            icono: ArrowUpRight,
        },
        {
            valor: 'centro-izquierda',
            label: 'Al medio a la izquierda',
            icono: ArrowLeft,
        },
        { valor: 'centro-centro', label: 'Al centro', icono: Circle },
        {
            valor: 'centro-derecha',
            label: 'Al medio a la derecha',
            icono: ArrowRight,
        },
        {
            valor: 'abajo-izquierda',
            label: 'Abajo a la izquierda',
            icono: ArrowDownLeft,
        },
        { valor: 'abajo-centro', label: 'Abajo al centro', icono: ArrowDown },
        {
            valor: 'abajo-derecha',
            label: 'Abajo a la derecha',
            icono: ArrowDownRight,
        },
    ];

/**
 * Los campos de un slide del hero, con su vista previa en vivo al costado.
 *
 * Los inputs llevan `name` y viajan con el `<Form>` de la página; el estado
 * local existe sólo para alimentar la vista previa, que usa el mismo
 * componente que la portada.
 */
export default function HeroSlideFormFields({
    errors,
    slide,
    acciones,
}: Props) {
    const [tipoFondo, setTipoFondo] = useState<TipoFondo>(
        slide?.tipoFondo ?? 'imagen',
    );
    const [archivoLocal, setArchivoLocal] = useState<string | null>(null);
    const [eyebrow, setEyebrow] = useState(slide?.eyebrow ?? '');
    const [titulo, setTitulo] = useState(slide?.titulo ?? '');
    const [bajada, setBajada] = useState(slide?.bajada ?? '');
    const [posicion, setPosicion] = useState<PosicionTexto>(
        slide?.posicion ?? 'abajo-izquierda',
    );
    const [boton1, setBoton1] = useState<HeroBoton>({
        texto: slide?.boton1_texto ?? '',
        url: slide?.boton1_url ?? '',
    });
    const [boton2, setBoton2] = useState<HeroBoton>({
        texto: slide?.boton2_texto ?? '',
        url: slide?.boton2_url ?? '',
    });
    const [activo, setActivo] = useState(slide?.activo ?? true);

    /* La URL temporal del archivo elegido se libera al cambiarlo o al salir. */
    useEffect(
        () => () => {
            if (archivoLocal) {
                URL.revokeObjectURL(archivoLocal);
            }
        },
        [archivoLocal],
    );

    /* Al cambiar de tipo, el fondo guardado deja de valer: hay que subir uno
       nuevo (lo exige también `HeroSlideRequest`). */
    const fondoGuardado =
        slide && slide.tipoFondo === tipoFondo ? slide.fondo : '';

    const vistaPrevia: HeroSlide = {
        id: slide?.id ?? null,
        tipoFondo,
        fondo: archivoLocal ?? fondoGuardado,
        eyebrow: eyebrow.trim() || null,
        titulo: titulo.trim() || 'El título del slide',
        bajada: bajada.trim() || null,
        posicion,
        botones: [boton1, boton2].filter(
            (boton) => boton.texto.trim() && boton.url.trim(),
        ),
    };

    return (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            <div className="space-y-6">
                <div className="grid gap-2">
                    <Label>Fondo</Label>
                    <input type="hidden" name="tipo_fondo" value={tipoFondo} />
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        value={tipoFondo}
                        onValueChange={(valor) => {
                            if (valor) {
                                setTipoFondo(valor as TipoFondo);
                                setArchivoLocal(null);
                            }
                        }}
                        className="justify-start"
                        aria-label="Tipo de fondo"
                    >
                        <ToggleGroupItem value="imagen">
                            <Image />
                            Imagen
                        </ToggleGroupItem>
                        <ToggleGroupItem value="video">
                            <Video />
                            Video
                        </ToggleGroupItem>
                    </ToggleGroup>
                </div>

                <Campo
                    name="fondo"
                    label={tipoFondo === 'video' ? 'Video' : 'Imagen'}
                    error={errors.fondo ?? errors.tipo_fondo}
                    ayuda={
                        slide && fondoGuardado
                            ? `${AYUDA_FONDO[tipoFondo]} Dejalo vacío para conservar el actual.`
                            : AYUDA_FONDO[tipoFondo]
                    }
                >
                    <Input
                        key={tipoFondo}
                        id="fondo"
                        name="fondo"
                        type="file"
                        accept={ACEPTA[tipoFondo]}
                        required={!fondoGuardado}
                        onChange={(evento) => {
                            const archivo = evento.target.files?.[0];
                            setArchivoLocal(
                                archivo ? URL.createObjectURL(archivo) : null,
                            );
                        }}
                    />
                </Campo>

                <Campo
                    name="eyebrow"
                    label="Antetítulo (opcional)"
                    error={errors.eyebrow}
                    ayuda="La línea chica en mayúsculas sobre el título."
                >
                    <Input
                        id="eyebrow"
                        name="eyebrow"
                        maxLength={60}
                        value={eyebrow}
                        onChange={(evento) => setEyebrow(evento.target.value)}
                        placeholder="Rivera · Uruguay"
                    />
                </Campo>

                <Campo
                    name="titulo"
                    label="Título"
                    error={errors.titulo}
                    ayuda="Cada salto de línea se respeta en el sitio. Corto se lee mejor: dos o tres renglones."
                >
                    <Textarea
                        id="titulo"
                        name="titulo"
                        rows={3}
                        maxLength={120}
                        value={titulo}
                        onChange={(evento) => setTitulo(evento.target.value)}
                        required
                    />
                </Campo>

                <Campo
                    name="bajada"
                    label="Bajada (opcional)"
                    error={errors.bajada}
                >
                    <Textarea
                        id="bajada"
                        name="bajada"
                        rows={2}
                        maxLength={240}
                        value={bajada}
                        onChange={(evento) => setBajada(evento.target.value)}
                    />
                </Campo>

                <div className="grid gap-2">
                    <Label id="posicion-label">Posición del texto</Label>
                    <input type="hidden" name="posicion" value={posicion} />
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        value={posicion}
                        onValueChange={(valor) =>
                            valor && setPosicion(valor as PosicionTexto)
                        }
                        className="grid w-fit grid-cols-3"
                        aria-labelledby="posicion-label"
                    >
                        {POSICIONES.map(({ valor, label, icono: Icono }) => (
                            <ToggleGroupItem
                                key={valor}
                                value={valor}
                                aria-label={label}
                                title={label}
                                className="size-10"
                            >
                                <Icono />
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                    <p className="text-xs text-muted-foreground">
                        Dónde van el título, la bajada y los botones sobre el
                        fondo. Elegí la zona que tape lo menos importante de la
                        foto.
                    </p>
                    <InputError message={errors.posicion} />
                </div>

                {(
                    [
                        [1, boton1, setBoton1, 'Botón principal'],
                        [2, boton2, setBoton2, 'Botón secundario'],
                    ] as const
                ).map(([numero, boton, setBoton, leyenda]) => (
                    <fieldset
                        key={numero}
                        className="grid gap-4 rounded-lg border p-4 sm:grid-cols-2"
                    >
                        <legend className="px-1 text-sm font-medium">
                            {leyenda} (opcional)
                        </legend>

                        <Campo
                            name={`boton${numero}_texto`}
                            label="Texto"
                            error={errors[`boton${numero}_texto`]}
                        >
                            <Input
                                id={`boton${numero}_texto`}
                                name={`boton${numero}_texto`}
                                maxLength={40}
                                value={boton.texto}
                                onChange={(evento) =>
                                    setBoton({
                                        ...boton,
                                        texto: evento.target.value,
                                    })
                                }
                                placeholder={
                                    numero === 1 ? 'Ver catálogo' : 'Consultar'
                                }
                            />
                        </Campo>

                        <Campo
                            name={`boton${numero}_url`}
                            label="Destino"
                            error={errors[`boton${numero}_url`]}
                            ayuda="Una ruta del sitio (/catalogo) o una dirección completa (https://wa.me/…)."
                        >
                            <Input
                                id={`boton${numero}_url`}
                                name={`boton${numero}_url`}
                                value={boton.url}
                                onChange={(evento) =>
                                    setBoton({
                                        ...boton,
                                        url: evento.target.value,
                                    })
                                }
                                placeholder="/catalogo"
                            />
                        </Campo>
                    </fieldset>
                ))}

                <div className="grid gap-4 sm:grid-cols-3">
                    <Campo
                        name="desde"
                        label="Desde (opcional)"
                        error={errors.desde}
                    >
                        <Input
                            id="desde"
                            name="desde"
                            type="date"
                            defaultValue={slide?.desde ?? ''}
                        />
                    </Campo>

                    <Campo
                        name="hasta"
                        label="Hasta (opcional)"
                        error={errors.hasta}
                    >
                        <Input
                            id="hasta"
                            name="hasta"
                            type="date"
                            defaultValue={slide?.hasta ?? ''}
                        />
                    </Campo>

                    <Campo
                        name="orden"
                        label="Orden"
                        error={errors.orden}
                        ayuda="El menor sale primero."
                    >
                        <Input
                            id="orden"
                            name="orden"
                            type="number"
                            min={0}
                            max={255}
                            defaultValue={slide?.orden ?? 0}
                            required
                        />
                    </Campo>
                </div>

                <div className="flex items-center gap-3 rounded-lg border p-3">
                    <Switch
                        id="activo"
                        name="activo"
                        checked={activo}
                        onCheckedChange={setActivo}
                        value="1"
                    />
                    <Label htmlFor="activo">Visible en la portada</Label>
                </div>

                {acciones}
            </div>

            {/* En celular va primero: abajo de «Guardar» no se veía mientras
                se editaba. En escritorio queda a la derecha y fija. */}
            <Card className="order-first self-start lg:sticky lg:top-4 lg:order-none">
                <CardHeader>
                    <CardTitle>Vista previa</CardTitle>
                    <CardDescription>
                        {activo
                            ? 'Así se ve en la portada. Las fechas, si las cargás, deciden cuándo sale.'
                            : 'Apagado: la portada no lo muestra.'}
                    </CardDescription>
                </CardHeader>
                <CardContent className={activo ? undefined : 'opacity-50'}>
                    <HeroVistaPrevia slide={vistaPrevia} />
                </CardContent>
            </Card>
        </div>
    );
}
