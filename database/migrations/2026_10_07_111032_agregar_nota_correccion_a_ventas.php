<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que el asesor explica que corrigió en la última corrección. Va aparte de
 * "observaciones", que es parte de la venta y no cambia al corregir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->text('nota_correccion')->nullable()->after('corregida_en');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn('nota_correccion');
        });
    }
};
