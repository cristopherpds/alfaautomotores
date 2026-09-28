import { index } from '@/routes/panel/productos';
import type { SeccionProducto } from '@/types';

/** El título de cada sección del panel, igual que la entrada del sidebar. */
export const TITULO_SECCION: Record<SeccionProducto, string> = {
    movilidad: 'Movilidad',
    bicicletas: 'Bicicletas',
};

/** El índice del panel filtrado por sección. */
export function indiceDeSeccion(seccion: SeccionProducto) {
    return index({ query: { seccion } });
}
