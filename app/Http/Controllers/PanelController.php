<?php

namespace App\Http\Controllers;

use App\Enums\EstadoVenta;
use App\Http\Resources\VentaPanelResource;
use App\Models\ConvenioVenta;
use App\Models\Venta;
use App\Models\VentaProducto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PanelController extends Controller
{
    private const VENTAS_POR_PAGINA = 25;

    private const PESTANAS = ['pendientes', 'historial'];

    private const COLUMNAS_INFORME = [
        'Venta', 'Fecha', 'Asesor', 'Usuario asesor',
        'Cliente', 'Cédula', 'Celular', 'Teléfono', 'Otro celular', 'Correo', 'Dirección', 'Ciudad',
        'Producto', 'Artículo', 'Marca', 'Referencia', 'Serial', 'Valor producto',
        'Total venta', 'Valor inicial', 'Pago inicial', 'Número transferencia',
        'Convenios', 'Gasto administrativo', 'Observaciones',
        'Estado', 'Series asignadas en', 'Series asignadas por',
        'Número factura', 'Facturada en', 'Facturada por',
        'Motivo devolución', 'Devuelta a', 'Error de serie', 'Error de otros datos',
        'Devuelta en', 'Devuelta por', 'Corregida en',
        'Motivo caída', 'Caída en',
    ];

    /**
     * Panel del facturador y del administrador: indicadores y listado de ventas.
     * La página se actualiza sola cada 30 segundos; "ultimaVentaId" y
     * "ultimaListaParaFacturar" le permiten avisar cuando llega trabajo nuevo.
     */
    public function index(Request $request): Response
    {
        $filtros = $this->filtros($request);

        return Inertia::render('panel/index', [
            'filtros' => $filtros,
            'indicadores' => $this->indicadores(),
            'ventas' => VentaPanelResource::collection(
                $this->consulta($filtros)->paginate(self::VENTAS_POR_PAGINA)->withQueryString(),
            ),
            'puedeFacturar' => $request->user()->can('facturar'),
            'ultimaVentaId' => (int) Venta::query()->max('id'),
            'ultimaListaParaFacturar' => Venta::query()->max('con_serie_en'),
        ]);
    }

    /**
     * Descarga en CSV (separador ";" y BOM para Excel) de las ventas que
     * coinciden con los filtros actuales: una fila por producto, repitiendo
     * los datos de la venta.
     */
    public function informe(Request $request): StreamedResponse
    {
        $filtros = $this->filtros($request);

        return response()->streamDownload(function () use ($filtros): void {
            $salida = fopen('php://output', 'w');

            if ($salida === false) {
                throw new RuntimeException('No se pudo generar el archivo CSV.');
            }

            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, self::COLUMNAS_INFORME, ';', '"', '');

            $this->consulta($filtros)->reorder()->lazyByIdDesc(500)->each(function (Venta $venta) use ($salida): void {
                foreach ($this->filasInforme($venta) as $fila) {
                    fputcsv($salida, $fila, ';', '"', '');
                }
            });

            fclose($salida);
        }, 'ventas-'.now()->format('Y-m-d-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{pestana: string, buscar: string, estado: string}
     */
    private function filtros(Request $request): array
    {
        $pestana = $request->string('pestana')->value();
        $estado = EstadoVenta::tryFrom($request->string('estado')->value());

        return [
            'pestana' => in_array($pestana, self::PESTANAS, true) ? $pestana : 'pendientes',
            'buscar' => $request->string('buscar')->trim()->limit(100, '')->value(),
            'estado' => $estado->value ?? '',
        ];
    }

    /**
     * En "pendientes" aparecen las ventas sin serie (solo consulta) y con serie
     * (para facturar); estas últimas van primero.
     *
     * @param  array{pestana: string, buscar: string, estado: string}  $filtros
     * @return Builder<Venta>
     */
    private function consulta(array $filtros): Builder
    {
        return Venta::query()
            ->with(['asesor:id,name,usuario', 'productos', 'conveniosVenta.convenio:id,nombre', 'facturador:id,name', 'devueltaPor:id,name', 'seriesAsignadasPor:id,name'])
            ->when(
                $filtros['pestana'] === 'pendientes',
                fn (Builder $query) => $query
                    ->whereIn('estado', EstadoVenta::porFacturar())
                    ->orderByRaw('case when estado = ? then 0 else 1 end', [EstadoVenta::ConSerie->value]),
                fn (Builder $query) => $query->when(
                    $filtros['estado'] !== '',
                    fn (Builder $query) => $query->where('estado', $filtros['estado']),
                ),
            )
            ->buscar($filtros['buscar'])
            ->latest('id');
    }

    /**
     * @return array{conSerie: int, sinSerie: int, facturadasHoy: int, devueltasHoy: int, valorPendiente: int}
     */
    private function indicadores(): array
    {
        return [
            'conSerie' => Venta::query()->where('estado', EstadoVenta::ConSerie)->count(),
            'sinSerie' => Venta::query()->where('estado', EstadoVenta::Pendiente)->count(),
            'facturadasHoy' => Venta::query()->where('facturada_en', '>=', today())->count(),
            'devueltasHoy' => Venta::query()->where('devuelta_en', '>=', today())->count(),
            'valorPendiente' => (int) Venta::query()->whereIn('estado', EstadoVenta::porFacturar())->sum('valor_venta'),
        ];
    }

    /**
     * Filas del CSV de una venta: una por producto ("1 de 3", "2 de 3"…).
     *
     * @return list<list<string|int|null>>
     */
    private function filasInforme(Venta $venta): array
    {
        $convenios = $venta->conveniosVenta
            ->map(fn (ConvenioVenta $convenio): string => $convenio->convenio->nombre.' $'.number_format($convenio->valor, 0, ',', '.'))
            ->join('; ');
        $gasto = $venta->conveniosVenta->sum(fn (ConvenioVenta $convenio): int => $convenio->gasto_administrativo ?? 0);
        $cantidad = $venta->productos->count();

        return array_values($venta->productos->values()->map(fn (VentaProducto $producto, int $posicion): array => array_map($this->celdaSegura(...), [
            $venta->consecutivo,
            $venta->created_at->format('Y-m-d H:i'),
            $venta->asesor->name,
            $venta->asesor->usuario,
            $venta->cliente_nombre,
            $venta->cliente_cedula,
            $venta->cliente_celular,
            $venta->cliente_telefono,
            $venta->cliente_celular_2,
            $venta->cliente_correo,
            $venta->cliente_direccion,
            $venta->cliente_ciudad,
            ($posicion + 1).' de '.$cantidad,
            $producto->articulo,
            $producto->marca,
            $producto->referencia,
            $producto->serial,
            $producto->valor,
            $venta->valor_venta,
            $venta->valor_inicial,
            $venta->forma_pago_inicial?->etiqueta(),
            $venta->numero_transferencia,
            $convenios,
            $gasto > 0 ? $gasto : null,
            $venta->observaciones,
            $venta->estado->etiqueta(),
            $venta->series_asignadas_en?->format('Y-m-d H:i'),
            $venta->seriesAsignadasPor?->name,
            $venta->numero_factura,
            $venta->facturada_en?->format('Y-m-d H:i'),
            $venta->facturador?->name,
            $venta->motivo_devolucion,
            $venta->devuelta_a?->etiqueta(),
            $venta->devuelta_en !== null ? ($venta->devolucion_por_serie ? 'Sí' : 'No') : null,
            $venta->devuelta_en !== null ? ($venta->devolucion_por_otros ? 'Sí' : 'No') : null,
            $venta->devuelta_en?->format('Y-m-d H:i'),
            $venta->devueltaPor?->name,
            $venta->corregida_en?->format('Y-m-d H:i'),
            $venta->motivo_caida,
            $venta->caida_en?->format('Y-m-d H:i'),
        ]))->all());
    }

    /**
     * Evita que Excel interprete como fórmula un texto escrito por un usuario.
     */
    private function celdaSegura(string|int|null $valor): string|int|null
    {
        if (is_string($valor) && preg_match('/^[=+\-@\t\r]/', $valor)) {
            return "'".$valor;
        }

        return $valor;
    }
}
