import { useState } from 'react';
import { Campo } from '@/components/form-campo';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { ClienteEditable } from '@/types';

type Errores = Record<string, string>;

type ClienteFormFieldsProps = {
    errors: Errores;
    cliente?: ClienteEditable;
    /** Sin permiso de edición, los campos se muestran pero no se tocan. */
    soloLectura?: boolean;
};

const fecha = new Intl.DateTimeFormat('es-UY', { dateStyle: 'short' });

/**
 * Los datos de un cliente, compartidos por el alta manual y la ficha.
 *
 * El consentimiento va controlado y viaja en un input oculto con 1 o 0: un
 * checkbox destildado no manda nada, y entonces retirarlo desde el panel no
 * llegaría nunca al servidor.
 */
export default function ClienteFormFields({
    errors,
    cliente,
    soloLectura = false,
}: ClienteFormFieldsProps) {
    const [acepta, setAcepta] = useState(cliente?.acepta_novedades ?? false);

    return (
        <div className="flex flex-col gap-6">
            <div className="grid gap-4 sm:grid-cols-2">
                <Campo name="nombre" label="Nombre" error={errors.nombre}>
                    <Input
                        id="nombre"
                        name="nombre"
                        defaultValue={cliente?.nombre}
                        required
                        disabled={soloLectura}
                    />
                </Campo>

                <Campo name="apellido" label="Apellido" error={errors.apellido}>
                    <Input
                        id="apellido"
                        name="apellido"
                        defaultValue={cliente?.apellido}
                        required
                        disabled={soloLectura}
                    />
                </Campo>

                <Campo
                    name="celular"
                    label="Celular"
                    error={errors.celular}
                    ayuda="Identifica al cliente: una reserva con este número se suma a esta ficha."
                >
                    <Input
                        id="celular"
                        name="celular"
                        type="tel"
                        defaultValue={cliente?.celular}
                        required
                        placeholder="099 123 456"
                        disabled={soloLectura}
                    />
                </Campo>

                <Campo name="email" label="Email" error={errors.email}>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        defaultValue={cliente?.email ?? ''}
                        disabled={soloLectura}
                    />
                </Campo>
            </div>

            <Campo
                name="notas"
                label="Notas internas"
                error={errors.notas}
                ayuda="No las ve el cliente."
            >
                <Textarea
                    id="notas"
                    name="notas"
                    rows={3}
                    defaultValue={cliente?.notas ?? ''}
                    placeholder="Prefiere que lo llamen de tarde."
                    disabled={soloLectura}
                />
            </Campo>

            <div className="grid gap-1">
                <input
                    type="hidden"
                    name="acepta_novedades"
                    value={acepta ? '1' : '0'}
                />
                <div className="flex items-center gap-2">
                    <Checkbox
                        id="acepta_novedades"
                        checked={acepta}
                        onCheckedChange={(tildado) =>
                            setAcepta(tildado === true)
                        }
                        disabled={soloLectura}
                    />
                    <Label htmlFor="acepta_novedades">
                        Acepta recibir novedades y recordatorios
                    </Label>
                </div>
                <p className="text-xs text-muted-foreground">
                    {cliente?.acepta_novedades && cliente.acepta_novedades_at
                        ? `Consentimiento dado el ${fecha.format(new Date(cliente.acepta_novedades_at))}.`
                        : 'Sólo si el cliente lo pidió: es el permiso para escribirle con promociones.'}
                </p>
            </div>
        </div>
    );
}
