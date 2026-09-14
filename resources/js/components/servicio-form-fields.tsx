import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { OpcionSelect, ServicioEditable } from '@/types';

type Props = {
    areas: OpcionSelect[];
    errors: Partial<Record<string, string>>;
    servicio?: ServicioEditable;
};

/**
 * Los campos de un servicio del taller, compartidos por el alta y la edición.
 *
 * `duracion` no es un dato más: es el largo del turno, así que cambiarla mueve
 * los horarios que la web ofrece a partir de la próxima consulta.
 */
export default function ServicioFormFields({ areas, errors, servicio }: Props) {
    return (
        <>
            <div className="grid gap-2 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="nombre">Nombre</Label>
                    <Input
                        id="nombre"
                        name="nombre"
                        defaultValue={servicio?.nombre}
                        required
                    />
                    <InputError message={errors.nombre} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="slug">Slug</Label>
                    <Input
                        id="slug"
                        name="slug"
                        defaultValue={servicio?.slug}
                        placeholder="service-completo"
                        required
                    />
                    <InputError message={errors.slug} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="area">Área</Label>
                    <select
                        id="area"
                        name="area"
                        defaultValue={servicio?.area ?? areas[0]?.value}
                        className="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs"
                    >
                        {areas.map((area) => (
                            <option key={area.value} value={area.value}>
                                {area.label}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.area} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="duracion">Duración (minutos)</Label>
                    <Input
                        id="duracion"
                        name="duracion"
                        type="number"
                        step={5}
                        min={15}
                        max={480}
                        defaultValue={servicio?.duracion ?? 60}
                        required
                    />
                    <p className="text-sm text-muted-foreground">
                        Es el largo del turno: define los horarios que se
                        ofrecen en la web.
                    </p>
                    <InputError message={errors.duracion} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="descripcion">Descripción</Label>
                <Textarea
                    id="descripcion"
                    name="descripcion"
                    rows={3}
                    defaultValue={servicio?.descripcion}
                    required
                />
                <InputError message={errors.descripcion} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="foto">Foto</Label>
                <Input
                    id="foto"
                    name="foto"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    className="max-w-md"
                />
                <p className="text-sm text-muted-foreground">
                    Se ve en la tarjeta del sitio público, recortada en 4:3.
                </p>
                <InputError message={errors.foto} />
            </div>

            <div className="grid gap-2 sm:grid-cols-3">
                <div className="flex items-center gap-3 rounded-lg border p-3">
                    <Switch
                        id="activo"
                        name="activo"
                        defaultChecked={servicio?.activo ?? true}
                        value="1"
                    />
                    <Label htmlFor="activo">Visible en la web</Label>
                </div>

                <div className="flex items-center gap-3 rounded-lg border p-3">
                    <Switch
                        id="agendable"
                        name="agendable"
                        defaultChecked={servicio?.agendable ?? true}
                        value="1"
                    />
                    <Label htmlFor="agendable">Se puede reservar online</Label>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="orden">Orden</Label>
                    <Input
                        id="orden"
                        name="orden"
                        type="number"
                        min={0}
                        max={255}
                        defaultValue={servicio?.orden ?? 0}
                        required
                    />
                    <InputError message={errors.orden} />
                </div>
            </div>
        </>
    );
}
