import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { devolver, facturar } from '@/routes/ventas';
import type { VentaPanel } from '@/types';

type Accion = 'detalle' | 'facturar' | 'devolver';

/**
 * FACTURAR y DEVOLVER del facturador. Solo se puede actuar sobre ventas con
 * serie; las que esperan a bodega muestran un aviso.
 */
export default function AccionesFacturacion({
    venta,
    onClose,
}: {
    venta: VentaPanel;
    onClose: () => void;
}) {
    const [accion, setAccion] = useState<Accion>('detalle');
    const formFactura = useForm({ numero_factura: '' });
    const formDevolucion = useForm({
        motivo_devolucion: '',
        error_serie: false,
        error_otros: false,
    });

    if (venta.estado === 'pendiente') {
        return (
            <p className="text-sm text-muted-foreground">
                Esta venta todavía no tiene serie: primero debe asignarla
                bodega.
            </p>
        );
    }

    if (venta.estado !== 'con_serie') {
        return null;
    }

    function confirmarFacturacion(event: FormEvent) {
        event.preventDefault();
        formFactura.post(facturar(venta.id).url, {
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

    if (accion === 'facturar') {
        return (
            <form onSubmit={confirmarFacturacion} className="grid gap-3">
                <Label htmlFor="numero_factura">
                    Número de factura del ERP
                </Label>
                <Input
                    id="numero_factura"
                    autoFocus
                    required
                    maxLength={50}
                    value={formFactura.data.numero_factura}
                    onChange={(e) =>
                        formFactura.setData('numero_factura', e.target.value)
                    }
                    aria-invalid={Boolean(formFactura.errors.numero_factura)}
                />
                <InputError message={formFactura.errors.numero_factura} />
                <InputError
                    message={
                        (formFactura.errors as Record<string, string>).venta
                    }
                />
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setAccion('detalle')}
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        disabled={formFactura.processing}
                        className="bg-green-600 text-white hover:bg-green-700"
                    >
                        {formFactura.processing && <Spinner />}
                        CONFIRMAR FACTURACIÓN
                    </Button>
                </DialogFooter>
            </form>
        );
    }

    if (accion === 'devolver') {
        return (
            <form onSubmit={confirmarDevolucion} className="grid gap-3">
                <fieldset className="grid gap-2">
                    <legend className="mb-2 text-sm font-medium">
                        ¿Dónde está el error?
                    </legend>
                    <div className="flex flex-wrap gap-x-6 gap-y-2">
                        <Label className="font-normal">
                            <Checkbox
                                checked={formDevolucion.data.error_serie}
                                onCheckedChange={(marcado) =>
                                    formDevolucion.setData(
                                        'error_serie',
                                        marcado === true,
                                    )
                                }
                            />
                            Error en la serie
                        </Label>
                        <Label className="font-normal">
                            <Checkbox
                                checked={formDevolucion.data.error_otros}
                                onCheckedChange={(marcado) =>
                                    formDevolucion.setData(
                                        'error_otros',
                                        marcado === true,
                                    )
                                }
                            />
                            Error en otros datos
                        </Label>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        {destinoDevolucion(
                            formDevolucion.data.error_serie,
                            formDevolucion.data.error_otros,
                        )}
                    </p>
                    <InputError message={formDevolucion.errors.error_serie} />
                </fieldset>

                <Label htmlFor="motivo_devolucion">
                    Motivo de la devolución
                </Label>
                <textarea
                    id="motivo_devolucion"
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
                    placeholder="Ej. El serial no coincide con la factura del proveedor"
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
                        onClick={() => setAccion('detalle')}
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
        <DialogFooter>
            <Button variant="destructive" onClick={() => setAccion('devolver')}>
                DEVOLVER / CORREGIR
            </Button>
            <Button
                className="bg-green-600 text-white hover:bg-green-700"
                onClick={() => setAccion('facturar')}
            >
                FACTURAR
            </Button>
        </DialogFooter>
    );
}

/**
 * Explica a quién le llega la venta según los errores marcados.
 */
function destinoDevolucion(errorSerie: boolean, errorOtros: boolean): string {
    if (errorSerie && errorOtros) {
        return 'Va primero al asesor y, cuando la corrija, a bodega para revisar la serie.';
    }

    if (errorOtros) {
        return 'Va al asesor para que corrija los datos.';
    }

    if (errorSerie) {
        return 'Va a bodega para que corrija la serie.';
    }

    return 'Marca al menos una opción.';
}
