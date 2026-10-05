import { Photo } from 'alfa-ui';

const marco = { position: 'relative' as const, containerType: 'inline-size' as const, width: 340, aspectRatio: '4 / 3' };

export const FotoPendiente = () => (
    <div className="alfa">
        <div style={marco}>
            <Photo alt="Fiat Strada Freedom" placeholder="Fiat Strada Freedom" detalle="2024" />
        </div>
    </div>
);

export const SinDetalle = () => (
    <div className="alfa">
        <div style={marco}>
            <Photo alt="Moto eléctrica MAX 350" placeholder="Moto eléctrica MAX 350" />
        </div>
    </div>
);
