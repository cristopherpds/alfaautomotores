import { Plus, X } from 'lucide-react';
import { useState } from 'react';
import { Campo, SelectCampo } from '@/components/form-campo';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { fondoDeMuestra, NOMBRES_DE_COLOR, tonosDe } from '@/lib/productos';
import { aSlug } from '@/lib/slug';
import type { OpcionesProducto, ProductoEditable } from '@/types';

type Errores = Record<string, string>;

/** El slug de ejemplo del placeholder y del preview cuando el campo está vacío. */
const EJEMPLO_SLUG = 'moto-electrica-max-350';

/**
 * Filas que se agregan y se quitan, con id propio para que React no mezcle los
 * valores de los inputs sin controlar al borrar una del medio.
 */
let proximaFila = 0;

function useFilas<T>(iniciales: T[], vacia: T) {
    const nueva = (valor: T) => ({ id: proximaFila++, valor });

    const [filas, setFilas] = useState(() =>
        (iniciales.length > 0 ? iniciales : [vacia]).map(nueva),
    );

    return {
        filas,
        agregar: () => setFilas((previas) => [...previas, nueva(vacia)]),
        quitar: (id: number) =>
            setFilas((previas) => previas.filter((fila) => fila.id !== id)),
        cambiar: (id: number, valor: T) =>
            setFilas((previas) =>
                previas.map((fila) =>
                    fila.id === id ? { ...fila, valor } : fila,
                ),
            ),
    };
}

/** El primer error de cualquier campo que empiece con `prefijo`. */
function errorDe(errors: Errores, prefijo: string): string | undefined {
    return Object.entries(errors).find(
        ([campo]) => campo === prefijo || campo.startsWith(`${prefijo}.`),
    )?.[1];
}

function SpecsEditor({
    specs,
    errors,
}: {
    specs: [string, string][];
    errors: Errores;
}) {
    const { filas, agregar, quitar } = useFilas<[string, string]>(specs, [
        '',
        '',
    ]);

    return (
        <div className="grid gap-2">
            <Label>Ficha técnica</Label>
            <p className="text-xs text-muted-foreground">
                Pares etiqueta / valor, en el orden en que se muestran. Las
                filas vacías se ignoran.
            </p>

            {filas.map(({ id, valor }, indice) => (
                <div key={id} className="flex items-center gap-2">
                    <Input
                        name={`specs[${indice}][0]`}
                        defaultValue={valor[0]}
                        placeholder="Potencia"
                        aria-label={`Etiqueta de la fila ${indice + 1}`}
                        className="sm:max-w-56"
                    />
                    <Input
                        name={`specs[${indice}][1]`}
                        defaultValue={valor[1]}
                        placeholder="350 W"
                        aria-label={`Valor de la fila ${indice + 1}`}
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={() => quitar(id)}
                        aria-label={`Quitar la fila ${indice + 1}`}
                    >
                        <X />
                    </Button>
                </div>
            ))}

            <div>
                <Button type="button" variant="outline" onClick={agregar}>
                    <Plus />
                    Agregar fila
                </Button>
            </div>

            <InputError message={errorDe(errors, 'specs')} />
        </div>
    );
}

/**
 * La misma muestra que dibuja la ficha pública, para ver mientras se escribe
 * si el nombre se reconoce. Vacía y punteada cuando no.
 */
function MuestraDeColor({ color }: { color: string }) {
    const tonos = color.trim() === '' ? null : tonosDe(color);

    return (
        <span
            aria-hidden="true"
            className={
                tonos
                    ? 'size-5 shrink-0 rounded-full border border-input'
                    : 'size-5 shrink-0 rounded-full border border-dashed border-muted-foreground/50'
            }
            style={tonos ? { background: fondoDeMuestra(tonos) } : undefined}
        />
    );
}

function ColoresEditor({
    colores,
    errors,
}: {
    colores: string[];
    errors: Errores;
}) {
    const { filas, agregar, quitar, cambiar } = useFilas<string>(colores, '');

    const sinMuestra = filas
        .map(({ valor }) => valor.trim())
        .filter((valor) => valor !== '' && tonosDe(valor) === null);

    return (
        <div className="grid gap-2">
            <Label>Colores</Label>
            <p className="text-xs text-muted-foreground">
                El nombre es lo que lee el cliente; la muestra se arma sola a
                partir de él. Para combinados, separalos con «/»: Azul/Blanco.
            </p>

            <datalist id="colores-conocidos">
                {NOMBRES_DE_COLOR.map((nombre) => (
                    <option key={nombre} value={nombre} />
                ))}
            </datalist>

            <div className="flex flex-wrap items-center gap-2">
                {filas.map(({ id, valor }, indice) => (
                    <div key={id} className="flex items-center gap-1">
                        <MuestraDeColor color={valor} />
                        <Input
                            name={`colores[${indice}]`}
                            value={valor}
                            onChange={(evento) =>
                                cambiar(id, evento.target.value)
                            }
                            list="colores-conocidos"
                            autoComplete="off"
                            placeholder="Negro"
                            aria-label={`Color ${indice + 1}`}
                            className="w-44"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => quitar(id)}
                            aria-label={`Quitar el color ${indice + 1}`}
                        >
                            <X />
                        </Button>
                    </div>
                ))}

                <Button type="button" variant="outline" onClick={agregar}>
                    <Plus />
                    Agregar color
                </Button>
            </div>

            {sinMuestra.length > 0 && (
                <p className="text-xs text-muted-foreground">
                    Sin muestra: {sinMuestra.join(', ')}. En el sitio se verá
                    sólo el nombre.
                </p>
            )}

            <InputError message={errorDe(errors, 'colores')} />
        </div>
    );
}

type ProductoFormFieldsProps = {
    opciones: OpcionesProducto;
    errors: Errores;
    producto?: ProductoEditable;
    familiaInicial?: string | null;
};

/**
 * Los campos de la ficha de un producto, compartidos por el alta y la edición.
 *
 * Igual que en `vehiculo-form-fields.tsx`: sin controlar, salvo el slug, que
 * se sugiere a partir del nombre hasta que se escribe encima. La ficha técnica
 * y los colores viajan como `specs[i][0|1]` y `colores[i]`; el servidor
 * descarta las filas vacías.
 */
export default function ProductoFormFields({
    opciones,
    errors,
    producto,
    familiaInicial,
}: ProductoFormFieldsProps) {
    const [nombre, setNombre] = useState(producto?.nombre ?? '');
    const [slugEscrito, setSlugEscrito] = useState(producto?.slug ?? '');

    const slug = slugEscrito.trim() === '' ? aSlug(nombre) : slugEscrito;

    return (
        <div className="flex flex-col gap-6">
            <div className="grid gap-4 sm:grid-cols-3">
                <div className="sm:col-span-2">
                    <Campo name="nombre" label="Nombre" error={errors.nombre}>
                        <Input
                            id="nombre"
                            name="nombre"
                            defaultValue={producto?.nombre}
                            onChange={(evento) =>
                                setNombre(evento.target.value)
                            }
                            required
                            autoFocus
                            placeholder="Moto eléctrica MAX 350"
                        />
                    </Campo>
                </div>

                <Campo
                    name="codigo"
                    label="Código del proveedor"
                    error={errors.codigo}
                >
                    <Input
                        id="codigo"
                        name="codigo"
                        defaultValue={producto?.codigo ?? ''}
                        placeholder="ETB-122"
                    />
                </Campo>
            </div>

            <Campo
                name="slug"
                label="Slug"
                error={errors.slug}
                ayuda={`Es la dirección pública del producto: /productos/${slug || EJEMPLO_SLUG}`}
            >
                <Input
                    id="slug"
                    name="slug"
                    value={slug}
                    onChange={(evento) =>
                        setSlugEscrito(evento.target.value.toLowerCase())
                    }
                    required
                    placeholder={EJEMPLO_SLUG}
                />
            </Campo>

            <div className="grid gap-4 sm:grid-cols-2">
                <SelectCampo
                    name="familia"
                    label="Familia"
                    opciones={opciones.familias}
                    defaultValue={
                        producto?.familia ?? familiaInicial ?? undefined
                    }
                    error={errors.familia}
                    placeholder="Elegí la familia"
                />

                <SelectCampo
                    name="estado"
                    label="Estado"
                    opciones={opciones.estados}
                    defaultValue={producto?.estado ?? 'borrador'}
                    error={errors.estado}
                    placeholder="Elegí el estado"
                />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <Campo
                    name="precio"
                    label="Precio"
                    error={errors.precio}
                    ayuda="Vacío, el sitio muestra «Consultar precio»."
                >
                    <Input
                        id="precio"
                        name="precio"
                        type="number"
                        min={0}
                        defaultValue={producto?.precio ?? ''}
                        placeholder="32900"
                    />
                </Campo>

                <SelectCampo
                    name="moneda"
                    label="Moneda"
                    opciones={opciones.monedas}
                    defaultValue={producto?.moneda ?? 'UYU'}
                    error={errors.moneda}
                    placeholder="Elegí la moneda"
                />
            </div>

            <Campo
                name="resumen"
                label="Resumen"
                error={errors.resumen}
                ayuda="La línea de la tarjeta: potencia · velocidad · autonomía."
            >
                <Input
                    id="resumen"
                    name="resumen"
                    defaultValue={producto?.resumen}
                    required
                    placeholder="350 W · 30 km/h · 25–30 km"
                />
            </Campo>

            <Campo name="desc" label="Descripción" error={errors.desc}>
                <Textarea
                    id="desc"
                    name="desc"
                    rows={4}
                    defaultValue={producto?.desc}
                    required
                    placeholder="Moto eléctrica urbana con batería extraíble."
                />
            </Campo>

            <SpecsEditor specs={producto?.specs ?? []} errors={errors} />

            <ColoresEditor colores={producto?.colores ?? []} errors={errors} />
        </div>
    );
}
