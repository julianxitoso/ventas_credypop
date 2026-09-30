import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { caida } from '@/routes/ventas';
import type { VentaReciente } from '@/types';

type Props = {
    venta: VentaReciente | null;
    onClose: () => void;
};

/**
 * Pide el motivo para marcar una venta como caída.
 */
export default function MarcarCaidaDialog({ venta, onClose }: Props) {
    return (
        <Dialog
            open={venta !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                {venta && (
                    <FormularioCaida
                        key={venta.id}
                        venta={venta}
                        onClose={onClose}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function FormularioCaida({
    venta,
    onClose,
}: {
    venta: VentaReciente;
    onClose: () => void;
}) {
    const form = useForm({ motivo_caida: '' });

    function confirmar(event: FormEvent) {
        event.preventDefault();
        form.post(caida(venta.id).url, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    }

    return (
        <form onSubmit={confirmar} className="grid gap-4">
            <DialogHeader>
                <DialogTitle>
                    Marcar VENTA #{venta.consecutivo} como caída
                </DialogTitle>
                <DialogDescription>
                    {venta.cliente_nombre} · {venta.articulo}. Esta acción no se
                    puede deshacer.
                </DialogDescription>
            </DialogHeader>

            <div className="grid gap-2">
                <Label htmlFor="motivo_caida">Motivo</Label>
                <textarea
                    id="motivo_caida"
                    autoFocus
                    required
                    rows={3}
                    maxLength={1000}
                    value={form.data.motivo_caida}
                    onChange={(e) =>
                        form.setData('motivo_caida', e.target.value)
                    }
                    placeholder="Ej. El cliente desistió de la compra"
                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                />
                <InputError message={form.errors.motivo_caida} />
                <InputError
                    message={(form.errors as Record<string, string>).venta}
                />
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" onClick={onClose}>
                    Cancelar
                </Button>
                <Button
                    type="submit"
                    variant="destructive"
                    disabled={form.processing}
                >
                    {form.processing && <Spinner />}
                    CONFIRMAR VENTA CAÍDA
                </Button>
            </DialogFooter>
        </form>
    );
}
