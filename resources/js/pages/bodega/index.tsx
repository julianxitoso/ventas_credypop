import { Head, router, usePoll } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import AccionesBodega from '@/components/acciones-bodega';
import DetalleVentaDialog from '@/components/detalle-venta-dialog';
import EstadoVentaBadge from '@/components/estado-venta-badge';
import Paginacion from '@/components/paginacion';
import { Input } from '@/components/ui/input';
import { formatearPesos } from '@/lib/formato';
import { cn } from '@/lib/utils';
import { bodega } from '@/routes';
import type { Paginado, VentaPanel } from '@/types';

type Props = {
    buscar: string;
    indicadores: {
        porAsignar: number;
        devueltas: number;
        asignadasHoy: number;
    };
    ventas: Paginado<VentaPanel>;
    ultimaVentaId: number;
    ultimaDevolucion: string | null;
    ultimaCorreccion: string | null;
};

const INTERVALO_ACTUALIZACION = 30_000;

/**
 * Ventas que esperan serie: bodega asigna la serie de cada producto o
 * devuelve la venta al asesor.
 */
export default function Bodega({
    buscar,
    indicadores,
    ventas,
    ultimaVentaId,
    ultimaDevolucion,
    ultimaCorreccion,
}: Props) {
    const [busqueda, setBusqueda] = useState(buscar);
    const [seleccionada, setSeleccionada] = useState<VentaPanel | null>(null);
    const vistoAntes = useRef({
        ultimaVentaId,
        ultimaDevolucion,
        ultimaCorreccion,
    });
    const porAtender = indicadores.porAsignar + indicadores.devueltas;

    usePoll(
        INTERVALO_ACTUALIZACION,
        {
            only: [
                'ventas',
                'indicadores',
                'ultimaVentaId',
                'ultimaDevolucion',
                'ultimaCorreccion',
            ],
            showProgress: false,
        },
        { keepAlive: true },
    );

    useEffect(() => {
        const antes = vistoAntes.current;

        if (ultimaVentaId > antes.ultimaVentaId) {
            avisar(
                'ventas-nuevas',
                'Llegaron ventas nuevas para asignar serie',
            );
        }

        if (
            ultimaDevolucion !== null &&
            ultimaDevolucion !== antes.ultimaDevolucion
        ) {
            avisar(
                'venta-devuelta',
                'El facturador devolvió una venta por la serie',
            );
        }

        if (
            ultimaCorreccion !== null &&
            ultimaCorreccion !== antes.ultimaCorreccion
        ) {
            avisar('venta-corregida', 'Una venta corregida volvió a bodega');
        }

        vistoAntes.current = {
            ultimaVentaId,
            ultimaDevolucion,
            ultimaCorreccion,
        };
    }, [ultimaVentaId, ultimaDevolucion, ultimaCorreccion]);

    useEffect(() => {
        if (busqueda === buscar) {
            return;
        }

        const espera = setTimeout(
            () =>
                router.get(bodega.url(), busqueda ? { buscar: busqueda } : {}, {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                }),
            400,
        );

        return () => clearTimeout(espera);
    }, [busqueda]);

    return (
        <>
            <Head
                title={porAtender > 0 ? `(${porAtender}) Bodega` : 'Bodega'}
            />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <h1 className="text-2xl font-bold">Bodega</h1>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Indicador
                        titulo="Sin serie"
                        valor={indicadores.porAsignar}
                        clases="text-red-600 dark:text-red-400"
                    />
                    <Indicador
                        titulo="Devueltas por serie"
                        valor={indicadores.devueltas}
                        clases="text-purple-600 dark:text-purple-400"
                    />
                    <Indicador
                        titulo="Series asignadas hoy"
                        valor={indicadores.asignadasHoy}
                        clases="text-amber-600 dark:text-amber-400"
                    />
                </div>

                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        value={busqueda}
                        onChange={(e) => setBusqueda(e.target.value)}
                        placeholder="Buscar por cliente, cédula, serial o número de venta"
                        className="pl-9"
                    />
                </div>

                {ventas.data.length === 0 ? (
                    <p className="rounded-xl border p-8 text-center text-sm text-muted-foreground">
                        {buscar
                            ? 'No hay ventas que coincidan con la búsqueda.'
                            : 'No hay ventas esperando serie.'}
                    </p>
                ) : (
                    <>
                        <ul className="grid gap-3 lg:grid-cols-2">
                            {ventas.data.map((venta) => (
                                <li key={venta.id}>
                                    <TarjetaVenta
                                        venta={venta}
                                        onSeleccionar={setSeleccionada}
                                    />
                                </li>
                            ))}
                        </ul>
                        <Paginacion pagina={ventas} />
                    </>
                )}
            </div>

            <DetalleVentaDialog
                venta={seleccionada}
                onClose={() => setSeleccionada(null)}
                acciones={(venta, onClose) => (
                    <AccionesBodega venta={venta} onClose={onClose} />
                )}
            />
        </>
    );
}

/**
 * Aviso que queda en pantalla hasta que bodega lo cierre.
 */
function avisar(id: string, mensaje: string) {
    toast.info(mensaje, { id, duration: Infinity, closeButton: true });
}

function Indicador({
    titulo,
    valor,
    clases,
}: {
    titulo: string;
    valor: number;
    clases: string;
}) {
    return (
        <div className="rounded-xl border bg-card p-4 shadow-xs">
            <p className="text-sm text-muted-foreground">{titulo}</p>
            <p className={cn('mt-1 text-2xl font-semibold', clases)}>{valor}</p>
        </div>
    );
}

function TarjetaVenta({
    venta,
    onSeleccionar,
}: {
    venta: VentaPanel;
    onSeleccionar: (venta: VentaPanel) => void;
}) {
    return (
        <button
            type="button"
            onClick={() => onSeleccionar(venta)}
            className="w-full rounded-xl border bg-card p-4 text-left text-sm shadow-xs transition-colors hover:bg-muted/40"
        >
            <div className="flex items-start justify-between gap-2">
                <div>
                    <p className="font-semibold">VENTA #{venta.consecutivo}</p>
                    <p className="text-muted-foreground">
                        {venta.fecha} · {venta.asesor}
                    </p>
                </div>
                <EstadoVentaBadge estado={venta.estado} />
            </div>
            <ul className="mt-3 grid gap-1">
                {venta.productos.map((producto) => (
                    <li
                        key={producto.id}
                        className="flex justify-between gap-3"
                    >
                        <span>
                            {producto.articulo} · {producto.marca}{' '}
                            {producto.referencia}
                        </span>
                        <span className="shrink-0 text-muted-foreground">
                            {producto.serial ?? 'Sin serie'}
                        </span>
                    </li>
                ))}
            </ul>
            <p className="mt-2 text-muted-foreground">
                {venta.cliente_nombre} · {formatearPesos(venta.valor_venta)}
            </p>
            {venta.estado === 'devuelta' && venta.motivo_devolucion && (
                <p className="mt-2 rounded-md bg-purple-50 p-2 text-purple-900 dark:bg-purple-950 dark:text-purple-100">
                    Devuelta: {venta.motivo_devolucion}
                </p>
            )}
            {venta.estado === 'pendiente' && venta.revisar_serie && (
                <p className="mt-2 rounded-md bg-amber-50 p-2 text-amber-900 dark:bg-amber-950 dark:text-amber-100">
                    Corregida por el asesor: revisa las series.
                    {venta.nota_correccion &&
                        ` Qué corrigió: ${venta.nota_correccion}`}
                </p>
            )}
        </button>
    );
}

Bodega.layout = {
    breadcrumbs: [{ title: 'Bodega', href: bodega() }],
};
