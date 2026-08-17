import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    Copy,
    Crown,
    MoreHorizontal,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import UserController from '@/actions/App/Http/Controllers/UserController';
import Heading from '@/components/heading';
import RoleBadge from '@/components/role-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useClipboard } from '@/hooks/use-clipboard';
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

function UserRowActions({ user }: { user: ManagedUser }) {
    /* El diálogo vive fuera del menú: si colgara del contenido del menú se
       desmontaría junto con él al elegir la opción. */
    const [confirmando, setConfirmando] = useState(false);
    const [, copy] = useClipboard();

    const copiarEmail = async () => {
        if (await copy(user.email)) {
            toast.success('Email copiado.');
        }
    };

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        data-test={`user-${user.id}-actions`}
                    >
                        <MoreHorizontal />
                        <span className="sr-only">
                            Acciones para {user.name}
                        </span>
                    </Button>
                </DropdownMenuTrigger>

                <DropdownMenuContent align="end">
                    <DropdownMenuLabel>Acciones</DropdownMenuLabel>

                    <DropdownMenuGroup>
                        <DropdownMenuItem onSelect={() => void copiarEmail()}>
                            <Copy />
                            Copiar email
                        </DropdownMenuItem>

                        {user.can.update && (
                            <DropdownMenuItem asChild>
                                <Link
                                    href={UserController.edit(user.id)}
                                    data-test={`edit-user-${user.id}-link`}
                                >
                                    <Pencil />
                                    Editar usuario
                                </Link>
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuGroup>

                    {user.can.delete && (
                        <>
                            <DropdownMenuSeparator />

                            <DropdownMenuGroup>
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={() => setConfirmando(true)}
                                    data-test={`delete-user-${user.id}-button`}
                                >
                                    <Trash2 />
                                    Eliminar usuario
                                </DropdownMenuItem>
                            </DropdownMenuGroup>
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog open={confirmando} onOpenChange={setConfirmando}>
                <DialogContent>
                    <DialogTitle>¿Eliminar a {user.name}?</DialogTitle>
                    <DialogDescription>
                        El usuario perderá el acceso al sistema de inmediato.
                        Esta acción no se puede deshacer.
                    </DialogDescription>

                    <Form
                        {...UserController.destroy.form(user.id)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setConfirmando(false)}
                    >
                        {({ processing }) => (
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        Cancelar
                                    </Button>
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
        </>
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
                            <Plus />
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
                                <TableHead className="w-12 px-4">
                                    <span className="sr-only">Acciones</span>
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
                                        <div className="flex flex-wrap items-center gap-2">
                                            <RoleBadge role={user.role} />

                                            {user.is_owner && (
                                                <Badge variant="outline">
                                                    <Crown />
                                                    Dueño
                                                </Badge>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDate(user.created_at)}
                                    </TableCell>
                                    <TableCell className="px-4 text-right">
                                        <UserRowActions user={user} />
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
