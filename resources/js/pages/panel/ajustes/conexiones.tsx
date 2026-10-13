import { Form, Head, router } from '@inertiajs/react';
import {
    Bot,
    Copy,
    KeyRound,
    ListChecks,
    MessagesSquare,
    PlugZap,
    Shuffle,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import ConexionController from '@/actions/App/Http/Controllers/Panel/ConexionController';
import { Campo } from '@/components/form-campo';
import Heading from '@/components/heading';
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
import { useClipboard } from '@/hooks/use-clipboard';
import { conexiones } from '@/routes/panel/ajustes';

type Secreto = {
    cargado: boolean;
    final: string | null;
    origen: 'panel' | 'env' | null;
};

type Props = {
    whatsapp: {
        token: Secreto;
        appSecret: Secreto;
        phoneNumberId: string | null;
        wabaId: string | null;
        verifyToken: string | null;
        graphVersion: string;
        webhookUrl: string | null;
    };
    ia: {
        apiKey: Secreto;
        modelo: string;
        modeloTranscripcion: string;
    };
    /** Sólo llega al tocar «Traer modelos» (prop opcional). */
    modelos?: {
        agente: { id: string; nombre: string }[];
        audio: { id: string; nombre: string }[];
        error: string | null;
    };
};

/** Un token aleatorio para la URL del webhook. */
function tokenAleatorio(): string {
    const bytes = crypto.getRandomValues(new Uint8Array(24));

    return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join(
        '',
    );
}

/**
 * Las credenciales de WhatsApp (Meta) y de la IA (OpenRouter).
 */
export default function Conexiones({ whatsapp, ia, modelos }: Props) {
    const [verifyToken, setVerifyToken] = useState(whatsapp.verifyToken ?? '');
    const [trayendo, setTrayendo] = useState(false);
    const [, copiar] = useClipboard();

    return (
        <>
            <Head title="Conexiones" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Conexiones"
                    description="Las credenciales de WhatsApp y de la IA. Las claves se guardan cifradas y no se vuelven a mostrar: dejá el campo vacío para conservar la que está."
                />

                <div className="grid gap-6 xl:grid-cols-2 [&>*]:min-w-0">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <MessagesSquare className="size-5" />
                                WhatsApp Cloud API
                            </CardTitle>
                            <CardDescription>
                                De tu app en Meta for Developers → WhatsApp →
                                Configuración de la API.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...ConexionController.whatsapp.form()}
                                options={{ preserveScroll: true }}
                                resetOnSuccess={['token', 'app_secret']}
                                className="grid gap-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <Campo
                                            name="token"
                                            label="Token de acceso"
                                            error={errors.token}
                                            ayuda="El token permanente del usuario del sistema."
                                        >
                                            <SecretoInput
                                                name="token"
                                                secreto={whatsapp.token}
                                                conexion="whatsapp"
                                            />
                                        </Campo>

                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <Campo
                                                name="phone_number_id"
                                                label="Identificador del número"
                                                error={errors.phone_number_id}
                                            >
                                                <Input
                                                    id="phone_number_id"
                                                    name="phone_number_id"
                                                    inputMode="numeric"
                                                    defaultValue={
                                                        whatsapp.phoneNumberId ??
                                                        ''
                                                    }
                                                />
                                            </Campo>
                                            <Campo
                                                name="waba_id"
                                                label="Identificador de la cuenta (WABA)"
                                                error={errors.waba_id}
                                            >
                                                <Input
                                                    id="waba_id"
                                                    name="waba_id"
                                                    inputMode="numeric"
                                                    defaultValue={
                                                        whatsapp.wabaId ?? ''
                                                    }
                                                />
                                            </Campo>
                                        </div>

                                        <Campo
                                            name="app_secret"
                                            label="App Secret"
                                            error={errors.app_secret}
                                            ayuda="Configuración de la app → Básica. Verifica que cada aviso del webhook venga de Meta."
                                        >
                                            <SecretoInput
                                                name="app_secret"
                                                secreto={whatsapp.appSecret}
                                                conexion="whatsapp"
                                            />
                                        </Campo>

                                        <Campo
                                            name="verify_token"
                                            label="Token de verificación del webhook"
                                            error={errors.verify_token}
                                            ayuda="Lo inventás vos. Va en la URL del webhook y en el campo «Token de verificación» de Meta."
                                        >
                                            <div className="flex gap-2">
                                                <Input
                                                    id="verify_token"
                                                    name="verify_token"
                                                    value={verifyToken}
                                                    onChange={(evento) =>
                                                        setVerifyToken(
                                                            evento.target.value,
                                                        )
                                                    }
                                                    className="font-mono"
                                                />
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() =>
                                                        setVerifyToken(
                                                            tokenAleatorio(),
                                                        )
                                                    }
                                                >
                                                    <Shuffle />
                                                    Generar
                                                </Button>
                                            </div>
                                        </Campo>

                                        <Campo
                                            name="graph_version"
                                            label="Versión de la API"
                                            error={errors.graph_version}
                                        >
                                            <Input
                                                id="graph_version"
                                                name="graph_version"
                                                defaultValue={
                                                    whatsapp.graphVersion
                                                }
                                                className="max-w-32"
                                            />
                                        </Campo>

                                        {whatsapp.webhookUrl && (
                                            <div className="grid gap-2 rounded-md bg-muted p-3 text-sm">
                                                <p className="font-medium">
                                                    URL del webhook para Meta
                                                </p>
                                                <div className="flex items-center gap-2">
                                                    <code className="min-w-0 flex-1 truncate text-xs">
                                                        {whatsapp.webhookUrl}
                                                    </code>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            copiar(
                                                                whatsapp.webhookUrl ??
                                                                    '',
                                                            )
                                                        }
                                                        aria-label="Copiar la URL"
                                                    >
                                                        <Copy />
                                                    </Button>
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    En Meta hay que suscribir el
                                                    campo «messages». Si cambiás
                                                    el token, actualizá también
                                                    la URL allá.
                                                </p>
                                            </div>
                                        )}

                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Guardar
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    router.post(
                                                        ConexionController.probarWhatsapp.url(),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <PlugZap />
                                                Probar conexión
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Bot className="size-5" />
                                Inteligencia artificial
                            </CardTitle>
                            <CardDescription>
                                El agente responde por OpenRouter: con una sola
                                clave se puede usar cualquier modelo.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...ConexionController.ia.form()}
                                options={{ preserveScroll: true }}
                                resetOnSuccess={['api_key']}
                                className="grid gap-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <Campo
                                            name="api_key"
                                            label="Clave de OpenRouter"
                                            error={errors.api_key}
                                            ayuda="openrouter.ai → Keys."
                                        >
                                            <SecretoInput
                                                name="api_key"
                                                secreto={ia.apiKey}
                                                conexion="ia"
                                            />
                                        </Campo>

                                        <Campo
                                            name="modelo"
                                            label="Modelo del agente"
                                            error={errors.modelo}
                                            ayuda="Tiene que aceptar herramientas e imágenes. Por ejemplo google/gemini-2.5-flash o anthropic/claude-sonnet-4.5."
                                        >
                                            <Input
                                                id="modelo"
                                                name="modelo"
                                                defaultValue={ia.modelo}
                                                className="font-mono"
                                                list="modelos-agente"
                                                autoComplete="off"
                                            />
                                            <datalist id="modelos-agente">
                                                {modelos?.agente.map(
                                                    (modelo) => (
                                                        <option
                                                            key={modelo.id}
                                                            value={modelo.id}
                                                        >
                                                            {modelo.nombre}
                                                        </option>
                                                    ),
                                                )}
                                            </datalist>
                                        </Campo>

                                        <Campo
                                            name="modelo_transcripcion"
                                            label="Modelo para notas de voz"
                                            error={errors.modelo_transcripcion}
                                            ayuda="Uno que entienda audio, como google/gemini-2.5-flash."
                                        >
                                            <Input
                                                id="modelo_transcripcion"
                                                name="modelo_transcripcion"
                                                defaultValue={
                                                    ia.modeloTranscripcion
                                                }
                                                className="font-mono"
                                                list="modelos-audio"
                                                autoComplete="off"
                                            />
                                            <datalist id="modelos-audio">
                                                {modelos?.audio.map(
                                                    (modelo) => (
                                                        <option
                                                            key={modelo.id}
                                                            value={modelo.id}
                                                        >
                                                            {modelo.nombre}
                                                        </option>
                                                    ),
                                                )}
                                            </datalist>
                                        </Campo>

                                        {modelos ? (
                                            <p className="text-xs text-muted-foreground">
                                                {modelos.error ??
                                                    `${modelos.agente.length} modelos para el agente y ${modelos.audio.length} para audio: empezá a escribir en el campo para elegir.`}
                                            </p>
                                        ) : null}

                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Guardar
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    router.post(
                                                        ConexionController.probarIa.url(),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <PlugZap />
                                                Probar clave
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                disabled={trayendo}
                                                onClick={() =>
                                                    router.reload({
                                                        only: ['modelos'],
                                                        onStart: () =>
                                                            setTrayendo(true),
                                                        onFinish: () =>
                                                            setTrayendo(false),
                                                    })
                                                }
                                            >
                                                <ListChecks />
                                                {trayendo
                                                    ? 'Trayendo…'
                                                    : 'Traer modelos'}
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

/** Un campo de clave que nunca muestra la guardada: sólo si está y su final. */
function SecretoInput({
    name,
    secreto,
    conexion,
}: {
    name: string;
    secreto: Secreto;
    conexion: 'whatsapp' | 'ia';
}) {
    return (
        <div className="grid gap-2">
            <Input
                id={name}
                name={name}
                type="password"
                autoComplete="off"
                placeholder={
                    secreto.cargado
                        ? `••••${secreto.final ?? ''} (dejalo vacío para conservarla)`
                        : 'Pegá la clave'
                }
            />
            {secreto.cargado && (
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    <KeyRound className="size-3" />
                    {secreto.origen === 'panel' ? (
                        <>
                            <Badge variant="secondary">
                                Guardada en el panel
                            </Badge>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-6 px-2 text-xs"
                                onClick={() =>
                                    router.delete(
                                        ConexionController.olvidar.url({
                                            conexion,
                                            campo: name,
                                        }),
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Trash2 />
                                Borrar
                            </Button>
                        </>
                    ) : (
                        <Badge variant="outline">Tomada del .env</Badge>
                    )}
                </div>
            )}
        </div>
    );
}

Conexiones.layout = {
    breadcrumbs: [{ title: 'Conexiones', href: conexiones() }],
};
