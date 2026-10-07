import type { ReactNode } from 'react';
import EstadoVentaBadge from '@/components/estado-venta-badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatearPesos } from '@/lib/formato';
import type { VentaPanel } from '@/types';

type Props = {
    venta: VentaPanel | null;
    onClose: () => void;
    /** Acciones de la parte inferior (facturar, asignar series…), si las hay. */
    acciones?: (venta: VentaPanel, onClose: () => void) => ReactNode;
};

/**
 * Detalle de una venta. Las acciones las pone cada pantalla (panel o bodega).
 */
export default function DetalleVentaDialog({
    venta,
    onClose,
    acciones,
}: Props) {
    return (
        <Dialog
            open={venta !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            {/* Encabezado y acciones fijos; solo los datos se desplazan, para que
                los botones se vean siempre, aunque la pantalla sea baja. */}
            <DialogContent className="flex max-h-[90vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-2xl">
                {venta && (
                    <ContenidoDetalle
                        key={venta.id}
                        venta={venta}
                        acciones={acciones?.(venta, onClose)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function ContenidoDetalle({
    venta,
    acciones,
}: {
    venta: VentaPanel;
    acciones: ReactNode;
}) {
    const porFacturar =
        venta.estado === 'pendiente' || venta.estado === 'con_serie';

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
                {porFacturar && venta.corregida_en && (
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
                    {venta.series_asignadas_en && (
                        <p className="text-muted-foreground">
                            Series asignadas por bodega el{' '}
                            {venta.series_asignadas_en}
                            {venta.series_asignadas_por &&
                                ` · ${venta.series_asignadas_por}`}
                        </p>
                    )}
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
                        <p className="rounded-md bg-purple-50 p-2 whitespace-pre-line text-purple-900 dark:bg-purple-950 dark:text-purple-100">
                            {venta.motivo_devolucion}
                        </p>
                        <p className="text-muted-foreground">
                            {tipoDeError(venta)}
                            {venta.devuelta_a === 'bodega'
                                ? 'La corrige bodega'
                                : 'La corrige el asesor'}
                            {' · '}
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

            {acciones && (
                <div className="border-t bg-background px-6 py-4">
                    {acciones}
                </div>
            )}
        </>
    );
}

/**
 * "Error en la serie · ", "Error en otros datos · " o ambos.
 */
function tipoDeError(venta: VentaPanel): string {
    const errores = [
        venta.devolucion_por_serie && 'la serie',
        venta.devolucion_por_otros && 'otros datos',
    ].filter(Boolean);

    return errores.length > 0 ? `Error en ${errores.join(' y ')} · ` : '';
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
