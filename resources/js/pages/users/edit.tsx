import { Form, Head, Link, usePage } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import RoleSelect from '@/components/role-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/users';
import type { ManagedUser, RoleOption } from '@/types';

type Props = {
    user: ManagedUser;
    roles: RoleOption[];
    passwordRules: string;
};

export default function EditUser({ user, roles, passwordRules }: Props) {
    const { auth } = usePage().props;
    const isCurrentUser = auth.user?.id === user.id;

    return (
        <>
            <Head title={`Editar ${user.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Editar usuario"
                    description="Actualizá los datos, el rol o la contraseña del usuario"
                />

                <Form
                    {...UserController.update.form(user.id)}
                    resetOnSuccess={['password', 'password_confirmation']}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nombre</Label>

                                <Input
                                    id="name"
                                    name="name"
                                    className="mt-1 block w-full"
                                    defaultValue={user.name}
                                    required
                                    autoComplete="name"
                                    placeholder="Nombre y apellido"
                                />

                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>

                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    className="mt-1 block w-full"
                                    defaultValue={user.email}
                                    required
                                    autoComplete="off"
                                    placeholder="nombre@alfaautomotores.com"
                                />

                                <InputError message={errors.email} />
                            </div>

                            <RoleSelect
                                roles={roles}
                                defaultValue={user.role}
                                error={errors.role}
                                locked={isCurrentUser}
                                lockedMessage="No podés cambiar tu propio rol de administrador."
                            />

                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    Nueva contraseña
                                </Label>

                                <PasswordInput
                                    id="password"
                                    name="password"
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    placeholder="Dejalo vacío para no cambiarla"
                                    passwordrules={passwordRules}
                                />

                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirmar contraseña
                                </Label>

                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    placeholder="Confirmar contraseña"
                                    passwordrules={passwordRules}
                                />

                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-user-button"
                                >
                                    Guardar cambios
                                </Button>

                                <Button variant="ghost" asChild>
                                    <Link href={index()}>Cancelar</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

EditUser.layout = {
    breadcrumbs: [
        {
            title: 'Usuarios',
            href: index(),
        },
        {
            title: 'Editar usuario',
            href: index(),
        },
    ],
};
