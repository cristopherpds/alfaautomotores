/*
 * Service worker del panel. Sólo sirve para el clic en las notificaciones de
 * WhatsApp (`useAvisosWhatsapp`): no hay push ni caché.
 *
 * Se registra con alcance `/panel/`. Si hay una pestaña del panel abierta
 * (no del sitio público, que no escucha el mensaje), la enfoca y le pide que
 * navegue sola (así Inertia no recarga la página); si no, abre una nueva.
 */

/** Las secciones del panel: ahí corre `useAvisosWhatsapp`. */
const RUTAS_DEL_PANEL = ['/panel', '/dashboard', '/settings', '/users'];

const esDelPanel = (direccion) => {
    const { origin, pathname } = new URL(direccion);

    return (
        origin === self.location.origin &&
        RUTAS_DEL_PANEL.some(
            (ruta) => pathname === ruta || pathname.startsWith(ruta + '/'),
        )
    );
};

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) =>
    event.waitUntil(self.clients.claim()),
);

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = new URL(
        event.notification.data?.url ?? '/panel/whatsapp',
        self.location.origin,
    ).href;

    event.waitUntil(
        self.clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((ventanas) => {
                const abierta = ventanas.find((ventana) =>
                    esDelPanel(ventana.url),
                );

                if (abierta) {
                    abierta.postMessage({ tipo: 'abrir-conversacion', url });

                    return abierta.focus();
                }

                return self.clients.openWindow(url);
            }),
    );
});
