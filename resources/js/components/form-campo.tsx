import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { OpcionSelect } from '@/types';

type CampoProps = {
    name: string;
    label: string;
    error?: string;
    children: React.ReactNode;
    ayuda?: string;
};

/**
 * Etiqueta, control, ayuda y error de un campo del panel. Lo comparten las
 * fichas de vehículos y de productos.
 */
export function Campo({ name, label, error, children, ayuda }: CampoProps) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            {children}
            {ayuda && <p className="text-xs text-muted-foreground">{ayuda}</p>}
            <InputError message={error} />
        </div>
    );
}

type SelectCampoProps = {
    name: string;
    label: string;
    opciones: OpcionSelect[];
    defaultValue?: string;
    error?: string;
    placeholder: string;
    onValueChange?: (valor: string) => void;
};

/** Un `<Select>` sin controlar sobre las opciones que manda un enum de PHP. */
export function SelectCampo({
    name,
    label,
    opciones,
    defaultValue,
    error,
    placeholder,
    onValueChange,
}: SelectCampoProps) {
    return (
        <Campo name={name} label={label} error={error}>
            <Select
                name={name}
                defaultValue={defaultValue}
                onValueChange={onValueChange}
            >
                <SelectTrigger id={name} className="w-full">
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>

                <SelectContent>
                    <SelectGroup>
                        {opciones.map((opcion) => (
                            <SelectItem key={opcion.value} value={opcion.value}>
                                <span className="flex flex-col items-start">
                                    <span>{opcion.label}</span>
                                    {opcion.description && (
                                        <span className="text-xs text-muted-foreground">
                                            {opcion.description}
                                        </span>
                                    )}
                                </span>
                            </SelectItem>
                        ))}
                    </SelectGroup>
                </SelectContent>
            </Select>
        </Campo>
    );
}
