import { Badge } from '@/components/ui/badge';
import type { UserRole } from '@/types';

const roleVariants: Record<
    UserRole,
    { label: string; variant: 'default' | 'secondary' | 'outline' }
> = {
    admin: { label: 'Administrador', variant: 'default' },
    vendedor: { label: 'Vendedor', variant: 'secondary' },
    equipo: { label: 'Equipo', variant: 'outline' },
};

export default function RoleBadge({ role }: { role: UserRole }) {
    const { label, variant } = roleVariants[role];

    return <Badge variant={variant}>{label}</Badge>;
}
