import {
    Button,
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from 'alfa-ui';

export const ConfirmarBorrado = () => (
    <Dialog defaultOpen>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>¿Eliminar a Lucía Ferreira?</DialogTitle>
                <DialogDescription>
                    Se borra la cuenta y deja de poder entrar al panel. Esta acción no se puede deshacer.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <DialogClose asChild>
                    <Button variant="secondary">Cancelar</Button>
                </DialogClose>
                <Button variant="destructive">Eliminar usuario</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
);
