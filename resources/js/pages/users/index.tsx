import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import Heading from '@/components/heading';
import RoleBadge from '@/components/role-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { create, index } from '@/routes/users';
import type { ManagedUser } from '@/types';

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('es-AR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

function DeleteUserDialog({ user }: { user: ManagedUser }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Eliminar a ${user.name}`}
                    data-test={`delete-user-${user.id}-button`}
                >
                    <Trash2 className="size-4 text-destructive" />
                </Button>
            </DialogTrigger>

            <DialogContent>
                <DialogTitle>¿Eliminar a {user.name}?</DialogTitle>
                <DialogDescription>
                    El usuario perderá el acceso al sistema de inmediato. Esta
                    acción no se puede deshacer.
                </DialogDescription>

                <Form
                    {...UserController.destroy.form(user.id)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">Cancelar</Button>
                            </DialogClose>

                            <Button
                                variant="destructive"
                                disabled={processing}
                                asChild
                            >
                                <button
                                    type="submit"
                                    data-test={`confirm-delete-user-${user.id}-button`}
                                >
                                    Eliminar
                                </button>
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function UsersIndex({ users }: { users: ManagedUser[] }) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Usuarios" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Usuarios"
                        description="Registrá usuarios y asigná su rol dentro del sistema"
                    />

                    <Button asChild data-test="create-user-button">
                        <Link href={create()}>
                            <Plus className="size-4" />
                            Nuevo usuario
                        </Link>
                    </Button>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="px-4">Nombre</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Rol</TableHead>
                                <TableHead>Alta</TableHead>
                                <TableHead className="px-4 text-right">
                                    Acciones
                                </TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            {users.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        Todavía no hay usuarios registrados.
                                    </TableCell>
                                </TableRow>
                            )}

                            {users.map((user) => (
                                <TableRow key={user.id}>
                                    <TableCell className="px-4 font-medium">
                                        {user.name}
                                        {auth.user?.id === user.id && (
                                            <span className="ml-2 text-xs text-muted-foreground">
                                                (vos)
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {user.email}
                                    </TableCell>
                                    <TableCell>
                                        <RoleBadge role={user.role} />
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDate(user.created_at)}
                                    </TableCell>
                                    <TableCell className="px-4 text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                            >
                                                <Link
                                                    href={UserController.edit(
                                                        user.id,
                                                    )}
                                                    aria-label={`Editar a ${user.name}`}
                                                    data-test={`edit-user-${user.id}-link`}
                                                >
                                                    <Pencil className="size-4" />
                                                </Link>
                                            </Button>

                                            {auth.user?.id !== user.id && (
                                                <DeleteUserDialog user={user} />
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Usuarios',
            href: index(),
        },
    ],
};
