<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flujo de bodega (R2) y devoluciones según el tipo de error (R3).
 * Las ventas pendientes cuyos productos ya tienen serie pasan a "con_serie",
 * y las devueltas existentes quedan a cargo del asesor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            // Bodega
            $table->timestamp('series_asignadas_en')->nullable()->after('estado');
            $table->foreignId('series_asignadas_por')->nullable()->after('series_asignadas_en')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('con_serie_en')->nullable()->after('series_asignadas_por');

            // Devoluciones: a quién le toca corregir y por qué tipo de error
            $table->string('devuelta_a', 20)->nullable()->after('devuelta_por');
            $table->boolean('devolucion_por_serie')->default(false)->after('devuelta_a');
            $table->boolean('devolucion_por_otros')->default(false)->after('devolucion_por_serie');
            $table->boolean('revisar_serie')->default(false)->after('devolucion_por_otros');
        });

        $conTodasLasSeries = DB::table('ventas')
            ->where('estado', 'pendiente')
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('venta_productos')
                ->whereColumn('venta_productos.venta_id', 'ventas.id')
                ->where(fn ($sinSerie) => $sinSerie->whereNull('venta_productos.serial')->orWhere('venta_productos.serial', '')))
            ->pluck('id');

        DB::table('ventas')->whereIn('id', $conTodasLasSeries)->update([
            'estado' => 'con_serie',
            'con_serie_en' => DB::raw('created_at'),
        ]);

        DB::table('ventas')->where('estado', 'devuelta')->update([
            'devuelta_a' => 'asesor',
            'devolucion_por_otros' => true,
        ]);
    }

    public function down(): void
    {
        DB::table('ventas')->where('estado', 'con_serie')->update(['estado' => 'pendiente']);

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['series_asignadas_por']);
            $table->dropColumn([
                'series_asignadas_en', 'series_asignadas_por', 'con_serie_en',
                'devuelta_a', 'devolucion_por_serie', 'devolucion_por_otros', 'revisar_serie',
            ]);
        });
    }
};
