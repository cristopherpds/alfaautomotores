import { Head, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import AjusteController from '@/actions/App/Http/Controllers/Panel/AjusteController';
import {
    IconoWhatsapp,
    TarjetaWhatsapp,
} from '@/components/alfa/boton-whatsapp';
import { Campo } from '@/components/form-campo';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { whatsapp as rutaWhatsapp } from '@/routes/panel/ajustes';
import type { AjusteBotonWhatsapp, ContactoWhatsappEditable } from '@/types';

type Props = {
    boton: AjusteBotonWhatsapp;
    maxContactos: number;
};

/** Los días en el orden de la semana uruguaya; el valor es el día ISO. */
const DIAS = [
    { valor: 1, corto: 'L', nombre: 'lunes' },
    { valor: 2, corto: 'M', nombre: 'martes' },
    { valor: 3, corto: 'X', nombre: 'miércoles' },
    { valor: 4, corto: 'J', nombre: 'jueves' },
    { valor: 5, corto: 'V', nombre: 'viernes' },
    { valor: 6, corto: 'S', nombre: 'sábado' },
    { valor: 7, corto: 'D', nombre: 'domingo' },
];

function contactoNuevo(): ContactoWhatsappEditable {
    return {
        nombre: '',
        detalle: '',
        numero: '598',
        mensaje: 'Hola Alfa Automotores, quería hacer una consulta.',
        respaldo: false,
        horario: {
            siempre: false,
            dias: [1, 2, 3, 4, 5],
            desde: '08:30',
            hasta: '18:00',
        },
    };
}

/** «L a V 08:30–18:00», para el resumen de cada contacto. */
function resumenDeHorario(contacto: ContactoWhatsappEditable): string {
    if (contacto.horario.siempre) {
        return 'Siempre disponible';
    }

    const dias = DIAS.filter((dia) => contacto.horario.dias.includes(dia.valor))
        .map((dia) => dia.corto)
        .join(' ');
    const cruza = contacto.horario.desde > contacto.horario.hasta;

    return `${dias || 'Sin días'} · ${contacto.horario.desde}–${contacto.horario.hasta}${cruza ? ' (del día siguiente)' : ''}`;
}

/**
 * El botón flotante de WhatsApp del sitio: los contactos que ofrece y en qué
 * horario. El sitio muestra los que están en horario; si no hay ninguno, el
 * de respaldo.
 */
export default function AjusteWhatsapp({ boton, maxContactos }: Props) {
    const form = useForm<AjusteBotonWhatsapp>(boton);

    /* Claves estables para la lista: al borrar o mover un contacto, React
       no tiene que confundir los inputs de uno con los de otro. */
    const [claves, setClaves] = useState(() =>
        boton.contactos.map((_, indice) => indice),
    );
    const [proxima, setProxima] = useState(boton.contactos.length);

    const contactos = form.data.contactos;

    const cambiarContacto = (
        indice: number,
        cambio: Partial<ContactoWhatsappEditable>,
    ) => {
        form.setData(
            'contactos',
            contactos.map((contacto, otro) =>
                otro === indice ? { ...contacto, ...cambio } : contacto,
            ),
        );
    };

    const cambiarHorario = (
        indice: number,
        cambio: Partial<ContactoWhatsappEditable['horario']>,
    ) => {
        cambiarContacto(indice, {
            horario: { ...contactos[indice].horario, ...cambio },
        });
    };

    /* El respaldo es uno solo: marcar uno desmarca los demás. */
    const marcarRespaldo = (indice: number) => {
        form.setData(
            'contactos',
            contactos.map((contacto, otro) => ({
                ...contacto,
                respaldo: otro === indice,
            })),
        );
    };

    const agregar = () => {
        form.setData('contactos', [...contactos, contactoNuevo()]);
        setClaves([...claves, proxima]);
        setProxima(proxima + 1);
    };

    const quitar = (indice: number) => {
        const quedan = contactos.filter((_, otro) => otro !== indice);

        // Si se va el respaldo, el primero que queda toma su lugar.
        if (contactos[indice].respaldo && quedan.length > 0) {
            quedan[0] = { ...quedan[0], respaldo: true };
        }

        form.setData('contactos', quedan);
        setClaves(claves.filter((_, otro) => otro !== indice));
    };

    const mover = (indice: number, destino: number) => {
        const nuevos = [...contactos];
        const nuevasClaves = [...claves];
        [nuevos[indice], nuevos[destino]] = [nuevos[destino], nuevos[indice]];
        [nuevasClaves[indice], nuevasClaves[destino]] = [
            nuevasClaves[destino],
            nuevasClaves[indice],
        ];
        form.setData('contactos', nuevos);
        setClaves(nuevasClaves);
    };

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();
        form.submit(AjusteController.guardarWhatsapp(), {
            preserveScroll: true,
        });
    };

    /* El error de un campo o de cualquier cosa debajo de él: los de una
       lista llegan como `contactos.0.horario.dias.2`. */
    const error = (campo: string): string | undefined =>
        Object.entries(form.errors as Record<string, string>).find(
            ([clave]) => clave === campo || clave.startsWith(`${campo}.`),
        )?.[1];

    const hayErrores = Object.keys(form.errors).length > 0;

    return (
        <>
            <Head title="Botón de WhatsApp" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Botón de WhatsApp"
                    description="Los contactos del botón verde del sitio y en qué horario atiende cada uno. Los demás links de WhatsApp del sitio van al primero disponible."
                />

                <form
                    onSubmit={guardar}
                    className="grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]"
                >
                    <div className="flex flex-col gap-6">
                        <div className="flex items-center justify-between gap-4 rounded-lg border p-4">
                            <div className="grid gap-1">
                                <Label htmlFor="activo">Mostrar el botón</Label>
                                <p className="text-sm text-muted-foreground">
                                    Apagado, los demás links de WhatsApp del
                                    sitio siguen andando.
                                </p>
                            </div>
                            <Switch
                                id="activo"
                                checked={form.data.activo}
                                onCheckedChange={(activo) =>
                                    form.setData('activo', activo)
                                }
                            />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo
                                name="titulo"
                                label="Título"
                                error={form.errors.titulo}
                            >
                                <Input
                                    id="titulo"
                                    value={form.data.titulo}
                                    onChange={(evento) =>
                                        form.setData(
                                            'titulo',
                                            evento.target.value,
                                        )
                                    }
                                    maxLength={40}
                                    required
                                />
                            </Campo>
                            <Campo
                                name="subtitulo"
                                label="Subtítulo"
                                error={form.errors.subtitulo}
                            >
                                <Input
                                    id="subtitulo"
                                    value={form.data.subtitulo}
                                    onChange={(evento) =>
                                        form.setData(
                                            'subtitulo',
                                            evento.target.value,
                                        )
                                    }
                                    maxLength={80}
                                    required
                                />
                            </Campo>
                        </div>

                        <div className="grid gap-1">
                            <h2 className="text-base font-medium">Contactos</h2>
                            <p className="text-sm text-muted-foreground">
                                En la tarjeta salen en este orden los que están
                                en horario. Un horario puede cruzar la
                                medianoche (20:00 a 08:00).
                            </p>
                            <InputError message={error('contactos')} />
                        </div>

                        {contactos.map((contacto, indice) => (
                            <Card key={claves[indice]} className="gap-4">
                                <CardHeader className="flex flex-row items-start justify-between gap-2">
                                    <div className="grid gap-1">
                                        <CardTitle className="flex items-center gap-2">
                                            {contacto.nombre ||
                                                'Contacto nuevo'}
                                            {contacto.respaldo && (
                                                <Badge variant="secondary">
                                                    Respaldo
                                                </Badge>
                                            )}
                                        </CardTitle>
                                        <CardDescription>
                                            {resumenDeHorario(contacto)}
                                        </CardDescription>
                                    </div>
                                    <div className="flex gap-1">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            disabled={indice === 0}
                                            onClick={() =>
                                                mover(indice, indice - 1)
                                            }
                                            aria-label={`Subir ${contacto.nombre || 'el contacto'}`}
                                        >
                                            <ArrowUp />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            disabled={
                                                indice === contactos.length - 1
                                            }
                                            onClick={() =>
                                                mover(indice, indice + 1)
                                            }
                                            aria-label={`Bajar ${contacto.nombre || 'el contacto'}`}
                                        >
                                            <ArrowDown />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            disabled={contactos.length === 1}
                                            onClick={() => quitar(indice)}
                                            aria-label={`Quitar ${contacto.nombre || 'el contacto'}`}
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                </CardHeader>

                                <CardContent className="grid gap-4">
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Campo
                                            name={`contacto-${indice}-nombre`}
                                            label="Nombre"
                                            error={error(
                                                `contactos.${indice}.nombre`,
                                            )}
                                        >
                                            <Input
                                                id={`contacto-${indice}-nombre`}
                                                value={contacto.nombre}
                                                onChange={(evento) =>
                                                    cambiarContacto(indice, {
                                                        nombre: evento.target
                                                            .value,
                                                    })
                                                }
                                                placeholder="Ventas"
                                                maxLength={40}
                                                required
                                            />
                                        </Campo>
                                        <Campo
                                            name={`contacto-${indice}-detalle`}
                                            label="Detalle (opcional)"
                                            error={error(
                                                `contactos.${indice}.detalle`,
                                            )}
                                        >
                                            <Input
                                                id={`contacto-${indice}-detalle`}
                                                value={contacto.detalle ?? ''}
                                                onChange={(evento) =>
                                                    cambiarContacto(indice, {
                                                        detalle:
                                                            evento.target.value,
                                                    })
                                                }
                                                placeholder="Autos usados"
                                                maxLength={60}
                                            />
                                        </Campo>
                                    </div>

                                    <Campo
                                        name={`contacto-${indice}-numero`}
                                        label="Número"
                                        error={error(
                                            `contactos.${indice}.numero`,
                                        )}
                                        ayuda="Con código de país: 598 y el celular sin el 0 (59899123456)."
                                    >
                                        <Input
                                            id={`contacto-${indice}-numero`}
                                            inputMode="tel"
                                            value={contacto.numero}
                                            onChange={(evento) =>
                                                cambiarContacto(indice, {
                                                    numero: evento.target.value,
                                                })
                                            }
                                            required
                                        />
                                    </Campo>

                                    <Campo
                                        name={`contacto-${indice}-mensaje`}
                                        label="Mensaje precargado"
                                        error={error(
                                            `contactos.${indice}.mensaje`,
                                        )}
                                    >
                                        <Textarea
                                            id={`contacto-${indice}-mensaje`}
                                            rows={2}
                                            value={contacto.mensaje}
                                            onChange={(evento) =>
                                                cambiarContacto(indice, {
                                                    mensaje:
                                                        evento.target.value,
                                                })
                                            }
                                            maxLength={300}
                                            required
                                        />
                                    </Campo>

                                    <div className="grid gap-3 rounded-lg border p-3">
                                        <div className="flex items-center gap-2">
                                            <Switch
                                                id={`contacto-${indice}-siempre`}
                                                checked={
                                                    contacto.horario.siempre
                                                }
                                                onCheckedChange={(siempre) =>
                                                    cambiarHorario(indice, {
                                                        siempre,
                                                    })
                                                }
                                            />
                                            <Label
                                                htmlFor={`contacto-${indice}-siempre`}
                                            >
                                                Siempre disponible
                                            </Label>
                                        </div>

                                        {!contacto.horario.siempre && (
                                            <div className="flex flex-wrap items-end gap-4">
                                                <div className="grid gap-1.5">
                                                    <Label>Días</Label>
                                                    <ToggleGroup
                                                        type="multiple"
                                                        variant="outline"
                                                        value={contacto.horario.dias.map(
                                                            String,
                                                        )}
                                                        onValueChange={(dias) =>
                                                            cambiarHorario(
                                                                indice,
                                                                {
                                                                    dias: dias
                                                                        .map(
                                                                            Number,
                                                                        )
                                                                        .sort(),
                                                                },
                                                            )
                                                        }
                                                    >
                                                        {DIAS.map((dia) => (
                                                            <ToggleGroupItem
                                                                key={dia.valor}
                                                                value={String(
                                                                    dia.valor,
                                                                )}
                                                                aria-label={
                                                                    dia.nombre
                                                                }
                                                            >
                                                                {dia.corto}
                                                            </ToggleGroupItem>
                                                        ))}
                                                    </ToggleGroup>
                                                </div>
                                                <div className="grid gap-1.5">
                                                    <Label
                                                        htmlFor={`contacto-${indice}-desde`}
                                                    >
                                                        Desde
                                                    </Label>
                                                    <Input
                                                        id={`contacto-${indice}-desde`}
                                                        type="time"
                                                        value={
                                                            contacto.horario
                                                                .desde
                                                        }
                                                        onChange={(evento) =>
                                                            cambiarHorario(
                                                                indice,
                                                                {
                                                                    desde: evento
                                                                        .target
                                                                        .value,
                                                                },
                                                            )
                                                        }
                                                        className="w-28"
                                                    />
                                                </div>
                                                <div className="grid gap-1.5">
                                                    <Label
                                                        htmlFor={`contacto-${indice}-hasta`}
                                                    >
                                                        Hasta
                                                    </Label>
                                                    <Input
                                                        id={`contacto-${indice}-hasta`}
                                                        type="time"
                                                        value={
                                                            contacto.horario
                                                                .hasta
                                                        }
                                                        onChange={(evento) =>
                                                            cambiarHorario(
                                                                indice,
                                                                {
                                                                    hasta: evento
                                                                        .target
                                                                        .value,
                                                                },
                                                            )
                                                        }
                                                        className="w-28"
                                                    />
                                                </div>
                                            </div>
                                        )}
                                        <InputError
                                            message={
                                                error(
                                                    `contactos.${indice}.horario.dias`,
                                                ) ??
                                                error(
                                                    `contactos.${indice}.horario.hasta`,
                                                )
                                            }
                                        />

                                        <label className="flex items-center gap-2 text-sm">
                                            <input
                                                type="radio"
                                                name="respaldo"
                                                checked={contacto.respaldo}
                                                onChange={() =>
                                                    marcarRespaldo(indice)
                                                }
                                                className="size-4 accent-primary"
                                            />
                                            Respaldo: se muestra cuando ningún
                                            contacto está en horario
                                        </label>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}

                        <div className="flex flex-wrap items-center gap-4">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={agregar}
                                disabled={contactos.length >= maxContactos}
                            >
                                <Plus />
                                Agregar contacto
                            </Button>
                            <Button
                                type="submit"
                                disabled={form.processing}
                                data-test="guardar-whatsapp-button"
                            >
                                Guardar
                            </Button>
                            {hayErrores && (
                                <p className="text-sm text-destructive">
                                    No se guardó: revisá los campos marcados.
                                </p>
                            )}
                        </div>
                    </div>

                    <Card className="self-start lg:sticky lg:top-4">
                        <CardHeader>
                            <CardTitle>Vista previa</CardTitle>
                            <CardDescription>
                                {form.data.activo
                                    ? 'Con todos los contactos. En el sitio salen sólo los que están en horario.'
                                    : 'Apagado: el sitio no muestra el botón.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div
                                className="alfa rounded-lg bg-muted p-4"
                                aria-hidden="true"
                                inert
                            >
                                <div
                                    className="wpp wpp--vista-previa"
                                    style={{
                                        opacity: form.data.activo ? 1 : 0.4,
                                    }}
                                >
                                    <TarjetaWhatsapp
                                        titulo={form.data.titulo}
                                        subtitulo={form.data.subtitulo}
                                        fueraDeHorario={false}
                                        contactos={contactos.map(
                                            (contacto) => ({
                                                ...contacto,
                                                nombre:
                                                    contacto.nombre ||
                                                    'Contacto nuevo',
                                                numero: contacto.numero.replace(
                                                    /\D+/g,
                                                    '',
                                                ),
                                            }),
                                        )}
                                    />
                                    <span className="wpp__boton">
                                        <IconoWhatsapp className="wpp__icono" />
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </>
    );
}

AjusteWhatsapp.layout = {
    breadcrumbs: [{ title: 'Botón de WhatsApp', href: rutaWhatsapp() }],
};
