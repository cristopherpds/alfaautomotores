import { Form } from '@inertiajs/react';
import { MessageCircle } from 'lucide-react';
import ConversacionController from '@/actions/App/Http/Controllers/Panel/ConversacionController';
import { Button } from '@/components/ui/button';

/**
 * Abre en la bandeja la conversación del cliente (o la crea si nunca
 * escribió), así lo que se le manda queda registrado y no sale por un
 * `wa.me` del celular de quien atiende.
 */
export default function EscribirPorWhatsapp({
    clienteId,
    variant = 'outline',
    className,
}: {
    clienteId: number;
    variant?: 'default' | 'outline' | 'secondary';
    className?: string;
}) {
    return (
        <Form
            {...ConversacionController.abrir.form(clienteId)}
            className={className}
        >
            {({ processing }) => (
                <Button
                    type="submit"
                    variant={variant}
                    disabled={processing}
                    className="w-full"
                    data-test="escribir-por-whatsapp-button"
                >
                    <MessageCircle />
                    Escribir por WhatsApp
                </Button>
            )}
        </Form>
    );
}
