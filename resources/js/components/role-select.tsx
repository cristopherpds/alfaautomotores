import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { RoleOption, UserRole } from '@/types';

type RoleSelectProps = {
    roles: RoleOption[];
    defaultValue?: UserRole;
    error?: string;
    locked?: boolean;
    lockedMessage?: string;
};

export default function RoleSelect({
    roles,
    defaultValue,
    error,
    locked = false,
    lockedMessage,
}: RoleSelectProps) {
    return (
        <div className="grid gap-2">
            <Label htmlFor="role">Rol</Label>

            <Select name="role" defaultValue={defaultValue} disabled={locked}>
                <SelectTrigger id="role" className="w-full">
                    <SelectValue placeholder="Seleccioná un rol" />
                </SelectTrigger>

                <SelectContent>
                    {roles.map((role) => (
                        <SelectItem key={role.value} value={role.value}>
                            <span className="flex flex-col items-start">
                                <span>{role.label}</span>
                                <span className="text-xs text-muted-foreground">
                                    {role.description}
                                </span>
                            </span>
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {locked && defaultValue && (
                <input type="hidden" name="role" value={defaultValue} />
            )}

            {locked && lockedMessage && (
                <p className="text-sm text-muted-foreground">{lockedMessage}</p>
            )}

            <InputError message={error} />
        </div>
    );
}
