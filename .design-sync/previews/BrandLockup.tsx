import { BrandLockup } from 'alfa-ui';

export const Cabecera = () => (
    <div className="alfa">
        <a href="#" className="brand">
            <BrandLockup />
        </a>
    </div>
);

export const Compacto = () => (
    <div className="alfa">
        <a href="#" className="brand">
            <BrandLockup size={36} compact />
        </a>
    </div>
);
