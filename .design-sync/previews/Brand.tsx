import { Brand } from 'alfa-ui';

export const Tamanos = () => (
    <div className="alfa">
        <div style={{ display: 'flex', alignItems: 'center', gap: 24 }}>
            <Brand size={32} />
            <Brand />
            <Brand size={72} />
        </div>
    </div>
);
