<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Una venta pasa a tener varios productos (cada uno con su valor y su serie)
 * y varios convenios (cada uno con el valor que cubre y su gasto administrativo).
 * Las ventas existentes se convierten: cada una queda con 1 producto y 1 convenio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->string('articulo', 150);
            $table->string('marca', 80);
            $table->string('referencia', 80);
            $table->string('serial', 100)->nullable()->index();
            $table->unsignedBigInteger('valor');
            $table->timestamps();
        });

        Schema::create('convenio_venta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('convenio_id')->constrained('convenios')->restrictOnDelete();
            $table->unsignedBigInteger('valor')->default(0);
            $table->unsignedBigInteger('gasto_administrativo')->nullable();
            $table->timestamps();
            $table->unique(['venta_id', 'convenio_id']);
        });

        $ahora = now();

        foreach (DB::table('ventas')->orderBy('id')->cursor() as $venta) {
            DB::table('venta_productos')->insert([
                'venta_id' => $venta->id,
                'articulo' => $venta->articulo,
                'marca' => $venta->marca,
                'referencia' => $venta->referencia,
                'serial' => $venta->serial,
                'valor' => $venta->valor_venta,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            DB::table('convenio_venta')->insert([
                'venta_id' => $venta->id,
                'convenio_id' => $venta->convenio_id,
                'valor' => max(0, $venta->valor_venta - $venta->valor_inicial),
                'gasto_administrativo' => $venta->gasto_administrativo,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['convenio_id']);
            $table->dropIndex(['serial']);
            $table->dropColumn(['articulo', 'marca', 'referencia', 'serial', 'convenio_id', 'gasto_administrativo']);
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('articulo', 150)->default('');
            $table->string('marca', 80)->default('');
            $table->string('referencia', 80)->default('');
            $table->string('serial', 100)->nullable()->index();
            $table->foreignId('convenio_id')->nullable()->constrained('convenios')->restrictOnDelete();
            $table->unsignedBigInteger('gasto_administrativo')->nullable();
        });

        foreach (DB::table('ventas')->orderBy('id')->cursor() as $venta) {
            $producto = DB::table('venta_productos')->where('venta_id', $venta->id)->orderBy('id')->first();
            $convenio = DB::table('convenio_venta')->where('venta_id', $venta->id)->orderBy('id')->first();

            DB::table('ventas')->where('id', $venta->id)->update([
                'articulo' => $producto->articulo ?? '',
                'marca' => $producto->marca ?? '',
                'referencia' => $producto->referencia ?? '',
                'serial' => $producto->serial ?? null,
                'convenio_id' => $convenio->convenio_id ?? null,
                'gasto_administrativo' => $convenio->gasto_administrativo ?? null,
            ]);
        }

        Schema::dropIfExists('convenio_venta');
        Schema::dropIfExists('venta_productos');
    }
};
