import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { CircleCheck, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import EstadoVentaBadge from '@/components/estado-venta-badge';
import FormularioVenta, { ventaVacia } from '@/components/formulario-venta';
import MarcarCaidaDialog from '@/components/marcar-caida-dialog';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { formatearPesos } from '@/lib/formato';
import { create, edit, store } from '@/routes/ventas';
import type { Convenio, VentaReciente } from '@/types';

type Props = {
    convenios: Convenio[];
    porCorregir: VentaReciente[];
    misVentas: VentaReciente[];
};

export default function RegistrarVenta({
    convenios,
    porCorregir,
    misVentas,
}: Props) {
    const { flash } = usePage();
    const form = useForm(ventaVacia);
    const [ventaACaer, setVentaACaer] = useState<VentaReciente | null>(null);

    function registrar(event: FormEvent) {
        event.preventDefault();

        form.post(store().url, {
            onSuccess: () => form.reset(),
        });
    }

    return (
        <>
            <Head title="Registrar venta" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
                <h1 className="text-2xl font-bold">Registrar venta</h1>

                {flash.ventaRegistrada && (
                    <Alert className="border-green-300 bg-green-50 text-green-900 dark:border-green-800 dark:bg-green-950 dark:text-green-100">
                        <CircleCheck />
                        <AlertTitle>Venta registrada correctamente</AlertTitle>
                        <AlertDescription className="text-lg font-semibold text-green-900 dark:text-green-100">
                            VENTA #{flash.ventaRegistrada}
                        </AlertDescription>
                    </Alert>
                )}

                {porCorregir.length > 0 && (
                    <section className="flex flex-col gap-3 rounded-xl border border-red-300 bg-red-50/60 p-4 dark:border-red-900 dark:bg-red-950/30">
                        <h2 className="flex items-center gap-2 font-semibold text-red-800 dark:text-red-200">
                            <TriangleAlert className="size-5" />
                            Devueltas por corregir ({porCorregir.length})
                        </h2>
                        <ul className="flex flex-col gap-3">
                            {porCorregir.map((venta) => (
                                <TarjetaVenta
                                    key={venta.id}
                                    venta={venta}
                                    onMarcarCaida={setVentaACaer}
                                />
                            ))}
                        </ul>
                    </section>
                )}

                <FormularioVenta
                    form={form}
                    convenios={convenios}
                    textoBoton="REGISTRAR VENTA"
                    onSubmit={registrar}
                />

                <section className="flex flex-col gap-3">
                    <h2 className="text-lg font-semibold">
                        Mis ventas recientes
                    </h2>

                    {misVentas.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Todavía no has registrado ventas.
                        </p>
                    ) : (
                        <ul className="flex flex-col gap-3">
                            {misVentas.map((venta) => (
                                <TarjetaVenta
                                    key={venta.id}
                                    venta={venta}
                                    onMarcarCaida={setVentaACaer}
                                />
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <MarcarCaidaDialog
                venta={ventaACaer}
                onClose={() => setVentaACaer(null)}
            />
        </>
    );
}

function TarjetaVenta({
    venta,
    onMarcarCaida,
}: {
    venta: VentaReciente;
    onMarcarCaida: (venta: VentaReciente) => void;
}) {
    return (
        <li className="rounded-xl border bg-card p-4 text-sm shadow-xs">
            <div className="flex items-start justify-between gap-2">
                <div>
                    <p className="font-semibold">VENTA #{venta.consecutivo}</p>
                    <p className="text-muted-foreground">{venta.fecha}</p>
                </div>
                <div className="flex flex-col items-end gap-1">
                    <EstadoVentaBadge estado={venta.estado} />
                    {venta.corregida && venta.estado === 'pendiente' && (
                        <span className="text-xs text-muted-foreground">
                            Corregida
                        </span>
                    )}
                </div>
            </div>

            <p className="mt-2">
                {venta.cliente_nombre} · {venta.articulo}
            </p>
            <p className="font-medium">{formatearPesos(venta.valor_venta)}</p>

            {venta.estado === 'facturada' && venta.numero_factura && (
                <p className="mt-1 text-muted-foreground">
                    Factura: {venta.numero_factura}
                </p>
            )}

            {venta.estado === 'devuelta' && venta.motivo_devolucion && (
                <p className="mt-2 rounded-md bg-red-50 p-2 text-red-800 dark:bg-red-950 dark:text-red-200">
                    <span className="font-medium">Motivo de devolución:</span>{' '}
                    {venta.motivo_devolucion}
                </p>
            )}

            {venta.estado === 'caida' && venta.motivo_caida && (
                <p className="mt-2 rounded-md bg-muted p-2 text-muted-foreground">
                    <span className="font-medium">Motivo de caída:</span>{' '}
                    {venta.motivo_caida}
                </p>
            )}

            {(venta.estado === 'devuelta' || venta.puede_marcar_caida) && (
                <div className="mt-3 flex flex-wrap justify-end gap-2">
                    {venta.puede_marcar_caida && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => onMarcarCaida(venta)}
                        >
                            MARCAR COMO CAÍDA
                        </Button>
                    )}
                    {venta.estado === 'devuelta' && (
                        <Button size="sm" asChild>
                            <Link href={edit(venta.id)}>CORREGIR</Link>
                        </Button>
                    )}
                </div>
            )}
        </li>
    );
}

RegistrarVenta.layout = {
    breadcrumbs: [{ title: 'Registrar venta', href: create() }],
};
