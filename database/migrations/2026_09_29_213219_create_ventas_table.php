<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id(); // Es el consecutivo: VENTA #000001

            // Quién registró
            $table->foreignId('asesor_id')->constrained('users')->restrictOnDelete();

            // Cliente
            $table->string('cliente_nombre', 150);
            $table->string('cliente_cedula', 20)->index();
            $table->string('cliente_celular', 20);
            $table->string('cliente_correo', 150)->nullable();

            // Artículo
            $table->string('articulo', 150);
            $table->string('marca', 80);
            $table->string('referencia', 80);
            $table->string('serial', 100)->index();

            // Valores (pesos enteros)
            $table->unsignedBigInteger('valor_venta');
            $table->unsignedBigInteger('valor_inicial')->default(0);
            $table->foreignId('convenio_id')->constrained('convenios')->restrictOnDelete();
            $table->unsignedBigInteger('gasto_administrativo')->nullable();

            $table->text('observaciones')->nullable();

            // Estado y trazabilidad
            $table->string('estado', 20)->default('pendiente')->index();
            $table->string('numero_factura', 50)->nullable()->unique();
            $table->timestamp('facturada_en')->nullable();
            $table->foreignId('facturada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motivo_devolucion')->nullable();
            $table->timestamp('devuelta_en')->nullable();
            $table->foreignId('devuelta_por')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->index(['estado', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
