import { PlaceholderPattern } from 'alfa-ui';

export const Recuadro = () => (
    <div className="relative aspect-video w-96 overflow-hidden rounded-xl border border-sidebar-border/70">
        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20" />
    </div>
);

export const Grilla = () => (
    <div className="grid w-[36rem] grid-cols-3 gap-4">
        {[1, 2, 3].map((n) => (
            <div key={n} className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70">
                <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20" />
            </div>
        ))}
    </div>
);
