import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { devolver, series } from '@/routes/bodega';
import type { VentaPanel } from '@/types';

/**
 * GUARDAR SERIES (una por producto) y DEVOLVER AL ASESOR de bodega.
 */
export default function AccionesBodega({
    venta,
    onClose,
}: {
    venta: VentaPanel;
    onClose: () => void;
}) {
    const [devolviendo, setDevolviendo] = useState(false);
    const formSeries = useForm({
        series: Object.fromEntries(
            venta.productos.map((producto) => [
                String(producto.id),
                producto.serial ?? '',
            ]),
        ) as Record<string, string>,
    });
    const formDevolucion = useForm({ motivo_devolucion: '' });
    const errores = formSeries.errors as Record<string, string>;

    function guardarSeries(event: FormEvent) {
        event.preventDefault();
        formSeries.post(series(venta.id).url, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    }

    function confirmarDevolucion(event: FormEvent) {
        event.preventDefault();
        formDevolucion.post(devolver(venta.id).url, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    }

    if (devolviendo) {
        return (
            <form onSubmit={confirmarDevolucion} className="grid gap-3">
                <Label htmlFor="motivo_devolucion">
                    ¿Qué debe corregir el asesor?
                </Label>
                <textarea
                    id="motivo_devolucion"
                    autoFocus
                    required
                    rows={3}
                    maxLength={1000}
                    value={formDevolucion.data.motivo_devolucion}
                    onChange={(e) =>
                        formDevolucion.setData(
                            'motivo_devolucion',
                            e.target.value,
                        )
                    }
                    placeholder="Ej. La referencia no corresponde al artículo"
                    className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                />
                <InputError message={formDevolucion.errors.motivo_devolucion} />
                <InputError
                    message={
                        (formDevolucion.errors as Record<string, string>).venta
                    }
                />
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setDevolviendo(false)}
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        variant="destructive"
                        disabled={formDevolucion.processing}
                    >
                        {formDevolucion.processing && <Spinner />}
                        CONFIRMAR DEVOLUCIÓN
                    </Button>
                </DialogFooter>
            </form>
        );
    }

    return (
        <form onSubmit={guardarSeries} className="grid gap-3">
            <p className="text-sm font-medium">Serie de cada producto</p>
            <div className="grid max-h-56 gap-3 overflow-y-auto pr-1">
                {venta.productos.map((producto, posicion) => (
                    <div key={producto.id} className="grid gap-1">
                        <Label htmlFor={`serie-${producto.id}`}>
                            {producto.articulo} · {producto.marca}{' '}
                            {producto.referencia}
                        </Label>
                        <Input
                            id={`serie-${producto.id}`}
                            autoFocus={posicion === 0}
                            required
                            maxLength={100}
                            value={formSeries.data.series[String(producto.id)]}
                            onChange={(e) =>
                                formSeries.setData('series', {
                                    ...formSeries.data.series,
                                    [String(producto.id)]: e.target.value,
                                })
                            }
                            aria-invalid={Boolean(
                                errores[`series.${producto.id}`],
                            )}
                        />
                        <InputError
                            message={errores[`series.${producto.id}`]}
                        />
                    </div>
                ))}
            </div>
            <InputError message={errores.series ?? errores.venta} />
            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => setDevolviendo(true)}
                >
                    DEVOLVER AL ASESOR
                </Button>
                <Button
                    type="submit"
                    disabled={formSeries.processing}
                    className="bg-amber-500 text-white hover:bg-amber-600"
                >
                    {formSeries.processing && <Spinner />}
                    GUARDAR SERIES
                </Button>
            </DialogFooter>
        </form>
    );
}
