import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import EstadoVentaBadge from '@/components/estado-venta-badge';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatearPesos } from '@/lib/formato';
import { devolver, facturar } from '@/routes/ventas';
import type { VentaPanel } from '@/types';

type Props = {
    venta: VentaPanel | null;
    puedeFacturar: boolean;
    onClose: () => void;
};

/**
 * Detalle de una venta con las acciones de facturar y devolver.
 */
export default function DetalleVentaDialog({
    venta,
    puedeFacturar,
    onClose,
}: Props) {
    return (
        <Dialog
            open={venta !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            {/* Encabezado y acciones fijos; solo los datos se desplazan, para que
                FACTURAR y DEVOLVER se vean siempre, aunque la pantalla sea baja. */}
            <DialogContent className="flex max-h-[90vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-2xl">
                {venta && (
                    <ContenidoDetalle
                        key={venta.id}
                        venta={venta}
                        puedeFacturar={puedeFacturar}
                        onClose={onClose}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

type Accion = 'detalle' | 'facturar' | 'devolver';

function ContenidoDetalle({
    venta,
    puedeFacturar,
    onClose,
}: {
    venta: VentaPanel;
    puedeFacturar: boolean;
    onClose: () => void;
}) {
    const [accion, setAccion] = useState<Accion>('detalle');
    const formFactura = useForm({ numero_factura: '' });
    const formDevolucion = useForm({ motivo_devolucion: '' });

    const puedeProcesar = puedeFacturar && venta.estado === 'pendiente';

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

    return (
        <>
            <DialogHeader className="border-b px-6 pt-6 pr-12 pb-4">
                <div className="flex flex-wrap items-center gap-3">
                    <DialogTitle>VENTA #{venta.consecutivo}</DialogTitle>
                    <EstadoVentaBadge estado={venta.estado} />
                </div>
                <DialogDescription>
                    Registrada el {venta.fecha} por {venta.asesor}
                </DialogDescription>
            </DialogHeader>

            <div className="grid min-h-0 flex-1 gap-5 overflow-y-auto px-6 py-4 text-sm">
                {venta.estado === 'pendiente' && venta.corregida_en && (
                    <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">
                        <p className="font-medium">
                            Corregida por el asesor el {venta.corregida_en}
                        </p>
                        {venta.motivo_devolucion && (
                            <p className="mt-1 whitespace-pre-line">
                                Se había devuelto por: {venta.motivo_devolucion}
                            </p>
                        )}
                    </div>
                )}

                <Grupo titulo="Cliente">
                    <Dato etiqueta="Nombres">{venta.cliente_nombre}</Dato>
                    <Dato etiqueta="Cédula">{venta.cliente_cedula}</Dato>
                    <Dato etiqueta="Celular">{venta.cliente_celular}</Dato>
                    <Dato etiqueta="Teléfono">
                        {venta.cliente_telefono || '—'}
                    </Dato>
                    {venta.cliente_celular_2 && (
                        <Dato etiqueta="Otro celular">
                            {venta.cliente_celular_2}
                        </Dato>
                    )}
                    <Dato etiqueta="Correo">{venta.cliente_correo || '—'}</Dato>
                    <Dato etiqueta="Dirección">
                        {venta.cliente_direccion || '—'}
                    </Dato>
                    <Dato etiqueta="Ciudad">{venta.cliente_ciudad || '—'}</Dato>
                </Grupo>

                <Grupo
                    titulo={`Productos (${venta.productos.length})`}
                    columnas={1}
                >
                    <div className="overflow-x-auto rounded-md border">
                        <table className="w-full text-sm tabular-nums">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr>
                                    <th className="px-3 py-2 font-medium">
                                        Artículo
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Serie
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        Valor
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {venta.productos.map((producto) => (
                                    <tr key={producto.id} className="border-t">
                                        <td className="px-3 py-2">
                                            <p className="font-medium">
                                                {producto.articulo}
                                            </p>
                                            <p className="text-muted-foreground">
                                                {producto.marca} ·{' '}
                                                {producto.referencia}
                                            </p>
                                        </td>
                                        <td className="px-3 py-2">
                                            {producto.serial ?? (
                                                <span className="text-muted-foreground">
                                                    Sin serie
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            {formatearPesos(producto.valor)}
                                        </td>
                                    </tr>
                                ))}
                                <tr className="border-t bg-muted/30 font-semibold">
                                    <td className="px-3 py-2" colSpan={2}>
                                        Total
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {formatearPesos(venta.valor_venta)}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Grupo>

                <Grupo titulo="Cuota inicial y convenios">
                    <Dato etiqueta="Valor de inicial">
                        {formatearPesos(venta.valor_inicial)}
                    </Dato>
                    {venta.forma_pago_inicial && (
                        <Dato etiqueta="Pago de la inicial">
                            {venta.forma_pago_inicial}
                            {venta.numero_transferencia &&
                                ` · N.º ${venta.numero_transferencia}`}
                        </Dato>
                    )}
                    {venta.convenios.map((convenio) => (
                        <Dato key={convenio.nombre} etiqueta={convenio.nombre}>
                            {formatearPesos(convenio.valor)}
                            {convenio.gasto_administrativo !== null &&
                                ` · Gasto administrativo ${formatearPesos(convenio.gasto_administrativo)}`}
                        </Dato>
                    ))}
                </Grupo>

                {venta.observaciones && (
                    <Grupo titulo="Observaciones" columnas={1}>
                        <p className="whitespace-pre-line">
                            {venta.observaciones}
                        </p>
                    </Grupo>
                )}

                {venta.estado === 'facturada' && (
                    <Grupo titulo="Facturación">
                        <Dato etiqueta="Número de factura">
                            {venta.numero_factura}
                        </Dato>
                        <Dato etiqueta="Facturada">
                            {venta.facturada_en} · {venta.facturada_por ?? '—'}
                        </Dato>
                    </Grupo>
                )}

                {venta.estado === 'devuelta' && (
                    <Grupo titulo="Devolución" columnas={1}>
                        <p className="rounded-md bg-red-50 p-2 whitespace-pre-line text-red-800 dark:bg-red-950 dark:text-red-200">
                            {venta.motivo_devolucion}
                        </p>
                        <p className="text-muted-foreground">
                            {venta.devuelta_en} · {venta.devuelta_por ?? '—'}
                        </p>
                    </Grupo>
                )}

                {venta.estado === 'caida' && (
                    <Grupo titulo="Venta caída" columnas={1}>
                        <p className="rounded-md bg-muted p-2 whitespace-pre-line">
                            {venta.motivo_caida}
                        </p>
                        <p className="text-muted-foreground">
                            Marcada por el asesor el {venta.caida_en}
                        </p>
                    </Grupo>
                )}
            </div>

            {puedeProcesar && (
                <div className="border-t bg-background px-6 py-4">
                    {accion === 'detalle' && (
                        <DialogFooter>
                            <Button
                                variant="destructive"
                                onClick={() => setAccion('devolver')}
                            >
                                DEVOLVER / CORREGIR
                            </Button>
                            <Button
                                className="bg-green-600 text-white hover:bg-green-700"
                                onClick={() => setAccion('facturar')}
                            >
                                FACTURAR
                            </Button>
                        </DialogFooter>
                    )}

                    {accion === 'facturar' && (
                        <form
                            onSubmit={confirmarFacturacion}
                            className="grid gap-3"
                        >
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
                                    formFactura.setData(
                                        'numero_factura',
                                        e.target.value,
                                    )
                                }
                                aria-invalid={Boolean(
                                    formFactura.errors.numero_factura,
                                )}
                            />
                            <InputError
                                message={formFactura.errors.numero_factura}
                            />
                            <InputError
                                message={
                                    (
                                        formFactura.errors as Record<
                                            string,
                                            string
                                        >
                                    ).venta
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
                    )}

                    {accion === 'devolver' && (
                        <form
                            onSubmit={confirmarDevolucion}
                            className="grid gap-3"
                        >
                            <Label htmlFor="motivo_devolucion">
                                Motivo de la devolución
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
                                placeholder="Ej. El serial no coincide con la factura del proveedor"
                                className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                            />
                            <InputError
                                message={
                                    formDevolucion.errors.motivo_devolucion
                                }
                            />
                            <InputError
                                message={
                                    (
                                        formDevolucion.errors as Record<
                                            string,
                                            string
                                        >
                                    ).venta
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
                    )}
                </div>
            )}
        </>
    );
}

function Grupo({
    titulo,
    columnas = 2,
    children,
}: {
    titulo: string;
    columnas?: 1 | 2;
    children: ReactNode;
}) {
    return (
        <section className="grid gap-2">
            <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {titulo}
            </h3>
            <div
                className={
                    columnas === 2 ? 'grid gap-2 sm:grid-cols-2' : 'grid gap-2'
                }
            >
                {children}
            </div>
        </section>
    );
}

function Dato({
    etiqueta,
    children,
}: {
    etiqueta: string;
    children: ReactNode;
}) {
    return (
        <div>
            <p className="text-muted-foreground">{etiqueta}</p>
            <p className="font-medium break-words">{children}</p>
        </div>
    );
}
