/**
 * Las piezas de los avisos de WhatsApp que no dependen de React: el sonido,
 * la preferencia guardada, el service worker y la notificación del sistema.
 * Las usa `useAvisosWhatsapp` y el control de «Activar notificaciones».
 */

const CLAVE_SONIDO = 'alfa.avisos.sonido';

/** Evento de ventana para que el hook y el control vean el mismo valor. */
export const EVENTO_PREFERENCIAS = 'alfa:avisos-preferencias';

/** Como mucho un sonido cada tanto, aunque entren varios mensajes juntos. */
const SONIDO_CADA_MS = 1500;

/** Las dos notas del aviso: si (B5) y mi (E6). */
const NOTAS_HZ = [988, 1319] as const;

/** Si el sonido está prendido. Por defecto sí. */
export function sonidoActivado(): boolean {
    try {
        return window.localStorage.getItem(CLAVE_SONIDO) !== '0';
    } catch {
        return true;
    }
}

export function guardarSonido(activado: boolean): void {
    try {
        window.localStorage.setItem(CLAVE_SONIDO, activado ? '1' : '0');
    } catch {
        // Sin almacenamiento (ventana privada): vale sólo para esta pestaña.
    }

    window.dispatchEvent(new Event(EVENTO_PREFERENCIAS));
}

/** Si el navegador sabe mostrar notificaciones. */
export function notificacionesDisponibles(): boolean {
    return typeof window !== 'undefined' && 'Notification' in window;
}

export function permisoDeNotificaciones(): NotificationPermission | null {
    return notificacionesDisponibles() ? Notification.permission : null;
}

export async function pedirPermisoDeNotificaciones(): Promise<NotificationPermission | null> {
    if (!notificacionesDisponibles()) {
        return null;
    }

    const permiso = await Notification.requestPermission();
    window.dispatchEvent(new Event(EVENTO_PREFERENCIAS));

    return permiso;
}

let contexto: AudioContext | null = null;
let ultimoSonido = 0;

/**
 * Crear o despertar el `AudioContext`. Los navegadores sólo lo dejan sonar
 * después de un gesto del usuario, así que se llama desde el primer clic o
 * tecla.
 */
export function desbloquearSonido(): void {
    try {
        contexto ??= new AudioContext();

        if (contexto.state === 'suspended') {
            void contexto.resume();
        }
    } catch {
        contexto = null;
    }
}

/** Las dos notas, si el sonido está prendido y ya hubo un gesto. */
export function sonarAviso(): void {
    if (
        !sonidoActivado() ||
        contexto === null ||
        contexto.state !== 'running'
    ) {
        return;
    }

    const ahora = Date.now();

    if (ahora - ultimoSonido < SONIDO_CADA_MS) {
        return;
    }

    ultimoSonido = ahora;

    NOTAS_HZ.forEach((frecuencia, indice) => {
        const inicio = contexto!.currentTime + indice * 0.14;
        const oscilador = contexto!.createOscillator();
        const volumen = contexto!.createGain();

        oscilador.type = 'sine';
        oscilador.frequency.value = frecuencia;
        volumen.gain.setValueAtTime(0.0001, inicio);
        volumen.gain.exponentialRampToValueAtTime(0.18, inicio + 0.02);
        volumen.gain.exponentialRampToValueAtTime(0.0001, inicio + 0.3);

        oscilador.connect(volumen).connect(contexto!.destination);
        oscilador.start(inicio);
        oscilador.stop(inicio + 0.32);
    });
}

let registro: Promise<ServiceWorkerRegistration | null> | null = null;

/**
 * Hasta dónde controla el service worker: sólo el panel, nunca el sitio
 * público. `/sw.js` puede registrarse con un alcance más chico que su
 * carpeta sin el encabezado `Service-Worker-Allowed`.
 */
const ALCANCE_SERVICE_WORKER = '/panel/';

/** El service worker de `public/sw.js`: sólo maneja el clic en la notificación. */
export function registrarServiceWorker(): Promise<ServiceWorkerRegistration | null> {
    if (!('serviceWorker' in navigator)) {
        return Promise.resolve(null);
    }

    registro ??= olvidarRegistroDeTodoElSitio()
        .then(() =>
            navigator.serviceWorker.register('/sw.js', {
                scope: ALCANCE_SERVICE_WORKER,
            }),
        )
        .catch(() => null);

    return registro;
}

/** Las primeras versiones lo registraban con alcance `/`, sitio público incluido. */
async function olvidarRegistroDeTodoElSitio(): Promise<void> {
    const raiz = new URL('/', window.location.origin).href;
    const registros = await navigator.serviceWorker.getRegistrations();

    await Promise.all(
        registros
            .filter((registro) => registro.scope === raiz)
            .map((registro) => registro.unregister()),
    );
}

type Aviso = {
    titulo: string;
    cuerpo: string;
    /** Una por conversación: la nueva reemplaza a la anterior. */
    etiqueta: string;
    url: string;
    alTocar: () => void;
};

/** Mostrar la notificación del sistema, por el service worker si está. */
export async function mostrarNotificacion(aviso: Aviso): Promise<void> {
    if (permisoDeNotificaciones() !== 'granted') {
        return;
    }

    const opciones = {
        body: aviso.cuerpo,
        tag: aviso.etiqueta,
        renotify: true,
        icon: '/favicon.ico',
        data: { url: aviso.url },
    } as NotificationOptions;

    const sw = await registrarServiceWorker();

    if (sw !== null) {
        try {
            await sw.showNotification(aviso.titulo, opciones);

            return;
        } catch {
            // Sigue con la notificación común.
        }
    }

    try {
        const notificacion = new Notification(aviso.titulo, opciones);

        notificacion.onclick = () => {
            window.focus();
            aviso.alTocar();
            notificacion.close();
        };
    } catch {
        // Android no deja crear `Notification` sin service worker.
    }
}
