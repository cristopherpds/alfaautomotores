import { Entregas } from 'alfa-ui';
import foto1 from '../../database/data/entregas/01_2025-10-28.jpg';
import foto2 from '../../database/data/entregas/03_2025-11-04.jpg';
import foto3 from '../../database/data/entregas/06_2025-11-12.jpg';
import foto4 from '../../database/data/entregas/08_2025-11-18.jpg';

const entregas = [
    { url: foto1, fecha: '2025-10-28', etiqueta: '28.10.25', legible: '28 de octubre de 2025' },
    { url: foto2, fecha: '2025-11-04', etiqueta: '04.11.25', legible: '4 de noviembre de 2025' },
    { url: foto3, fecha: '2025-11-12', etiqueta: '12.11.25', legible: '12 de noviembre de 2025' },
    { url: foto4, fecha: '2025-11-18', etiqueta: '18.11.25', legible: '18 de noviembre de 2025' },
];

export const Portada = () => (
    <div className="alfa">
        <Entregas entregas={entregas} />
    </div>
);
