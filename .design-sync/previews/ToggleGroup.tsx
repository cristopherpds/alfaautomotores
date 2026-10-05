import { Monitor, Moon, Sun, ToggleGroup, ToggleGroupItem } from 'alfa-ui';

export const Apariencia = () => (
    <ToggleGroup type="single" defaultValue="light" variant="outline" className="w-fit">
        <ToggleGroupItem value="light" aria-label="Claro">
            <Sun />
            Claro
        </ToggleGroupItem>
        <ToggleGroupItem value="dark" aria-label="Oscuro">
            <Moon />
            Oscuro
        </ToggleGroupItem>
        <ToggleGroupItem value="system" aria-label="Sistema">
            <Monitor />
            Sistema
        </ToggleGroupItem>
    </ToggleGroup>
);

export const Dias = () => (
    <ToggleGroup type="multiple" defaultValue={['1', '2', '3', '4', '5']} variant="outline" className="w-fit">
        {['L', 'M', 'X', 'J', 'V', 'S', 'D'].map((dia, i) => (
            <ToggleGroupItem key={dia} value={String(i + 1)} aria-label={dia}>
                {dia}
            </ToggleGroupItem>
        ))}
    </ToggleGroup>
);
