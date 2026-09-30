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
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
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
            <DialogHeader>
                <div className="flex flex-wrap items-center gap-3">
                    <DialogTitle>VENTA #{venta.consecutivo}</DialogTitle>
                    <EstadoVentaBadge estado={venta.estado} />
                </div>
                <DialogDescription>
                    Registrada el {venta.fecha} por {venta.asesor}
                </DialogDescription>
            </DialogHeader>

            <div className="grid gap-5 text-sm">
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
                    <Dato etiqueta="Correo">{venta.cliente_correo || '—'}</Dato>
                </Grupo>

                <Grupo titulo="Artículo">
                    <Dato etiqueta="Artículo">{venta.articulo}</Dato>
                    <Dato etiqueta="Marca">{venta.marca}</Dato>
                    <Dato etiqueta="Referencia">{venta.referencia}</Dato>
                    <Dato etiqueta="Serial">{venta.serial}</Dato>
                </Grupo>

                <Grupo titulo="Valores">
                    <Dato etiqueta="Valor de venta">
                        {formatearPesos(venta.valor_venta)}
                    </Dato>
                    <Dato etiqueta="Valor de inicial">
                        {formatearPesos(venta.valor_inicial)}
                    </Dato>
                    <Dato etiqueta="Convenio">{venta.convenio}</Dato>
                    {venta.gasto_administrativo !== null && (
                        <Dato etiqueta="Gasto administrativo">
                            {formatearPesos(venta.gasto_administrativo)}
                        </Dato>
                    )}
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

            {puedeProcesar && accion === 'detalle' && (
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

            {puedeProcesar && accion === 'facturar' && (
                <form
                    onSubmit={confirmarFacturacion}
                    className="grid gap-3 rounded-lg border p-4"
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
            )}

            {puedeProcesar && accion === 'devolver' && (
                <form
                    onSubmit={confirmarDevolucion}
                    className="grid gap-3 rounded-lg border p-4"
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
                        message={formDevolucion.errors.motivo_devolucion}
                    />
                    <InputError
                        message={
                            (formDevolucion.errors as Record<string, string>)
                                .venta
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
