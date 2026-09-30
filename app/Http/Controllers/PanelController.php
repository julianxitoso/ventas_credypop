<?php

namespace App\Http\Controllers;

use App\Enums\EstadoVenta;
use App\Http\Resources\VentaPanelResource;
use App\Models\Venta;
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
        'Cliente', 'Cédula', 'Celular', 'Correo',
        'Artículo', 'Marca', 'Referencia', 'Serial',
        'Valor venta', 'Valor inicial', 'Convenio', 'Gasto administrativo', 'Observaciones',
        'Estado', 'Número factura', 'Facturada en', 'Facturada por',
        'Motivo devolución', 'Devuelta en', 'Devuelta por', 'Corregida en',
        'Motivo caída', 'Caída en',
    ];

    /**
     * Panel del facturador y del administrador: indicadores y listado de ventas.
     * La página se actualiza sola cada 30 segundos; "ultimaVentaId" y
     * "ultimaCorreccion" le permiten avisar cuando llega trabajo nuevo.
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
            'ultimaCorreccion' => Venta::query()->max('corregida_en'),
        ]);
    }

    /**
     * Descarga en CSV (separador ";" y BOM para Excel) de las ventas
     * que coinciden con los filtros actuales.
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

            $this->consulta($filtros)->reorder()->lazyByIdDesc(500)->each(
                fn (Venta $venta) => fputcsv($salida, $this->filaInforme($venta), ';', '"', ''),
            );

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
     * @param  array{pestana: string, buscar: string, estado: string}  $filtros
     * @return Builder<Venta>
     */
    private function consulta(array $filtros): Builder
    {
        return Venta::query()
            ->with(['asesor:id,name,usuario', 'convenio:id,nombre', 'facturador:id,name', 'devueltaPor:id,name'])
            ->when(
                $filtros['pestana'] === 'pendientes',
                fn (Builder $query) => $query->where('estado', EstadoVenta::Pendiente),
                fn (Builder $query) => $query->when(
                    $filtros['estado'] !== '',
                    fn (Builder $query) => $query->where('estado', $filtros['estado']),
                ),
            )
            ->buscar($filtros['buscar'])
            ->latest('id');
    }

    /**
     * @return array{pendientes: int, facturadasHoy: int, devueltasHoy: int, valorPendiente: int}
     */
    private function indicadores(): array
    {
        $pendientes = Venta::query()->where('estado', EstadoVenta::Pendiente);

        return [
            'pendientes' => (clone $pendientes)->count(),
            'facturadasHoy' => Venta::query()->where('facturada_en', '>=', today())->count(),
            'devueltasHoy' => Venta::query()->where('devuelta_en', '>=', today())->count(),
            'valorPendiente' => (int) $pendientes->sum('valor_venta'),
        ];
    }

    /**
     * @return list<string|int|null>
     */
    private function filaInforme(Venta $venta): array
    {
        $fila = [
            $venta->consecutivo,
            $venta->created_at->format('Y-m-d H:i'),
            $venta->asesor->name,
            $venta->asesor->usuario,
            $venta->cliente_nombre,
            $venta->cliente_cedula,
            $venta->cliente_celular,
            $venta->cliente_correo,
            $venta->articulo,
            $venta->marca,
            $venta->referencia,
            $venta->serial,
            $venta->valor_venta,
            $venta->valor_inicial,
            $venta->convenio->nombre,
            $venta->gasto_administrativo,
            $venta->observaciones,
            $venta->estado->etiqueta(),
            $venta->numero_factura,
            $venta->facturada_en?->format('Y-m-d H:i'),
            $venta->facturador?->name,
            $venta->motivo_devolucion,
            $venta->devuelta_en?->format('Y-m-d H:i'),
            $venta->devueltaPor?->name,
            $venta->corregida_en?->format('Y-m-d H:i'),
            $venta->motivo_caida,
            $venta->caida_en?->format('Y-m-d H:i'),
        ];

        return array_map($this->celdaSegura(...), $fila);
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
