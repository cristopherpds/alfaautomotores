import { useState } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { OpcionesVehiculo, OpcionSelect, VehiculoEditable } from '@/types';

type Errores = Record<string, string>;

/** El slug de ejemplo del placeholder y del preview cuando el campo está vacío. */
const EJEMPLO_SLUG = 'strada-freedom-24';

/** Texto a slug: sin tildes, en minúsculas y separado por guiones. */
function aSlug(texto: string): string {
    return texto
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

/**
 * El slug que se sugiere solo: modelo, versión y los dos últimos dígitos del
 * año, que es el formato del stock ya cargado (`onix-activ-19`, `cs55-plus-23`).
 * El año se ignora hasta estar completo para no sugerir `strada-freedom-02`
 * mientras se tipea.
 */
function slugSugerido(modelo: string, version: string, anio: string): string {
    const dosDigitos = /^\d{4}$/.test(anio.trim()) ? anio.trim().slice(-2) : '';

    return aSlug([modelo, version, dosDigitos].filter(Boolean).join(' '));
}

type CampoProps = {
    name: string;
    label: string;
    error?: string;
    children: React.ReactNode;
    ayuda?: string;
};

function Campo({ name, label, error, children, ayuda }: CampoProps) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            {children}
            {ayuda && <p className="text-xs text-muted-foreground">{ayuda}</p>}
            <InputError message={error} />
        </div>
    );
}

type SelectCampoProps = {
    name: string;
    label: string;
    opciones: OpcionSelect[];
    defaultValue?: string;
    error?: string;
    placeholder: string;
};

function SelectCampo({
    name,
    label,
    opciones,
    defaultValue,
    error,
    placeholder,
}: SelectCampoProps) {
    return (
        <Campo name={name} label={label} error={error}>
            <Select name={name} defaultValue={defaultValue}>
                <SelectTrigger id={name} className="w-full">
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>

                <SelectContent>
                    <SelectGroup>
                        {opciones.map((opcion) => (
                            <SelectItem key={opcion.value} value={opcion.value}>
                                <span className="flex flex-col items-start">
                                    <span>{opcion.label}</span>
                                    {opcion.description && (
                                        <span className="text-xs text-muted-foreground">
                                            {opcion.description}
                                        </span>
                                    )}
                                </span>
                            </SelectItem>
                        ))}
                    </SelectGroup>
                </SelectContent>
            </Select>
        </Campo>
    );
}

type VehiculoFormFieldsProps = {
    opciones: OpcionesVehiculo;
    errors: Errores;
    vehiculo?: VehiculoEditable;
};

/**
 * Los campos de la ficha, compartidos por el alta y la edición.
 *
 * Van sin controlar (`defaultValue`): el `<Form>` de Inertia serializa el DOM,
 * igual que en `pages/users/*`. El slug es la excepción: está controlado
 * porque se sugiere solo a partir de modelo, versión y año. Escribir encima lo
 * fija; vaciarlo vuelve a la sugerencia. Al editar una ficha arranca con el
 * slug guardado, así que no se regenera y no rompe una URL que ya circula.
 */
export default function VehiculoFormFields({
    opciones,
    errors,
    vehiculo,
}: VehiculoFormFieldsProps) {
    const [modelo, setModelo] = useState(vehiculo?.modelo ?? '');
    const [version, setVersion] = useState(vehiculo?.version ?? '');
    const [anio, setAnio] = useState(
        vehiculo?.anio === undefined ? '' : String(vehiculo.anio),
    );
    const [slugEscrito, setSlugEscrito] = useState(vehiculo?.slug ?? '');

    const slug =
        slugEscrito.trim() === ''
            ? slugSugerido(modelo, version, anio)
            : slugEscrito;

    return (
        <div className="flex flex-col gap-6">
            <div className="grid gap-4 sm:grid-cols-3">
                <Campo name="marca" label="Marca" error={errors.marca}>
                    <Input
                        id="marca"
                        name="marca"
                        defaultValue={vehiculo?.marca}
                        required
                        autoFocus
                        placeholder="Fiat"
                    />
                </Campo>

                <Campo name="modelo" label="Modelo" error={errors.modelo}>
                    <Input
                        id="modelo"
                        name="modelo"
                        defaultValue={vehiculo?.modelo}
                        onChange={(evento) => setModelo(evento.target.value)}
                        required
                        placeholder="Strada"
                    />
                </Campo>

                <Campo name="version" label="Versión" error={errors.version}>
                    <Input
                        id="version"
                        name="version"
                        defaultValue={vehiculo?.version ?? ''}
                        onChange={(evento) => setVersion(evento.target.value)}
                        placeholder="Freedom"
                    />
                </Campo>
            </div>

            <Campo
                name="slug"
                label="Slug"
                error={errors.slug}
                ayuda={`Es la dirección pública del vehículo: /vehiculos/${slug || EJEMPLO_SLUG}`}
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

            <div className="grid gap-4 sm:grid-cols-4">
                <Campo name="anio" label="Año" error={errors.anio}>
                    <Input
                        id="anio"
                        name="anio"
                        type="number"
                        defaultValue={vehiculo?.anio}
                        onChange={(evento) => setAnio(evento.target.value)}
                        required
                        placeholder="2024"
                    />
                </Campo>

                <Campo name="km" label="Kilómetros" error={errors.km}>
                    <Input
                        id="km"
                        name="km"
                        type="number"
                        min={0}
                        defaultValue={vehiculo?.km}
                        required
                        placeholder="18000"
                    />
                </Campo>

                <Campo name="precio" label="Precio" error={errors.precio}>
                    <Input
                        id="precio"
                        name="precio"
                        type="number"
                        min={0}
                        defaultValue={vehiculo?.precio}
                        required
                        placeholder="23900"
                    />
                </Campo>

                <SelectCampo
                    name="moneda"
                    label="Moneda"
                    opciones={opciones.monedas}
                    defaultValue={vehiculo?.moneda ?? 'USD'}
                    error={errors.moneda}
                    placeholder="Elegí la moneda"
                />
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <SelectCampo
                    name="tipo"
                    label="Carrocería"
                    opciones={opciones.tipos}
                    defaultValue={vehiculo?.tipo}
                    error={errors.tipo}
                    placeholder="Elegí la carrocería"
                />

                <SelectCampo
                    name="comb"
                    label="Combustible"
                    opciones={opciones.combustibles}
                    defaultValue={vehiculo?.comb}
                    error={errors.comb}
                    placeholder="Elegí el combustible"
                />

                <SelectCampo
                    name="trans"
                    label="Transmisión"
                    opciones={opciones.transmisiones}
                    defaultValue={vehiculo?.trans}
                    error={errors.trans}
                    placeholder="Elegí la transmisión"
                />
            </div>

            <SelectCampo
                name="estado"
                label="Estado"
                opciones={opciones.estados}
                defaultValue={vehiculo?.estado ?? 'borrador'}
                error={errors.estado}
                placeholder="Elegí el estado"
            />

            <Campo name="desc" label="Descripción" error={errors.desc}>
                <Textarea
                    id="desc"
                    name="desc"
                    rows={4}
                    defaultValue={vehiculo?.desc}
                    required
                    placeholder="Cabina doble, único dueño, service oficial al día."
                />
            </Campo>
        </div>
    );
}
