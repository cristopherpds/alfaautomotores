export type UserRole = 'admin' | 'vendedor' | 'equipo';

export type RoleOption = {
    value: UserRole;
    label: string;
    description: string;
};

export type User = {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type ManagedUser = {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    created_at: string | null;
};

export type Auth = {
    user: User;
};
