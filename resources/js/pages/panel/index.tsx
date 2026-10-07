import { Head, Link, router, usePoll } from '@inertiajs/react';
import { Download, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import AccionesFacturacion from '@/components/acciones-facturacion';
import DetalleVentaDialog from '@/components/detalle-venta-dialog';
import EstadoVentaBadge from '@/components/estado-venta-badge';
import Paginacion from '@/components/paginacion';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatearPesos } from '@/lib/formato';
import { cn } from '@/lib/utils';
import { panel } from '@/routes';
import { informe } from '@/routes/panel';
import type {
    FiltrosPanel,
    IndicadoresPanel,
    Paginado,
    PestanaPanel,
    VentaPanel,
} from '@/types';

type Props = {
    filtros: FiltrosPanel;
    indicadores: IndicadoresPanel;
    ventas: Paginado<VentaPanel>;
    puedeFacturar: boolean;
    ultimaVentaId: number;
    /** Última vez que una venta quedó con serie (lista para facturar). */
    ultimaListaParaFacturar: string | null;
};

const TODOS = 'todos';

const INTERVALO_ACTUALIZACION = 30_000;

/**
 * Deja solo los filtros con valor para no ensuciar la URL.
 */
function consultaDe(filtros: FiltrosPanel): Record<string, string> {
    return Object.fromEntries(
        Object.entries(filtros).filter(([, valor]) => valor !== ''),
    );
}

export default function Panel({
    filtros,
    indicadores,
    ventas,
    puedeFacturar,
    ultimaVentaId,
    ultimaListaParaFacturar,
}: Props) {
    const [busqueda, setBusqueda] = useState(filtros.buscar);
    const [seleccionada, setSeleccionada] = useState<VentaPanel | null>(null);
    const vistoAntes = useRef({ ultimaVentaId, ultimaListaParaFacturar });
    const pendientes = indicadores.conSerie + indicadores.sinSerie;
    const nuevasSinVer = useRef(0);

    usePoll(
        INTERVALO_ACTUALIZACION,
        {
            only: [
                'ventas',
                'indicadores',
                'ultimaVentaId',
                'ultimaListaParaFacturar',
            ],
            showProgress: false,
        },
        { keepAlive: true },
    );

    useEffect(() => {
        const nuevas = ultimaVentaId - vistoAntes.current.ultimaVentaId;

        // Los avisos quedan en pantalla hasta que el facturador los cierre,
        // y uno abierto se actualiza en vez de apilar avisos nuevos.
        if (nuevas > 0) {
            nuevasSinVer.current += nuevas;

            toast.info(
                nuevasSinVer.current === 1
                    ? 'Llegó 1 venta nueva'
                    : `Llegaron ${nuevasSinVer.current} ventas nuevas`,
                {
                    id: 'ventas-nuevas',
                    duration: Infinity,
                    closeButton: true,
                    onDismiss: () => {
                        nuevasSinVer.current = 0;
                    },
                },
            );
        }

        if (
            ultimaListaParaFacturar !== null &&
            ultimaListaParaFacturar !==
                vistoAntes.current.ultimaListaParaFacturar
        ) {
            toast.info('Hay ventas con serie listas para facturar', {
                id: 'lista-para-facturar',
                duration: Infinity,
                closeButton: true,
            });
        }

        vistoAntes.current = { ultimaVentaId, ultimaListaParaFacturar };
    }, [ultimaVentaId, ultimaListaParaFacturar]);

    function aplicarFiltros(cambios: Partial<FiltrosPanel>) {
        router.get(panel.url(), consultaDe({ ...filtros, ...cambios }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    useEffect(() => {
        if (busqueda === filtros.buscar) {
            return;
        }

        const espera = setTimeout(
            () => aplicarFiltros({ buscar: busqueda }),
            400,
        );

        return () => clearTimeout(espera);
    }, [busqueda]);

    return (
        <>
            <Head
                title={
                    indicadores.conSerie > 0
                        ? `(${indicadores.conSerie}) Panel de ventas`
                        : 'Panel de ventas'
                }
            />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-bold">Panel de ventas</h1>
                    <Button variant="outline" asChild>
                        <a href={informe.url({ query: consultaDe(filtros) })}>
                            <Download />
                            Descargar CSV
                        </a>
                    </Button>
                </div>

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Indicador
                        titulo="Listas para facturar"
                        valor={String(indicadores.conSerie)}
                        detalle={`${indicadores.sinSerie} esperando serie de bodega`}
                        clases="text-amber-600 dark:text-amber-400"
                    />
                    <Indicador
                        titulo="Facturadas hoy"
                        valor={String(indicadores.facturadasHoy)}
                        clases="text-green-600 dark:text-green-400"
                    />
                    <Indicador
                        titulo="Devueltas hoy"
                        valor={String(indicadores.devueltasHoy)}
                        clases="text-purple-600 dark:text-purple-400"
                    />
                    <Indicador
                        titulo="Valor pendiente"
                        valor={formatearPesos(indicadores.valorPendiente)}
                    />
                </div>

                <div className="flex flex-col gap-3 md:flex-row md:items-center">
                    <div className="inline-flex w-full rounded-lg bg-muted p-1 md:w-auto">
                        <PestanaLink
                            pestana="pendientes"
                            actual={filtros.pestana}
                            filtros={filtros}
                        >
                            Pendientes ({pendientes})
                        </PestanaLink>
                        <PestanaLink
                            pestana="historial"
                            actual={filtros.pestana}
                            filtros={filtros}
                        >
                            Historial
                        </PestanaLink>
                    </div>

                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            value={busqueda}
                            onChange={(e) => setBusqueda(e.target.value)}
                            placeholder="Buscar por cliente, cédula, serial o número de venta"
                            className="pl-9"
                        />
                    </div>

                    {filtros.pestana === 'historial' && (
                        <Select
                            value={filtros.estado || TODOS}
                            onValueChange={(valor) =>
                                aplicarFiltros({
                                    estado:
                                        valor === TODOS
                                            ? ''
                                            : (valor as FiltrosPanel['estado']),
                                })
                            }
                        >
                            <SelectTrigger className="w-full md:w-44">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={TODOS}>
                                    Todos los estados
                                </SelectItem>
                                <SelectItem value="pendiente">
                                    Pendiente
                                </SelectItem>
                                <SelectItem value="con_serie">
                                    Pendiente con serie
                                </SelectItem>
                                <SelectItem value="facturada">
                                    Facturada
                                </SelectItem>
                                <SelectItem value="devuelta">
                                    Devuelta
                                </SelectItem>
                                <SelectItem value="caida">Caída</SelectItem>
                            </SelectContent>
                        </Select>
                    )}
                </div>

                {ventas.data.length === 0 ? (
                    <p className="rounded-xl border p-8 text-center text-sm text-muted-foreground">
                        {filtros.buscar || filtros.estado
                            ? 'No hay ventas que coincidan con la búsqueda.'
                            : filtros.pestana === 'pendientes'
                              ? 'No hay ventas pendientes por facturar.'
                              : 'Todavía no hay ventas registradas.'}
                    </p>
                ) : (
                    <>
                        <TablaVentas
                            ventas={ventas.data}
                            onSeleccionar={setSeleccionada}
                        />
                        <TarjetasVentas
                            ventas={ventas.data}
                            onSeleccionar={setSeleccionada}
                        />
                        <Paginacion pagina={ventas} />
                    </>
                )}
            </div>

            <DetalleVentaDialog
                venta={seleccionada}
                onClose={() => setSeleccionada(null)}
                acciones={
                    puedeFacturar
                        ? (venta, onClose) => (
                              <AccionesFacturacion
                                  venta={venta}
                                  onClose={onClose}
                              />
                          )
                        : undefined
                }
            />
        </>
    );
}

function Indicador({
    titulo,
    valor,
    detalle,
    clases,
}: {
    titulo: string;
    valor: string;
    detalle?: string;
    clases?: string;
}) {
    return (
        <div className="rounded-xl border bg-card p-4 shadow-xs">
            <p className="text-sm text-muted-foreground">{titulo}</p>
            <p className={cn('mt-1 text-2xl font-semibold', clases)}>{valor}</p>
            {detalle && (
                <p className="mt-1 text-xs text-muted-foreground">{detalle}</p>
            )}
        </div>
    );
}

function PestanaLink({
    pestana,
    actual,
    filtros,
    children,
}: {
    pestana: PestanaPanel;
    actual: PestanaPanel;
    filtros: FiltrosPanel;
    children: ReactNode;
}) {
    return (
        <Link
            href={panel.url({
                query: consultaDe({ ...filtros, pestana, estado: '' }),
            })}
            preserveState
            preserveScroll
            className={cn(
                'flex-1 rounded-md px-4 py-1.5 text-center text-sm font-medium transition-colors md:flex-none',
                pestana === actual
                    ? 'bg-background shadow-xs'
                    : 'text-muted-foreground hover:text-foreground',
            )}
        >
            {children}
        </Link>
    );
}

function TablaVentas({
    ventas,
    onSeleccionar,
}: {
    ventas: VentaPanel[];
    onSeleccionar: (venta: VentaPanel) => void;
}) {
    return (
        <div className="hidden overflow-x-auto rounded-xl border md:block">
            <table className="w-full text-sm">
                <thead className="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th className="px-4 py-3 font-medium">Venta</th>
                        <th className="px-4 py-3 font-medium">Fecha</th>
                        <th className="px-4 py-3 font-medium">Cliente</th>
                        <th className="px-4 py-3 font-medium">Artículo</th>
                        <th className="px-4 py-3 font-medium">Asesor</th>
                        <th className="px-4 py-3 text-right font-medium">
                            Valor
                        </th>
                        <th className="px-4 py-3 font-medium">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    {ventas.map((venta) => (
                        <tr
                            key={venta.id}
                            onClick={() => onSeleccionar(venta)}
                            className="cursor-pointer border-t hover:bg-muted/40"
                        >
                            <td className="px-4 py-3 font-medium">
                                <button
                                    type="button"
                                    className="hover:underline"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        onSeleccionar(venta);
                                    }}
                                >
                                    #{venta.consecutivo}
                                </button>
                            </td>
                            <td className="px-4 py-3 whitespace-nowrap">
                                {venta.fecha}
                            </td>
                            <td className="px-4 py-3">
                                <p>{venta.cliente_nombre}</p>
                                <p className="text-muted-foreground">
                                    C.C. {venta.cliente_cedula}
                                </p>
                            </td>
                            <td className="px-4 py-3">
                                <p>{venta.productos_resumen}</p>
                                <p className="text-muted-foreground">
                                    {venta.serial_resumen ?? 'Sin serie'}
                                </p>
                            </td>
                            <td className="px-4 py-3">{venta.asesor}</td>
                            <td className="px-4 py-3 text-right whitespace-nowrap">
                                {formatearPesos(venta.valor_venta)}
                            </td>
                            <td className="px-4 py-3">
                                <EstadoVentaBadge estado={venta.estado} />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function TarjetasVentas({
    ventas,
    onSeleccionar,
}: {
    ventas: VentaPanel[];
    onSeleccionar: (venta: VentaPanel) => void;
}) {
    return (
        <ul className="flex flex-col gap-3 md:hidden">
            {ventas.map((venta) => (
                <li key={venta.id}>
                    <button
                        type="button"
                        onClick={() => onSeleccionar(venta)}
                        className="w-full rounded-xl border bg-card p-4 text-left text-sm shadow-xs"
                    >
                        <div className="flex items-start justify-between gap-2">
                            <div>
                                <p className="font-semibold">
                                    VENTA #{venta.consecutivo}
                                </p>
                                <p className="text-muted-foreground">
                                    {venta.fecha}
                                </p>
                            </div>
                            <EstadoVentaBadge estado={venta.estado} />
                        </div>
                        <p className="mt-2">
                            {venta.cliente_nombre} · C.C. {venta.cliente_cedula}
                        </p>
                        <p className="text-muted-foreground">
                            {venta.productos_resumen} · {venta.asesor}
                        </p>
                        <p className="mt-1 font-medium">
                            {formatearPesos(venta.valor_venta)}
                        </p>
                    </button>
                </li>
            ))}
        </ul>
    );
}

Panel.layout = {
    breadcrumbs: [{ title: 'Panel de ventas', href: panel() }],
};
