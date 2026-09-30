<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('usuario', 50)->unique()->after('name');
            $table->string('rol', 20)->default('asesor')->index()->after('usuario');
            $table->boolean('activo')->default(true)->after('rol');
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['usuario']);
            $table->dropIndex(['rol']);
            $table->dropColumn(['usuario', 'rol', 'activo']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
