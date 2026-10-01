import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
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
    /** Solo ventas facturadas: la venta real. */
    valorVendido: number;
    /** Pendientes + devueltas: aún pueden caerse. */
    porFacturar: number;
    totalRegistrado: number;
    /** Todas las ventas no caídas: la inicial se recibe al registrar. */
    cuotaInicial: number;
    ventasConInicial: number;
    valorSinIniciales: number;
    facturadas: number;
    pendientes: number;
    devueltas: number;
    caidas: number;
};

type Dia = {
    dia: string;
    etiqueta: string;
    cantidad: number;
    valor: number;
    facturadas: number;
    pendientes: number;
    devueltas: number;
};

type FilaAsesor = {
    id: number;
    nombre: string;
    usuario: string;
    ventas: number;
    valor: number;
    porFacturar: number;
    inicial: number;
    facturadas: number;
    devueltas: number;
    caidas: number;
};

type FilaConvenio = {
    nombre: string;
    ventas: number;
    valor: number;
    porFacturar: number;
    inicial: number;
    /** Posición fija del color del convenio en la paleta (0 a 7). */
    color: number;
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

const COLOR_GUIA = 'var(--color-border)';
const COLOR_TEXTO_EJE = 'var(--color-muted-foreground)';
const COLOR_SUPERFICIE = 'var(--color-card)';

/** Estados apilados en la gráfica por día, de abajo hacia arriba. */
const ESTADOS_POR_DIA = [
    {
        clave: 'facturadas',
        etiqueta: 'Facturadas',
        color: 'var(--grafica-facturada)',
    },
    {
        clave: 'pendientes',
        etiqueta: 'Pendientes',
        color: 'var(--grafica-pendiente)',
    },
    {
        clave: 'devueltas',
        etiqueta: 'Devueltas',
        color: 'var(--grafica-devuelta)',
    },
] as const;

function plural(cantidad: number, singular: string, varios: string): string {
    return `${cantidad} ${cantidad === 1 ? singular : varios}`;
}

function colorConvenio(posicion: number): string {
    return `var(--convenio-${posicion + 1})`;
}

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
                            Valor vendido (facturado)
                        </p>
                        <p className="mt-1 text-4xl font-semibold">
                            {formatearPesos(indicadores.valorVendido)}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {plural(
                                indicadores.facturadas,
                                'venta facturada',
                                'ventas facturadas',
                            )}
                        </p>
                    </div>
                    <Tile
                        titulo="Por facturar"
                        valor={formatearPesos(indicadores.porFacturar)}
                        detalle={`${plural(indicadores.pendientes, 'pendiente', 'pendientes')} · ${plural(indicadores.devueltas, 'devuelta', 'devueltas')}`}
                    />
                    <Tile
                        titulo="Total registrado"
                        valor={formatearPesos(indicadores.totalRegistrado)}
                        detalle="Vendido + por facturar, sin caídas"
                    />
                    <Tile
                        titulo="Cuotas iniciales"
                        valor={formatearPesos(indicadores.cuotaInicial)}
                        detalle={`${plural(indicadores.ventasConInicial, 'venta', 'ventas')} con inicial, sin caídas`}
                    />
                    <Tile
                        titulo="Vendido sin iniciales"
                        valor={formatearPesos(indicadores.valorSinIniciales)}
                        detalle="Facturado − sus cuotas iniciales"
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
                        subtitulo="Ventas registradas cada día según su estado actual (sin caídas)"
                        className="xl:col-span-3"
                    >
                        <GraficaPorDia datos={porDia} />
                    </Tarjeta>

                    <Tarjeta
                        titulo="Ventas por convenio"
                        subtitulo="La gráfica muestra lo vendido (facturado); la tabla agrega lo que está por facturar y la cuota inicial. Sin caídas."
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

type ClaveEstado = (typeof ESTADOS_POR_DIA)[number]['clave'];

/**
 * Segmento de la barra apilada: solo el segmento superior de cada día lleva
 * las esquinas redondeadas; el borde del color del fondo separa los segmentos.
 */
function segmentoApilado(clave: ClaveEstado) {
    const posicion = ESTADOS_POR_DIA.findIndex((e) => e.clave === clave);

    return function Segmento(props: unknown) {
        const { x, y, width, height, fill, payload } = props as {
            x: number;
            y: number;
            width: number;
            height: number;
            fill: string;
            payload: Dia;
        };

        if (!height || height <= 0) {
            return <g />;
        }

        const esSuperior = ESTADOS_POR_DIA.slice(posicion + 1).every(
            (e) => payload[e.clave] === 0,
        );
        const r = esSuperior ? Math.min(4, height, width / 2) : 0;

        return (
            <path
                d={`M${x},${y + height} L${x},${y + r} Q${x},${y} ${x + r},${y} L${x + width - r},${y} Q${x + width},${y} ${x + width},${y + r} L${x + width},${y + height} Z`}
                fill={fill}
                stroke={COLOR_SUPERFICIE}
                strokeWidth={2}
            />
        );
    };
}

function GraficaPorDia({ datos }: { datos: Dia[] }) {
    return (
        <div className="flex flex-col gap-3">
            <ul className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                {ESTADOS_POR_DIA.map((estado) => (
                    <li
                        key={estado.clave}
                        className="flex items-center gap-1.5"
                    >
                        <span
                            aria-hidden
                            className="size-2.5 rounded-full"
                            style={{ backgroundColor: estado.color }}
                        />
                        {estado.etiqueta}
                    </li>
                ))}
            </ul>
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
                                    <div className="rounded-md border bg-popover px-3 py-2 text-sm text-popover-foreground shadow-md">
                                        <p className="font-medium">
                                            {dia.etiqueta} ·{' '}
                                            {formatearPesos(dia.valor)}
                                        </p>
                                        {ESTADOS_POR_DIA.slice()
                                            .reverse()
                                            .map((estado) => (
                                                <p
                                                    key={estado.clave}
                                                    className="flex items-center gap-1.5 text-muted-foreground"
                                                >
                                                    <span
                                                        aria-hidden
                                                        className="size-2 rounded-full"
                                                        style={{
                                                            backgroundColor:
                                                                estado.color,
                                                        }}
                                                    />
                                                    {estado.etiqueta}:{' '}
                                                    {dia[estado.clave]}
                                                </p>
                                            ))}
                                    </div>
                                ) : null;
                            }}
                        />
                        {ESTADOS_POR_DIA.map((estado) => (
                            <Bar
                                key={estado.clave}
                                dataKey={estado.clave}
                                name={estado.etiqueta}
                                stackId="estado"
                                fill={estado.color}
                                maxBarSize={24}
                                shape={segmentoApilado(estado.clave)}
                                isAnimationActive={false}
                            />
                        ))}
                    </BarChart>
                </ResponsiveContainer>
            </div>
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
                                        `Por facturar: ${formatearPesos(fila.porFacturar)}`,
                                        `Cuota inicial: ${formatearPesos(fila.inicial)}`,
                                        plural(fila.ventas, 'venta', 'ventas'),
                                    ]}
                                />
                            ) : null;
                        }}
                    />
                    <Bar dataKey="valor" radius={[0, 4, 4, 0]} maxBarSize={24}>
                        {datos.map((fila) => (
                            <Cell
                                key={fila.nombre}
                                fill={colorConvenio(fila.color)}
                            />
                        ))}
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
                            Vendido
                        </th>
                        <th className="px-4 py-2 text-right font-medium">
                            Por facturar
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
                                {formatearPesos(fila.porFacturar)}
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
                            Vendido
                        </th>
                        <th className="px-3 py-2 text-right font-medium">
                            Por facturar
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
                                <span className="flex items-center gap-2">
                                    <span
                                        aria-hidden
                                        className="size-2.5 shrink-0 rounded-full"
                                        style={{
                                            backgroundColor: colorConvenio(
                                                fila.color,
                                            ),
                                        }}
                                    />
                                    {fila.nombre}
                                </span>
                            </td>
                            <td className="px-3 py-2 text-right font-medium">
                                {formatearPesos(fila.valor)}
                            </td>
                            <td className="px-3 py-2 text-right">
                                {formatearPesos(fila.porFacturar)}
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
