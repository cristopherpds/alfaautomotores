import { Form, Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    Bot,
    Check,
    CheckCheck,
    Clock,
    FileText,
    Loader2,
    Paperclip,
    SendHorizontal,
    X,
} from 'lucide-react';
import { useLayoutEffect, useRef, useState } from 'react';
import ConversacionController from '@/actions/App/Http/Controllers/Panel/ConversacionController';
import MensajeWhatsappController from '@/actions/App/Http/Controllers/Panel/MensajeWhatsappController';
import PlantillaWhatsappController from '@/actions/App/Http/Controllers/Panel/PlantillaWhatsappController';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { index } from '@/routes/panel/whatsapp';
import type {
    ConversacionAbierta,
    EstadoMensajeWhatsapp,
    MensajeWhatsapp,
} from '@/types';
import { cuandoCorto } from './lista-conversaciones';
import { TextoWhatsapp } from './texto-whatsapp';

/** El hilo de la conversación abierta y la caja para contestar. */
export function Hilo({ conversacion }: { conversacion: ConversacionAbierta }) {
    const fondo = useRef<HTMLDivElement>(null);
    const ultimoId = conversacion.mensajes.at(-1)?.id;

    /* Al abrir otra conversación o llegar un mensaje nuevo, al fondo. El
       polling repinta cada pocos segundos: sólo se baja si cambió el último. */
    useLayoutEffect(() => {
        fondo.current?.scrollIntoView({ block: 'end' });
    }, [conversacion.id, ultimoId]);

    const atiendeLaIa = conversacion.iaActiva && !conversacion.derivada;

    return (
        <div className="flex min-h-0 min-w-0 flex-col">
            {/* Debajo de `xl` no hay panel del contacto: esta barra deja
                volver a la lista y tomar o devolver la conversación. */}
            <div className="flex items-center gap-2 border-b border-sidebar-border/70 px-2 py-2 xl:hidden">
                <Button
                    variant="ghost"
                    size="icon"
                    className="md:hidden"
                    asChild
                >
                    <Link href={index()} aria-label="Volver a la lista">
                        <ArrowLeft />
                    </Link>
                </Button>
                <div className="min-w-0 flex-1 px-1">
                    <p className="truncate text-sm font-medium">
                        {conversacion.nombre}
                    </p>
                    <p
                        className={cn(
                            'truncate text-xs',
                            conversacion.derivada
                                ? 'text-destructive'
                                : 'text-muted-foreground',
                        )}
                    >
                        {conversacion.derivada
                            ? (conversacion.motivoDerivacion ??
                              'Atención humana')
                            : conversacion.telefono}
                    </p>
                </div>
                <Label
                    htmlFor="ia-activa-compacto"
                    className="flex shrink-0 items-center gap-2 text-xs"
                >
                    <Bot className="size-4" />
                    IA
                </Label>
                <Switch
                    id="ia-activa-compacto"
                    checked={atiendeLaIa}
                    onCheckedChange={(activa) =>
                        router.patch(
                            ConversacionController.ia.url(conversacion.id),
                            { activa },
                            { preserveScroll: true, preserveState: true },
                        )
                    }
                    data-test="ia-switch-compacto"
                />
            </div>

            <div className="min-h-0 flex-1 overflow-y-auto bg-muted/30 px-4 py-4">
                <ol className="mx-auto flex max-w-3xl flex-col gap-2">
                    {conversacion.mensajes.map((mensaje) => (
                        <Burbuja key={mensaje.id} mensaje={mensaje} />
                    ))}
                </ol>
                <div ref={fondo} />
            </div>

            <div className="border-t border-sidebar-border/70 p-3">
                {conversacion.ventanaAbierta ? (
                    /* El `key` la vacía al cambiar de conversación: no se
                       arrastra el adjunto de la otra. */
                    <CajaDeTexto
                        key={conversacion.id}
                        conversacionId={conversacion.id}
                    />
                ) : (
                    <EnviarPlantilla conversacion={conversacion} />
                )}
            </div>
        </div>
    );
}

function Burbuja({ mensaje }: { mensaje: MensajeWhatsapp }) {
    return (
        <li
            className={cn(
                'flex',
                mensaje.entrante ? 'justify-start' : 'justify-end',
            )}
        >
            <div
                className={cn(
                    'max-w-[80%] rounded-lg px-3 py-2 text-sm shadow-xs',
                    mensaje.entrante
                        ? 'bg-background'
                        : 'bg-primary text-primary-foreground',
                )}
            >
                {!mensaje.entrante &&
                    (mensaje.generadoPorIa ||
                        mensaje.autor ||
                        mensaje.plantilla) && (
                        <p className="mb-1 flex items-center gap-1 text-xs opacity-80">
                            {mensaje.generadoPorIa ? (
                                <>
                                    <Bot className="size-3" />
                                    IA
                                </>
                            ) : (
                                mensaje.autor
                            )}
                            {mensaje.plantilla &&
                                ` · plantilla ${mensaje.plantilla}`}
                        </p>
                    )}

                <Adjunto mensaje={mensaje} />

                {mensaje.cuerpo ? (
                    <p className="[overflow-wrap:anywhere] whitespace-pre-wrap">
                        <TextoWhatsapp texto={mensaje.cuerpo} />
                    </p>
                ) : (
                    !mensaje.media &&
                    !mensaje.mediaPendiente && (
                        <p className="italic opacity-70">{mensaje.tipoLabel}</p>
                    )
                )}

                <p className="mt-1 flex items-center justify-end gap-1 text-[11px] opacity-70">
                    {cuandoCorto(mensaje.enviadoAt)}
                    {!mensaje.entrante && (
                        <EstadoIcono
                            estado={mensaje.estado}
                            error={mensaje.error}
                        />
                    )}
                </p>
            </div>
        </li>
    );
}

function Adjunto({ mensaje }: { mensaje: MensajeWhatsapp }) {
    if (mensaje.mediaPendiente) {
        return (
            <p className="mb-1 flex items-center gap-2 text-xs opacity-70">
                <Loader2 className="size-3 animate-spin" />
                {mensaje.error ?? `${mensaje.tipoLabel}: bajando el archivo…`}
            </p>
        );
    }

    if (!mensaje.media) {
        return null;
    }

    const { url, nombre } = mensaje.media;

    switch (mensaje.tipo) {
        case 'imagen':
        case 'sticker':
            return (
                <a
                    href={url}
                    target="_blank"
                    rel="noopener"
                    className="mb-1 block"
                >
                    <img
                        src={url}
                        alt={mensaje.cuerpo ?? mensaje.tipoLabel}
                        className={cn(
                            'rounded-md object-cover',
                            mensaje.tipo === 'sticker' ? 'size-32' : 'max-h-72',
                        )}
                        loading="lazy"
                    />
                </a>
            );
        case 'audio':
            return <audio controls src={url} className="mb-1 max-w-64" />;
        case 'video':
            return (
                <video
                    controls
                    src={url}
                    className="mb-1 max-h-72 rounded-md"
                />
            );
        default:
            return (
                <a
                    href={url}
                    target="_blank"
                    rel="noopener"
                    className="mb-1 flex items-center gap-2 underline underline-offset-4"
                >
                    <FileText className="size-4 shrink-0" />
                    {nombre ?? 'Documento'}
                </a>
            );
    }
}

function EstadoIcono({
    estado,
    error,
}: {
    estado: EstadoMensajeWhatsapp;
    error: string | null;
}) {
    switch (estado) {
        case 'pendiente':
            return <Clock className="size-3" aria-label="Enviando" />;
        case 'enviado':
            return <Check className="size-3" aria-label="Enviado" />;
        case 'entregado':
            return <CheckCheck className="size-3" aria-label="Entregado" />;
        case 'leido':
            return (
                <CheckCheck
                    className="size-3 opacity-100"
                    strokeWidth={3}
                    aria-label="Leído"
                />
            );
        case 'fallido':
            return (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <AlertCircle
                            className="size-3.5"
                            aria-label="No se envió"
                        />
                    </TooltipTrigger>
                    <TooltipContent>{error ?? 'No se envió'}</TooltipContent>
                </Tooltip>
            );
        default:
            return null;
    }
}

/** Texto o archivo. Enter manda, Shift+Enter baja de línea. */
function CajaDeTexto({ conversacionId }: { conversacionId: number }) {
    const archivoInput = useRef<HTMLInputElement>(null);
    const [archivo, setArchivo] = useState<string | null>(null);

    return (
        <Form
            {...MensajeWhatsappController.store.form(conversacionId)}
            options={{ preserveScroll: true, preserveState: true }}
            resetOnSuccess
            onSuccess={() => setArchivo(null)}
            className="mx-auto grid max-w-3xl gap-2"
        >
            {({ processing, errors }) => (
                <>
                    {archivo && (
                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                            <Paperclip className="size-3" />
                            <span className="truncate">{archivo}</span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-6"
                                onClick={() => {
                                    setArchivo(null);

                                    if (archivoInput.current) {
                                        archivoInput.current.value = '';
                                    }
                                }}
                                aria-label="Quitar el adjunto"
                            >
                                <X />
                            </Button>
                        </div>
                    )}

                    <div className="flex items-end gap-2">
                        <input
                            ref={archivoInput}
                            type="file"
                            name="archivo"
                            accept="image/jpeg,image/png,application/pdf,.doc,.docx,.xls,.xlsx"
                            className="sr-only"
                            id={`archivo-${conversacionId}`}
                            onChange={(evento) =>
                                setArchivo(
                                    evento.target.files?.[0]?.name ?? null,
                                )
                            }
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => archivoInput.current?.click()}
                            aria-label="Adjuntar archivo"
                        >
                            <Paperclip />
                        </Button>

                        <Textarea
                            name="cuerpo"
                            rows={1}
                            placeholder={
                                archivo
                                    ? 'Epígrafe (opcional)'
                                    : 'Escribí un mensaje'
                            }
                            className="max-h-40 min-h-10 flex-1 resize-none"
                            onKeyDown={(evento) => {
                                if (
                                    evento.key === 'Enter' &&
                                    !evento.shiftKey
                                ) {
                                    evento.preventDefault();
                                    evento.currentTarget.form?.requestSubmit();
                                }
                            }}
                            data-test="caja-mensaje"
                        />

                        <Button
                            type="submit"
                            size="icon"
                            disabled={processing}
                            aria-label="Enviar"
                            data-test="enviar-mensaje-button"
                        >
                            {processing ? (
                                <Loader2 className="animate-spin" />
                            ) : (
                                <SendHorizontal />
                            )}
                        </Button>
                    </div>

                    <InputError message={errors.cuerpo ?? errors.archivo} />
                </>
            )}
        </Form>
    );
}

/** Con la ventana de 24 h cerrada, lo único que se puede mandar. */
function EnviarPlantilla({
    conversacion,
}: {
    conversacion: ConversacionAbierta;
}) {
    const [elegidaId, setElegidaId] = useState('');
    const elegida = conversacion.plantillas.find(
        (plantilla) => String(plantilla.id) === elegidaId,
    );

    return (
        <div className="mx-auto grid max-w-3xl gap-3">
            <Alert>
                <Clock />
                <AlertTitle>
                    {conversacion.nuncaEscribio
                        ? 'Todavía no te escribió'
                        : 'Pasaron más de 24 h desde su último mensaje'}
                </AlertTitle>
                <AlertDescription>
                    {conversacion.nuncaEscribio
                        ? 'Para arrancar, WhatsApp sólo deja mandarle una plantilla aprobada. Cuando conteste, se puede escribir libre.'
                        : 'WhatsApp sólo deja escribirle con una plantilla aprobada. Cuando conteste, se vuelve a poder escribir libre.'}
                </AlertDescription>
            </Alert>

            {conversacion.plantillas.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No hay plantillas aprobadas. Se crean en el administrador de
                    WhatsApp de Meta y se traen con «Sincronizar plantillas».
                </p>
            ) : (
                <Form
                    {...PlantillaWhatsappController.enviar.form(
                        conversacion.id,
                    )}
                    options={{ preserveScroll: true, preserveState: true }}
                    onSuccess={() => setElegidaId('')}
                    className="grid gap-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="plantilla">Plantilla</Label>
                                <Select
                                    name="plantilla"
                                    value={elegidaId}
                                    onValueChange={setElegidaId}
                                >
                                    <SelectTrigger
                                        id="plantilla"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Elegí una plantilla" />
                                    </SelectTrigger>
                                    {/* Va al pie de la bandeja: abajo no
                                        hay lugar para la lista. */}
                                    <SelectContent side="top">
                                        <SelectGroup>
                                            {conversacion.plantillas.map(
                                                (plantilla) => (
                                                    <SelectItem
                                                        key={plantilla.id}
                                                        value={String(
                                                            plantilla.id,
                                                        )}
                                                    >
                                                        {plantilla.nombre}
                                                        <Badge
                                                            variant="outline"
                                                            className="ml-2"
                                                        >
                                                            {plantilla.idioma}
                                                        </Badge>
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.plantilla} />
                            </div>

                            {elegida && (
                                <>
                                    <p className="rounded-md bg-muted px-3 py-2 text-sm whitespace-pre-wrap">
                                        {elegida.cuerpo}
                                    </p>

                                    {Array.from(
                                        { length: elegida.variables },
                                        (_, indice) => (
                                            <div
                                                className="grid gap-2"
                                                key={`${elegida.id}-${indice}`}
                                            >
                                                <Label
                                                    htmlFor={`variable-${indice}`}
                                                >
                                                    Variable{' '}
                                                    {`{{${indice + 1}}}`}
                                                </Label>
                                                <Input
                                                    id={`variable-${indice}`}
                                                    name={`variables[${indice}]`}
                                                    required
                                                />
                                            </div>
                                        ),
                                    )}
                                    <InputError message={errors.variables} />

                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="justify-self-end"
                                        data-test="enviar-plantilla-button"
                                    >
                                        <SendHorizontal />
                                        Enviar plantilla
                                    </Button>
                                </>
                            )}
                        </>
                    )}
                </Form>
            )}
        </div>
    );
}
