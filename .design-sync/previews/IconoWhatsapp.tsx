import { IconoWhatsapp } from 'alfa-ui';

export const BotonFlotante = () => (
    <div className="alfa">
        <div className="wpp wpp--vista-previa">
            <span className="wpp__boton">
                <IconoWhatsapp className="wpp__icono" />
            </span>
        </div>
    </div>
);

export const EnBoton = () => (
    <div className="alfa">
        <a href="#" className="btn">
            <IconoWhatsapp className="wpp__icono-contacto" />
            Escribinos por WhatsApp
        </a>
    </div>
);
