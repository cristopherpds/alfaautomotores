import { Form, Head, Link, router } from '@inertiajs/react';
import { Bot, CircleCheck, CircleX, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import AgenteController from '@/actions/App/Http/Controllers/Panel/AgenteController';
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
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { conexiones } from '@/routes/panel/ajustes';
import { agente, index as whatsappIndex } from '@/routes/panel/whatsapp';

type Perfil = {
    activo: boolean;
    nombre: string;
    tono: string | null;
    instrucciones: string | null;
    reglas_escalado: string | null;
    saludo: string | null;
};

type Conocimiento = {
    id: number;
    tipo: 'pregunta' | 'bloque';
    pregunta: string | null;
    respuesta: string | null;
    contenido: string | null;
};

type Props = {
    perfil: Perfil;
    conocimientos: Conocimiento[];
    estado: {
        whatsapp: boolean;
        ia: boolean;
        modelo: string;
        ultimaRespuesta: string | null;
    };
};

const fechaHora = new Intl.DateTimeFormat('es-UY', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

/**
 * El agente de IA de WhatsApp: quién es, cómo habla y qué sabe.
 */
export default function Agente({ perfil, conocimientos, estado }: Props) {
    const [activo, setActivo] = useState(perfil.activo);
    const contesta = perfil.activo && estado.whatsapp && estado.ia;

    return (
        <>
            <Head title="Agente de WhatsApp" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Agente de WhatsApp"
                    description="El asistente de IA que contesta solo. Responde únicamente con el stock, la agenda y lo que cargues acá."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Quién responde a tus clientes</CardTitle>
                        <CardDescription>
                            {contesta
                                ? `Contesta ${perfil.nombre}, el agente de IA (${estado.modelo}). El equipo puede tomar cualquier conversación desde la bandeja.`
                                : 'Por ahora contesta sólo el equipo, desde la bandeja.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-2 text-sm">
                        <Requisito
                            ok={perfil.activo}
                            texto="Agente encendido"
                        />
                        <Requisito
                            ok={estado.whatsapp}
                            texto="WhatsApp conectado"
                        />
                        <Requisito
                            ok={estado.ia}
                            texto="Clave de OpenRouter cargada"
                        />
                        {(!estado.whatsapp || !estado.ia) && (
                            <Link
                                href={conexiones()}
                                className="text-sm underline underline-offset-4"
                            >
                                Cargar las credenciales en Conexiones
                            </Link>
                        )}
                        {estado.ultimaRespuesta && (
                            <p className="text-muted-foreground">
                                Última respuesta automática:{' '}
                                {fechaHora.format(
                                    new Date(estado.ultimaRespuesta),
                                )}
                            </p>
                        )}
                    </CardContent>
                </Card>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Bot className="size-5" />
                                Perfil
                            </CardTitle>
                            <CardDescription>
                                Las reglas de conducta (decir que es una IA, no
                                inventar, pasar a una persona) vienen fijas y no
                                se pueden apagar desde acá.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...AgenteController.update.form()}
                                options={{ preserveScroll: true }}
                                className="grid gap-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="flex items-center justify-between gap-4 rounded-md border p-3">
                                            <div>
                                                <Label htmlFor="activo">
                                                    Contestar automáticamente
                                                </Label>
                                                <p className="text-xs text-muted-foreground">
                                                    Apagado, los mensajes entran
                                                    a la bandeja y nadie
                                                    contesta solo.
                                                </p>
                                            </div>
                                            <input
                                                type="hidden"
                                                name="activo"
                                                value={activo ? '1' : '0'}
                                            />
                                            <Switch
                                                id="activo"
                                                checked={activo}
                                                onCheckedChange={setActivo}
                                            />
                                        </div>

                                        <Campo
                                            name="nombre"
                                            label="Nombre del agente"
                                            error={errors.nombre}
                                        >
                                            <Input
                                                id="nombre"
                                                name="nombre"
                                                defaultValue={perfil.nombre}
                                                maxLength={40}
                                                required
                                            />
                                        </Campo>

                                        <Campo
                                            name="tono"
                                            label="Tono"
                                            error={errors.tono}
                                            ayuda="Por ejemplo: cercano y canchero, sin exagerar con los emojis."
                                        >
                                            <Input
                                                id="tono"
                                                name="tono"
                                                defaultValue={perfil.tono ?? ''}
                                            />
                                        </Campo>

                                        <Campo
                                            name="saludo"
                                            label="Saludo para conversaciones nuevas"
                                            error={errors.saludo}
                                        >
                                            <Input
                                                id="saludo"
                                                name="saludo"
                                                defaultValue={
                                                    perfil.saludo ?? ''
                                                }
                                                placeholder="¡Hola! Soy Alfi, el asistente virtual de Alfa Automotores 🚗"
                                            />
                                        </Campo>

                                        <Campo
                                            name="instrucciones"
                                            label="Instrucciones"
                                            error={errors.instrucciones}
                                            ayuda="Cómo querés que trabaje: qué priorizar, qué preguntar, qué no ofrecer."
                                        >
                                            <Textarea
                                                id="instrucciones"
                                                name="instrucciones"
                                                rows={6}
                                                defaultValue={
                                                    perfil.instrucciones ?? ''
                                                }
                                            />
                                        </Campo>

                                        <Campo
                                            name="reglas_escalado"
                                            label="Cuándo pasar a una persona"
                                            error={errors.reglas_escalado}
                                            ayuda="Se suman a las fijas (pedir humano, insultos, dudas sin respuesta). Ej.: si pregunta por financiación con cuota cero."
                                        >
                                            <Textarea
                                                id="reglas_escalado"
                                                name="reglas_escalado"
                                                rows={4}
                                                defaultValue={
                                                    perfil.reglas_escalado ?? ''
                                                }
                                            />
                                        </Campo>

                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            className="justify-self-start"
                                        >
                                            Guardar perfil
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Conocimiento</CardTitle>
                            <CardDescription>
                                Lo único que el agente puede afirmar además del
                                stock y la agenda: garantía, financiación,
                                horarios, cómo llegar, papeles…
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <FormularioConocimiento />

                            {conocimientos.length === 0 ? (
                                <Empty>
                                    <EmptyHeader>
                                        <EmptyTitle>
                                            Sin conocimiento cargado
                                        </EmptyTitle>
                                        <EmptyDescription>
                                            Sin esto el agente sólo habla del
                                            stock y los turnos, y deriva el
                                            resto.
                                        </EmptyDescription>
                                    </EmptyHeader>
                                </Empty>
                            ) : (
                                <ul className="grid gap-3">
                                    {conocimientos.map((entrada) => (
                                        <EntradaConocimiento
                                            key={entrada.id}
                                            entrada={entrada}
                                        />
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

function Requisito({ ok, texto }: { ok: boolean; texto: string }) {
    return (
        <p className="flex items-center gap-2">
            {ok ? (
                <CircleCheck className="size-4 text-primary" />
            ) : (
                <CircleX className="size-4 text-destructive" />
            )}
            {texto}
        </p>
    );
}

/** Alta o edición de una entrada: pregunta y respuesta, o un bloque libre. */
function FormularioConocimiento({
    entrada,
    onListo,
}: {
    entrada?: Conocimiento;
    onListo?: () => void;
}) {
    const [tipo, setTipo] = useState<'pregunta' | 'bloque'>(
        entrada?.tipo ?? 'pregunta',
    );
    const accion = entrada
        ? AgenteController.actualizarConocimiento.form(entrada.id)
        : AgenteController.guardarConocimiento.form();

    return (
        <Form
            {...accion}
            options={{ preserveScroll: true }}
            resetOnSuccess={!entrada}
            onSuccess={onListo}
            className="grid gap-3 rounded-md border p-3"
        >
            {({ processing, errors }) => (
                <>
                    <input type="hidden" name="tipo" value={tipo} />
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        size="sm"
                        value={tipo}
                        onValueChange={(valor) =>
                            valor && setTipo(valor as 'pregunta' | 'bloque')
                        }
                        className="justify-start"
                    >
                        <ToggleGroupItem value="pregunta">
                            Pregunta y respuesta
                        </ToggleGroupItem>
                        <ToggleGroupItem value="bloque">
                            Bloque libre
                        </ToggleGroupItem>
                    </ToggleGroup>

                    {tipo === 'pregunta' ? (
                        <>
                            <Input
                                name="pregunta"
                                placeholder="¿Reciben usados como parte de pago?"
                                defaultValue={entrada?.pregunta ?? ''}
                                aria-label="Pregunta"
                            />
                            <InputError message={errors.pregunta} />
                            <Textarea
                                name="respuesta"
                                rows={3}
                                placeholder="Sí, lo tasamos en el día y lo tomamos como parte de pago."
                                defaultValue={entrada?.respuesta ?? ''}
                                aria-label="Respuesta"
                            />
                            <InputError message={errors.respuesta} />
                        </>
                    ) : (
                        <>
                            <Textarea
                                name="contenido"
                                rows={4}
                                placeholder="Financiación: hasta 60 cuotas en pesos con cédula y recibo de sueldo…"
                                defaultValue={entrada?.contenido ?? ''}
                                aria-label="Contenido"
                            />
                            <InputError message={errors.contenido} />
                        </>
                    )}

                    <div className="flex gap-2">
                        <Button type="submit" size="sm" disabled={processing}>
                            {entrada ? (
                                'Guardar'
                            ) : (
                                <>
                                    <Plus /> Agregar
                                </>
                            )}
                        </Button>
                        {entrada && (
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={onListo}
                            >
                                Cancelar
                            </Button>
                        )}
                    </div>
                </>
            )}
        </Form>
    );
}

function EntradaConocimiento({ entrada }: { entrada: Conocimiento }) {
    const [editando, setEditando] = useState(false);

    if (editando) {
        return (
            <li>
                <FormularioConocimiento
                    entrada={entrada}
                    onListo={() => setEditando(false)}
                />
            </li>
        );
    }

    return (
        <li className="grid gap-1 rounded-md border p-3 text-sm">
            <div className="flex items-start justify-between gap-2">
                {entrada.tipo === 'pregunta' ? (
                    <p className="font-medium">{entrada.pregunta}</p>
                ) : (
                    <Badge variant="secondary">Bloque</Badge>
                )}
                <div className="flex shrink-0 gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-7"
                        onClick={() => setEditando(true)}
                        aria-label="Editar"
                    >
                        <Pencil />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-7 text-destructive"
                        onClick={() =>
                            router.delete(
                                AgenteController.borrarConocimiento.url(
                                    entrada.id,
                                ),
                                { preserveScroll: true },
                            )
                        }
                        aria-label="Eliminar"
                    >
                        <Trash2 />
                    </Button>
                </div>
            </div>
            <p className="whitespace-pre-wrap text-muted-foreground">
                {entrada.tipo === 'pregunta'
                    ? entrada.respuesta
                    : entrada.contenido}
            </p>
        </li>
    );
}

Agente.layout = {
    breadcrumbs: [
        { title: 'WhatsApp', href: whatsappIndex() },
        { title: 'Agente', href: agente() },
    ],
};
