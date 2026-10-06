<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            // Contacto del cliente (obligatorios en el formulario; nulos solo en ventas anteriores)
            $table->string('cliente_telefono', 20)->nullable()->after('cliente_celular');
            $table->string('cliente_celular_2', 20)->nullable()->after('cliente_telefono');
            $table->string('cliente_direccion', 200)->nullable()->after('cliente_correo');
            $table->string('cliente_ciudad', 100)->nullable()->after('cliente_direccion');

            // Cómo se pagó la cuota inicial (solo si hay inicial)
            $table->string('forma_pago_inicial', 20)->nullable()->after('valor_inicial');
            $table->string('numero_transferencia', 50)->nullable()->after('forma_pago_inicial');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn([
                'cliente_telefono', 'cliente_celular_2', 'cliente_direccion', 'cliente_ciudad',
                'forma_pago_inicial', 'numero_transferencia',
            ]);
        });
    }
};
