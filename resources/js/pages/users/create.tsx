import { Form, Head, Link } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import RoleSelect from '@/components/role-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, index } from '@/routes/users';
import type { RoleOption } from '@/types';

type Props = {
    roles: RoleOption[];
    passwordRules: string;
};

export default function CreateUser({ roles, passwordRules }: Props) {
    return (
        <>
            <Head title="Nuevo usuario" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Nuevo usuario"
                    description="Registrá un usuario y asignale su rol"
                />

                <Form
                    {...UserController.store.form()}
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
                                    required
                                    autoFocus
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
                                    required
                                    autoComplete="off"
                                    placeholder="nombre@alfaautomotores.com"
                                />

                                <InputError message={errors.email} />
                            </div>

                            <RoleSelect roles={roles} error={errors.role} />

                            <div className="grid gap-2">
                                <Label htmlFor="password">Contraseña</Label>

                                <PasswordInput
                                    id="password"
                                    name="password"
                                    className="mt-1 block w-full"
                                    required
                                    autoComplete="new-password"
                                    placeholder="Contraseña"
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
                                    required
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
                                    data-test="store-user-button"
                                >
                                    Crear usuario
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

CreateUser.layout = {
    breadcrumbs: [
        {
            title: 'Usuarios',
            href: index(),
        },
        {
            title: 'Nuevo usuario',
            href: create(),
        },
    ],
};
