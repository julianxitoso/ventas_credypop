<?php

use App\Http\Controllers\Admin\ConvenioController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\FacturacionController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\ResumenController;
use App\Http\Controllers\VentaController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (Request $request): RedirectResponse => $request->user()
    ? redirect($request->user()->rutaDeInicio())
    : to_route('login'))->name('home');

Route::middleware(['auth'])->group(function () {
    Route::middleware('can:registrar-ventas')->group(function () {
        Route::get('ventas/registrar', [VentaController::class, 'create'])->name('ventas.create');
        Route::post('ventas', [VentaController::class, 'store'])->name('ventas.store');
        Route::get('ventas/{venta}/corregir', [VentaController::class, 'edit'])->name('ventas.edit');
        Route::put('ventas/{venta}', [VentaController::class, 'update'])->name('ventas.update');
        Route::post('ventas/{venta}/caida', [VentaController::class, 'marcarCaida'])->name('ventas.caida');
    });

    Route::middleware('can:ver-ventas')->group(function () {
        Route::get('panel', [PanelController::class, 'index'])->name('panel');
        Route::get('panel/informe', [PanelController::class, 'informe'])->name('panel.informe');
    });

    Route::get('resumen', ResumenController::class)
        ->middleware('can:ver-dashboard')
        ->name('resumen');

    Route::middleware('can:administrar')->prefix('admin')->name('admin.')->group(function () {
        Route::get('usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::post('usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
        Route::patch('usuarios/{usuario}/estado', [UsuarioController::class, 'actualizarEstado'])->name('usuarios.estado');
        Route::put('usuarios/{usuario}/contrasena', [UsuarioController::class, 'restablecerContrasena'])->name('usuarios.contrasena');

        Route::get('convenios', [ConvenioController::class, 'index'])->name('convenios.index');
        Route::post('convenios', [ConvenioController::class, 'store'])->name('convenios.store');
        Route::patch('convenios/{convenio}', [ConvenioController::class, 'update'])->name('convenios.update');
    });

    Route::middleware('can:facturar')->group(function () {
        Route::post('panel/ventas/{venta}/facturar', [FacturacionController::class, 'facturar'])->name('ventas.facturar');
        Route::post('panel/ventas/{venta}/devolver', [FacturacionController::class, 'devolver'])->name('ventas.devolver');
    });
});

require __DIR__.'/settings.php';
