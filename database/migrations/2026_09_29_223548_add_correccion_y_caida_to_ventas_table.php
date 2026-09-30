<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            // Última vez que el asesor corrigió la venta después de una devolución
            $table->timestamp('corregida_en')->nullable()->after('devuelta_por');

            // Venta caída: la marca el asesor
            $table->text('motivo_caida')->nullable()->after('corregida_en');
            $table->timestamp('caida_en')->nullable()->after('motivo_caida');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['corregida_en', 'motivo_caida', 'caida_en']);
        });
    }
};
