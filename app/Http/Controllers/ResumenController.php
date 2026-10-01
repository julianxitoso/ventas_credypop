<?php

namespace App\Http\Controllers;

use App\Enums\EstadoVenta;
use App\Models\Convenio;
use App\Models\Venta;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard del gerente y del administrador. El "valor vendido" y la
 * "cuota inicial" no incluyen las ventas caídas porque no se concretaron.
 */
class ResumenController extends Controller
{
    /** Cantidad de colores de la paleta de convenios (ver resources/css/app.css). */
    private const COLORES_CONVENIO = 8;

    private const PERIODOS = [
        'hoy' => 'Hoy',
        'semana' => 'Esta semana',
        'mes' => 'Este mes',
        'mes_anterior' => 'Mes anterior',
    ];

    public function __invoke(Request $request): Response
    {
        $periodo = array_key_exists($request->string('periodo')->value(), self::PERIODOS)
            ? $request->string('periodo')->value()
            : 'mes';

        [$desde, $hasta] = $this->rango($periodo);

        return Inertia::render('resumen/index', [
            'periodo' => $periodo,
            'periodos' => collect(self::PERIODOS)
                ->map(fn (string $etiqueta, string $valor): array => ['value' => $valor, 'etiqueta' => $etiqueta])
                ->values(),
            'rango' => $desde->isSameDay($hasta)
                ? $desde->format('d/m/Y')
                : $desde->format('d/m/Y').' – '.$hasta->format('d/m/Y'),
            'indicadores' => $this->indicadores($desde, $hasta),
            'porDia' => $this->porDia($desde, $hasta),
            'porAsesor' => $this->porAsesor($desde, $hasta),
            'porConvenio' => $this->porConvenio($desde, $hasta),
        ]);
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function rango(string $periodo): array
    {
        $ahora = CarbonImmutable::now();

        return match ($periodo) {
            'hoy' => [$ahora->startOfDay(), $ahora->endOfDay()],
            'semana' => [$ahora->startOfWeek(CarbonInterface::MONDAY), $ahora->endOfDay()],
            'mes_anterior' => [
                $ahora->subMonthNoOverflow()->startOfMonth(),
                $ahora->subMonthNoOverflow()->endOfMonth(),
            ],
            default => [$ahora->startOfMonth(), $ahora->endOfDay()],
        };
    }

    private function ventasDelPeriodo(CarbonImmutable $desde, CarbonImmutable $hasta): Builder
    {
        return Venta::query()->toBase()->whereBetween('ventas.created_at', [$desde, $hasta]);
    }

    /**
     * @return array<string, int>
     */
    private function indicadores(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $porEstado = $this->ventasDelPeriodo($desde, $hasta)
            ->selectRaw('estado, count(*) as cantidad, coalesce(sum(valor_venta), 0) as valor')
            ->selectRaw('coalesce(sum(valor_inicial), 0) as inicial')
            ->selectRaw('sum(case when valor_inicial > 0 then 1 else 0 end) as con_inicial')
            ->groupBy('estado')
            ->get()
            ->keyBy('estado');

        $dato = fn (EstadoVenta $estado, string $campo): int => (int) ($porEstado->get($estado->value)->{$campo} ?? 0);

        $valorVendido = (int) $porEstado->sum('valor') - $dato(EstadoVenta::Caida, 'valor');
        $cuotaInicial = (int) $porEstado->sum('inicial') - $dato(EstadoVenta::Caida, 'inicial');

        return [
            'registradas' => (int) $porEstado->sum('cantidad'),
            'valorVendido' => $valorVendido,
            'cuotaInicial' => $cuotaInicial,
            'valorSinIniciales' => $valorVendido - $cuotaInicial,
            'ventasConInicial' => (int) $porEstado->sum('con_inicial') - $dato(EstadoVenta::Caida, 'con_inicial'),
            'facturadas' => $dato(EstadoVenta::Facturada, 'cantidad'),
            'valorFacturado' => $dato(EstadoVenta::Facturada, 'valor'),
            'pendientes' => $dato(EstadoVenta::Pendiente, 'cantidad'),
            'devueltas' => $dato(EstadoVenta::Devuelta, 'cantidad'),
            'caidas' => $dato(EstadoVenta::Caida, 'cantidad'),
        ];
    }

    /**
     * Ventas (sin caídas) de cada día del período, separadas por estado,
     * incluidos los días en cero.
     *
     * @return list<array{dia: string, etiqueta: string, cantidad: int, valor: int, facturadas: int, pendientes: int, devueltas: int}>
     */
    private function porDia(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $totales = $this->ventasDelPeriodo($desde, $hasta)
            ->where('estado', '!=', EstadoVenta::Caida->value)
            ->selectRaw('date(created_at) as dia, count(*) as cantidad, sum(valor_venta) as valor')
            ->selectRaw('sum(case when estado = ? then 1 else 0 end) as facturadas', [EstadoVenta::Facturada->value])
            ->selectRaw('sum(case when estado = ? then 1 else 0 end) as pendientes', [EstadoVenta::Pendiente->value])
            ->selectRaw('sum(case when estado = ? then 1 else 0 end) as devueltas', [EstadoVenta::Devuelta->value])
            ->groupByRaw('date(created_at)')
            ->get()
            ->keyBy('dia');

        $ultimoDia = $hasta->min(CarbonImmutable::now()->endOfDay());
        $dias = [];

        foreach (CarbonPeriod::create($desde->startOfDay(), '1 day', $ultimoDia) as $dia) {
            $total = $totales->get($dia->format('Y-m-d'));

            $dias[] = [
                'dia' => $dia->format('Y-m-d'),
                'etiqueta' => $dia->format('d/m'),
                'cantidad' => (int) ($total->cantidad ?? 0),
                'valor' => (int) ($total->valor ?? 0),
                'facturadas' => (int) ($total->facturadas ?? 0),
                'pendientes' => (int) ($total->pendientes ?? 0),
                'devueltas' => (int) ($total->devueltas ?? 0),
            ];
        }

        return $dias;
    }

    /**
     * @return list<array{id: int, nombre: string, usuario: string, ventas: int, valor: int, inicial: int, facturadas: int, devueltas: int, caidas: int}>
     */
    private function porAsesor(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        return array_values($this->ventasDelPeriodo($desde, $hasta)
            ->join('users', 'users.id', '=', 'ventas.asesor_id')
            ->select('users.id', 'users.name', 'users.usuario')
            ->selectRaw('count(*) as ventas')
            ->selectRaw('sum(case when ventas.estado != ? then ventas.valor_venta else 0 end) as valor', [EstadoVenta::Caida->value])
            ->selectRaw('sum(case when ventas.estado != ? then ventas.valor_inicial else 0 end) as inicial', [EstadoVenta::Caida->value])
            ->selectRaw('sum(case when ventas.estado = ? then 1 else 0 end) as facturadas', [EstadoVenta::Facturada->value])
            ->selectRaw('sum(case when ventas.estado = ? then 1 else 0 end) as devueltas', [EstadoVenta::Devuelta->value])
            ->selectRaw('sum(case when ventas.estado = ? then 1 else 0 end) as caidas', [EstadoVenta::Caida->value])
            ->groupBy('users.id', 'users.name', 'users.usuario')
            ->orderByDesc('valor')
            ->get()
            ->map(fn (object $fila): array => [
                'id' => (int) $fila->id,
                'nombre' => (string) $fila->name,
                'usuario' => (string) $fila->usuario,
                'ventas' => (int) $fila->ventas,
                'valor' => (int) $fila->valor,
                'inicial' => (int) $fila->inicial,
                'facturadas' => (int) $fila->facturadas,
                'devueltas' => (int) $fila->devueltas,
                'caidas' => (int) $fila->caidas,
            ])
            ->all());
    }

    /**
     * Totales por convenio. Cada convenio lleva un color fijo según su orden de
     * creación, para que conserve el mismo color en cualquier período.
     *
     * @return list<array{nombre: string, ventas: int, valor: int, inicial: int, color: int}>
     */
    private function porConvenio(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $posicion = Convenio::query()->orderBy('id')->pluck('id')->flip();

        return array_values($this->ventasDelPeriodo($desde, $hasta)
            ->join('convenios', 'convenios.id', '=', 'ventas.convenio_id')
            ->where('ventas.estado', '!=', EstadoVenta::Caida->value)
            ->select('convenios.id', 'convenios.nombre')
            ->selectRaw('count(*) as ventas, sum(ventas.valor_venta) as valor, sum(ventas.valor_inicial) as inicial')
            ->groupBy('convenios.id', 'convenios.nombre')
            ->orderByDesc('valor')
            ->get()
            ->map(fn (object $fila): array => [
                'nombre' => (string) $fila->nombre,
                'ventas' => (int) $fila->ventas,
                'valor' => (int) $fila->valor,
                'inicial' => (int) $fila->inicial,
                'color' => (int) $posicion->get($fila->id, 0) % self::COLORES_CONVENIO,
            ])
            ->all());
    }
}
