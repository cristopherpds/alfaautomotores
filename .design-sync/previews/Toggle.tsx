import { Bold, Italic, Star, Toggle } from 'alfa-ui';

export const Default = () => (
    <div className="flex items-center gap-2">
        <Toggle aria-label="Negrita">
            <Bold />
        </Toggle>
        <Toggle aria-label="Cursiva" defaultPressed>
            <Italic />
        </Toggle>
    </div>
);

export const Outline = () => (
    <div className="flex items-center gap-2">
        <Toggle variant="outline" defaultPressed>
            <Star />
            Destacado
        </Toggle>
        <Toggle variant="outline" size="sm">
            Chico
        </Toggle>
        <Toggle variant="outline" size="lg">
            Grande
        </Toggle>
    </div>
);
