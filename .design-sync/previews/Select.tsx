import { Label, Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from 'alfa-ui';

export const Cerrado = () => (
    <div className="grid w-64 gap-2">
        <Label>Rubro</Label>
        <Select defaultValue="taller">
            <SelectTrigger className="w-full">
                <SelectValue placeholder="Elegí un rubro" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="taller">Taller</SelectItem>
                <SelectItem value="lavadero">Lavadero</SelectItem>
            </SelectContent>
        </Select>
    </div>
);

export const Abierto = () => (
    <div className="h-80 w-64">
        <Select defaultOpen defaultValue="suv">
            <SelectTrigger className="w-full">
                <SelectValue placeholder="Carrocería" />
            </SelectTrigger>
            <SelectContent>
                <SelectGroup>
                    <SelectLabel>Carrocería</SelectLabel>
                    <SelectItem value="hatchback">Hatchback</SelectItem>
                    <SelectItem value="sedan">Sedán</SelectItem>
                    <SelectItem value="suv">SUV</SelectItem>
                    <SelectItem value="pickup">Pick-up</SelectItem>
                    <SelectItem value="utilitario">Utilitario</SelectItem>
                </SelectGroup>
            </SelectContent>
        </Select>
    </div>
);
