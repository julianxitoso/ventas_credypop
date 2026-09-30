<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActualizarConvenioRequest;
use App\Http\Requests\Admin\GuardarConvenioRequest;
use App\Models\Convenio;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ConvenioController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/convenios', [
            'convenios' => Convenio::query()
                ->withCount('ventas')
                ->orderByDesc('activo')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'requiere_gasto', 'activo']),
        ]);
    }

    public function store(GuardarConvenioRequest $request): RedirectResponse
    {
        $convenio = Convenio::create([
            'nombre' => $request->validated('nombre'),
            'requiere_gasto' => $request->boolean('requiere_gasto'),
            'activo' => true,
        ]);

        return $this->volverConAviso("Convenio {$convenio->nombre} creado.");
    }

    /**
     * Activa o desactiva el convenio, o cambia si requiere gasto administrativo.
     */
    public function update(ActualizarConvenioRequest $request, Convenio $convenio): RedirectResponse
    {
        $convenio->update($request->validated());

        return $this->volverConAviso("Convenio {$convenio->nombre} actualizado.");
    }

    private function volverConAviso(string $mensaje): RedirectResponse
    {
        return Inertia::flash('toast', ['type' => 'success', 'message' => $mensaje])->back();
    }
}
