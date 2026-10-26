/*
 * Service worker del panel. Sólo sirve para el clic en las notificaciones de
 * WhatsApp (`useAvisosWhatsapp`): no hay push ni caché.
 *
 * Si hay una pestaña del panel abierta, la enfoca y le pide que navegue sola
 * (así Inertia no recarga la página); si no, abre una nueva.
 */

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
                const abierta = ventanas.find(
                    (ventana) =>
                        new URL(ventana.url).origin === self.location.origin,
                );

                if (abierta) {
                    abierta.postMessage({ tipo: 'abrir-conversacion', url });

                    return abierta.focus();
                }

                return self.clients.openWindow(url);
            }),
    );
});
