import { SiteFooter } from 'alfa-ui';

const site = {
    nombre: 'Alfa Automotores',
    ciudad: 'Rivera',
    pais: 'Uruguay',
    direccion: 'Ituzaingó 779',
    codigoPostal: '40000',
    horarios: {
        semana: '08:30–12:00 · 14:00–18:00',
        sabado: '08:30–12:00',
        corto: 'Lun a Vie 08:30–12:00 / 14:00–18:00 · Sáb 08:30–12:00',
    },
    instagram: 'https://www.instagram.com/alfaautomoviles2023/',
    whatsapp: '59899000000',
    telefono: '+59846222222',
    botonWhatsapp: { activo: true, titulo: '¡Hola!', subtitulo: '¿En qué te podemos ayudar?', fueraDeHorario: false, contactos: [] },
};

export const Pie = () => (
    <div className="alfa">
        <SiteFooter site={site} />
    </div>
);
