import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    LabelList,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { formatearPesos, formatearPesosCorto } from '@/lib/formato';
import { cn } from '@/lib/utils';
import { resumen } from '@/routes';

type Indicadores = {
    registradas: number;
    valorVendido: number;
    cuotaInicial: number;
    valorSinIniciales: number;
    ventasConInicial: number;
    facturadas: number;
    valorFacturado: number;
    pendientes: number;
    devueltas: number;
    caidas: number;
};

type Dia = { dia: string; etiqueta: string; cantidad: number; valor: number };

type FilaAsesor = {
    id: number;
    nombre: string;
    usuario: string;
    ventas: number;
    valor: number;
    inicial: number;
    facturadas: number;
    devueltas: number;
    caidas: number;
};

type FilaConvenio = {
    nombre: string;
    ventas: number;
    valor: number;
    inicial: number;
};

type Props = {
    periodo: string;
    periodos: { value: string; etiqueta: string }[];
    rango: string;
    indicadores: Indicadores;
    porDia: Dia[];
    porAsesor: FilaAsesor[];
    porConvenio: FilaConvenio[];
};

const COLOR_SERIE = 'var(--color-chart-1)';
const COLOR_GUIA = 'var(--color-border)';
const COLOR_TEXTO_EJE = 'var(--color-muted-foreground)';

export default function Resumen({
    periodo,
    periodos,
    rango,
    indicadores,
    porDia,
    porAsesor,
    porConvenio,
}: Props) {
    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold">Dashboard</h1>
                        <p className="text-sm text-muted-foreground">{rango}</p>
                    </div>

                    <nav className="inline-flex flex-wrap rounded-lg bg-muted p-1">
                        {periodos.map((p) => (
                            <Link
                                key={p.value}
                                href={resumen({ query: { periodo: p.value } })}
                                preserveScroll
                                className={cn(
                                    'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                    p.value === periodo
                                        ? 'bg-background shadow-xs'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {p.etiqueta}
                            </Link>
                        ))}
                    </nav>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="rounded-xl border bg-card p-4 shadow-xs sm:col-span-2">
                        <p className="text-sm text-muted-foreground">
                            Valor vendido
                        </p>
                        <p className="mt-1 text-4xl font-semibold">
                            {formatearPesos(indicadores.valorVendido)}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {indicadores.registradas} ventas registradas · sin
                            contar las caídas
                        </p>
                    </div>
                    <Tile
                        titulo="Cuotas iniciales"
                        valor={formatearPesos(indicadores.cuotaInicial)}
                        detalle={`${indicadores.ventasConInicial} ${indicadores.ventasConInicial === 1 ? 'venta' : 'ventas'} con cuota inicial`}
                    />
                    <Tile
                        titulo="Valor vendido sin iniciales"
                        valor={formatearPesos(indicadores.valorSinIniciales)}
                        detalle="Valor vendido − cuotas iniciales"
                    />
                    <Tile
                        titulo="Facturadas"
                        valor={String(indicadores.facturadas)}
                        detalle={formatearPesos(indicadores.valorFacturado)}
                    />
                    <Tile
                        titulo="Pendientes"
                        valor={String(indicadores.pendientes)}
                        detalle="Por facturar"
                    />
                    <Tile
                        titulo="Devueltas"
                        valor={String(indicadores.devueltas)}
                        detalle="Esperando corrección"
                    />
                    <Tile
                        titulo="Caídas"
                        valor={String(indicadores.caidas)}
                        detalle="No se concretaron"
                    />
                </div>

                <div className="grid gap-4 xl:grid-cols-5">
                    <Tarjeta
                        titulo="Ventas por día"
                        subtitulo="Cantidad de ventas registradas (sin caídas)"
                        className="xl:col-span-3"
                    >
                        <GraficaPorDia datos={porDia} />
                    </Tarjeta>

                    <Tarjeta
                        titulo="Ventas por convenio"
                        subtitulo="Valor vendido y cuota inicial, sin contar las caídas"
                        className="xl:col-span-2"
                    >
                        {porConvenio.length === 0 ? (
                            <SinDatos />
                        ) : (
                            <div className="flex flex-col gap-4">
                                <GraficaPorConvenio datos={porConvenio} />
                                <TablaConvenios filas={porConvenio} />
                            </div>
                        )}
                    </Tarjeta>
                </div>

                <Tarjeta
                    titulo="Ventas por asesor"
                    subtitulo="Ordenado por valor vendido"
                >
                    {porAsesor.length === 0 ? (
                        <SinDatos />
                    ) : (
                        <TablaAsesores filas={porAsesor} />
                    )}
                </Tarjeta>
            </div>
        </>
    );
}

function Tile({
    titulo,
    valor,
    detalle,
    className,
}: {
    titulo: string;
    valor: string;
    detalle: string;
    className?: string;
}) {
    return (
        <div
            className={cn('rounded-xl border bg-card p-4 shadow-xs', className)}
        >
            <p className="text-sm text-muted-foreground">{titulo}</p>
            <p className="mt-1 text-2xl font-semibold">{valor}</p>
            <p className="mt-1 text-sm text-muted-foreground">{detalle}</p>
        </div>
    );
}

function Tarjeta({
    titulo,
    subtitulo,
    className,
    children,
}: {
    titulo: string;
    subtitulo: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <section
            className={cn('rounded-xl border bg-card p-4 shadow-xs', className)}
        >
            <h2 className="font-semibold">{titulo}</h2>
            <p className="mb-4 text-sm text-muted-foreground">{subtitulo}</p>
            {children}
        </section>
    );
}

function SinDatos() {
    return (
        <p className="py-10 text-center text-sm text-muted-foreground">
            No hay ventas en este período.
        </p>
    );
}

function CajaTooltip({ titulo, lineas }: { titulo: string; lineas: string[] }) {
    return (
        <div className="rounded-md border bg-popover px-3 py-2 text-sm text-popover-foreground shadow-md">
            <p className="font-medium">{titulo}</p>
            {lineas.map((linea) => (
                <p key={linea} className="text-muted-foreground">
                    {linea}
                </p>
            ))}
        </div>
    );
}

function GraficaPorDia({ datos }: { datos: Dia[] }) {
    return (
        <div className="h-64">
            <ResponsiveContainer width="100%" height="100%">
                <BarChart
                    data={datos}
                    margin={{ top: 8, right: 8, left: -16, bottom: 0 }}
                >
                    <CartesianGrid vertical={false} stroke={COLOR_GUIA} />
                    <XAxis
                        dataKey="etiqueta"
                        tickLine={false}
                        axisLine={{ stroke: COLOR_GUIA }}
                        tick={{ fill: COLOR_TEXTO_EJE, fontSize: 12 }}
                        interval="preserveStartEnd"
                        minTickGap={12}
                    />
                    <YAxis
                        allowDecimals={false}
                        tickLine={false}
                        axisLine={false}
                        tick={{ fill: COLOR_TEXTO_EJE, fontSize: 12 }}
                    />
                    <Tooltip
                        cursor={{ fill: 'var(--color-muted)' }}
                        content={({ active, payload }) => {
                            const dia = payload?.[0]?.payload as
                                | Dia
                                | undefined;

                            return active && dia ? (
                                <CajaTooltip
                                    titulo={dia.etiqueta}
                                    lineas={[
                                        `${dia.cantidad} ${dia.cantidad === 1 ? 'venta' : 'ventas'}`,
                                        formatearPesos(dia.valor),
                                    ]}
                                />
                            ) : null;
                        }}
                    />
                    <Bar
                        dataKey="cantidad"
                        fill={COLOR_SERIE}
                        radius={[4, 4, 0, 0]}
                        maxBarSize={24}
                    />
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}

function GraficaPorConvenio({ datos }: { datos: FilaConvenio[] }) {
    const alto = Math.max(160, datos.length * 40);

    return (
        <div style={{ height: alto }}>
            <ResponsiveContainer width="100%" height="100%">
                <BarChart
                    data={datos}
                    layout="vertical"
                    margin={{ top: 0, right: 72, left: 0, bottom: 0 }}
                >
                    <CartesianGrid horizontal={false} stroke={COLOR_GUIA} />
                    <XAxis type="number" hide />
                    <YAxis
                        type="category"
                        dataKey="nombre"
                        width={96}
                        tickLine={false}
                        axisLine={false}
                        tick={{ fill: COLOR_TEXTO_EJE, fontSize: 12 }}
                    />
                    <Tooltip
                        cursor={{ fill: 'var(--color-muted)' }}
                        content={({ active, payload }) => {
                            const fila = payload?.[0]?.payload as
                                | FilaConvenio
                                | undefined;

                            return active && fila ? (
                                <CajaTooltip
                                    titulo={fila.nombre}
                                    lineas={[
                                        `Vendido: ${formatearPesos(fila.valor)}`,
                                        `Cuota inicial: ${formatearPesos(fila.inicial)}`,
                                        `${fila.ventas} ${fila.ventas === 1 ? 'venta' : 'ventas'}`,
                                    ]}
                                />
                            ) : null;
                        }}
                    />
                    <Bar
                        dataKey="valor"
                        fill={COLOR_SERIE}
                        radius={[0, 4, 4, 0]}
                        maxBarSize={24}
                    >
                        <LabelList
                            dataKey="valor"
                            position="right"
                            formatter={(valor) =>
                                formatearPesosCorto(Number(valor))
                            }
                            fill={COLOR_TEXTO_EJE}
                            fontSize={12}
                        />
                    </Bar>
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}

function TablaAsesores({ filas }: { filas: FilaAsesor[] }) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm tabular-nums">
                <thead className="text-left text-muted-foreground">
                    <tr className="border-b">
                        <th className="py-2 pr-4 font-medium">Asesor</th>
                        <th className="px-4 py-2 text-right font-medium">
                            Ventas
                        </th>
                        <th className="px-4 py-2 text-right font-medium">
                            Valor vendido
                        </th>
                        <th className="px-4 py-2 text-right font-medium">
                            Cuota inicial
                        </th>
                        <th className="px-4 py-2 text-right font-medium">
                            Facturadas
                        </th>
                        <th className="px-4 py-2 text-right font-medium">
                            Devueltas
                        </th>
                        <th className="py-2 pl-4 text-right font-medium">
                            Caídas
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {filas.map((fila) => (
                        <tr key={fila.id} className="border-b last:border-0">
                            <td className="py-2 pr-4">
                                <p className="font-medium">{fila.nombre}</p>
                                <p className="text-muted-foreground">
                                    {fila.usuario}
                                </p>
                            </td>
                            <td className="px-4 py-2 text-right">
                                {fila.ventas}
                            </td>
                            <td className="px-4 py-2 text-right font-medium">
                                {formatearPesos(fila.valor)}
                            </td>
                            <td className="px-4 py-2 text-right">
                                {formatearPesos(fila.inicial)}
                            </td>
                            <td className="px-4 py-2 text-right">
                                {fila.facturadas}
                            </td>
                            <td className="px-4 py-2 text-right">
                                {fila.devueltas}
                            </td>
                            <td className="py-2 pl-4 text-right">
                                {fila.caidas}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function TablaConvenios({ filas }: { filas: FilaConvenio[] }) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm tabular-nums">
                <thead className="text-left text-muted-foreground">
                    <tr className="border-b">
                        <th className="py-2 pr-3 font-medium">Convenio</th>
                        <th className="px-3 py-2 text-right font-medium">
                            Ventas
                        </th>
                        <th className="px-3 py-2 text-right font-medium">
                            Vendido
                        </th>
                        <th className="py-2 pl-3 text-right font-medium">
                            Cuota inicial
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {filas.map((fila) => (
                        <tr
                            key={fila.nombre}
                            className="border-b last:border-0"
                        >
                            <td className="py-2 pr-3 font-medium">
                                {fila.nombre}
                            </td>
                            <td className="px-3 py-2 text-right">
                                {fila.ventas}
                            </td>
                            <td className="px-3 py-2 text-right">
                                {formatearPesos(fila.valor)}
                            </td>
                            <td className="py-2 pl-3 text-right">
                                {formatearPesos(fila.inicial)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

Resumen.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: resumen() }],
};
