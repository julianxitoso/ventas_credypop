<?php

use App\Enums\EstadoVenta;
use App\Enums\FormaPagoInicial;
use App\Enums\Rol;
use App\Models\Convenio;
use App\Models\User;
use App\Models\Venta;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->asesor = User::factory()->create();
});

test('asesores register a pending sale in their own name', function () {
    $otroAsesor = User::factory()->create();

    $response = $this->actingAs($this->asesor)
        ->from(route('ventas.create'))
        ->post(route('ventas.store'), datosDeVenta(['asesor_id' => $otroAsesor->id]));

    $venta = Venta::sole();

    $response->assertRedirect(route('ventas.create'))
        ->assertInertiaFlash('ventaRegistrada', $venta->consecutivo);

    expect($venta->asesor_id)->toBe($this->asesor->id)
        ->and($venta->refresh()->estado)->toBe(EstadoVenta::Pendiente)
        ->and($venta->valor_venta)->toBe(1_250_000)
        ->and($venta->valor_inicial)->toBe(0)
        ->and($venta->productos)->toHaveCount(1)
        ->and($venta->convenios)->toHaveCount(1)
        ->and($venta->convenios->first()->pivot->valor)->toBe(1_250_000)
        ->and($venta->convenios->first()->pivot->gasto_administrativo)->toBeNull();
});

test('a sale has several products and its total is their sum', function () {
    $brilla = Convenio::factory()->create(['nombre' => 'Brilla']);
    $addi = Convenio::factory()->create(['nombre' => 'Addi']);

    $this->actingAs($this->asesor)->post(route('ventas.store'), datosDeVenta([
        'productos' => [
            ['articulo' => 'Televisor', 'marca' => 'Samsung', 'referencia' => 'UN55', 'serial' => '', 'valor' => '$1.000.000'],
            ['articulo' => 'Nevera', 'marca' => 'LG', 'referencia' => 'GT32', 'serial' => '', 'valor' => '$800.000'],
        ],
        'valor_inicial' => '$300.000',
        'forma_pago_inicial' => 'efectivo',
        'convenios' => [
            convenioDeVenta($brilla, '$750.000'),
            convenioDeVenta($addi, '$750.000'),
        ],
    ]))->assertSessionHasNoErrors();

    $venta = Venta::sole();

    expect($venta->valor_venta)->toBe(1_800_000)
        ->and($venta->valor_inicial)->toBe(300_000)
        ->and($venta->productos->pluck('articulo')->all())->toBe(['Televisor', 'Nevera'])
        ->and($venta->productos->pluck('valor')->all())->toBe([1_000_000, 800_000])
        ->and($venta->convenios->mapWithKeys(fn ($c) => [$c->nombre => $c->pivot->valor])->all())
        ->toBe(['Brilla' => 750_000, 'Addi' => 750_000]);
});

test('the convenios must add up to the total minus the down payment', function () {
    $brilla = Convenio::factory()->create();
    $addi = Convenio::factory()->create();

    $this->actingAs($this->asesor)
        ->post(route('ventas.store'), datosDeVenta([
            'valor_inicial' => '250000',
            'forma_pago_inicial' => 'efectivo',
            'convenios' => [convenioDeVenta($brilla, '500000'), convenioDeVenta($addi, '400000')],
        ]))
        ->assertSessionHasErrors(['convenios' => 'Los convenios deben sumar $1.000.000 (total menos la cuota inicial); faltan $100.000.']);

    $this->actingAs($this->asesor)
        ->post(route('ventas.store'), datosDeVenta([
            'valor_inicial' => '250000',
            'forma_pago_inicial' => 'efectivo',
            'convenios' => [convenioDeVenta($brilla, '700000'), convenioDeVenta($addi, '400000')],
        ]))
        ->assertSessionHasErrors(['convenios' => 'Los convenios deben sumar $1.000.000 (total menos la cuota inicial); sobran $100.000.']);

    expect(Venta::count())->toBe(0);
});

test('the same convenio can not be chosen twice', function () {
    $brilla = Convenio::factory()->create();

    $this->actingAs($this->asesor)
        ->post(route('ventas.store'), datosDeVenta([
            'convenios' => [convenioDeVenta($brilla, '625000'), convenioDeVenta($brilla, '625000')],
        ]))
        ->assertSessionHasErrors(['convenios.1.convenio_id' => 'Este convenio está repetido.']);
});

test('each convenio that requires it demands its administrative expense', function () {
    $krediya = Convenio::factory()->conGasto()->create();
    $addi = Convenio::factory()->create();

    $this->actingAs($this->asesor)
        ->post(route('ventas.store'), datosDeVenta([
            'convenios' => [convenioDeVenta($addi, '625000'), convenioDeVenta($krediya, '625000')],
        ]))
        ->assertSessionHasErrors(['convenios.1.gasto_administrativo' => 'Este convenio requiere el gasto administrativo.']);

    $this->actingAs($this->asesor)
        ->post(route('ventas.store'), datosDeVenta([
            'convenios' => [convenioDeVenta($addi, '625000', '20000'), convenioDeVenta($krediya, '625000', '45000')],
        ]))
        ->assertSessionHasNoErrors();

    $gastos = Venta::sole()->convenios->mapWithKeys(fn ($c) => [$c->id => $c->pivot->gasto_administrativo]);

    expect($gastos[$krediya->id])->toBe(45_000)
        ->and($gastos[$addi->id])->toBeNull();
});

test('the client contact details are stored', function () {
    $this->actingAs($this->asesor)->post(route('ventas.store'), datosDeVenta([
        'cliente_celular_2' => '3109876543',
    ]))->assertSessionHasNoErrors();

    expect(Venta::sole())
        ->cliente_telefono->toBe('6028201234')
        ->cliente_celular_2->toBe('3109876543')
        ->cliente_correo->toBe('ana.perez@example.com')
        ->cliente_direccion->toBe('Calle 5 # 10-20')
        ->cliente_ciudad->toBe('Popayán');
});

test('a down payment by transfer requires the transfer number', function () {
    $convenio = Convenio::factory()->create();
    $conInicial = [
        'valor_inicial' => '200000',
        'forma_pago_inicial' => 'transferencia',
        'convenios' => [convenioDeVenta($convenio, '1050000')],
    ];

    $this->actingAs($this->asesor)
        ->post(route('ventas.store'), datosDeVenta($conInicial))
        ->assertSessionHasErrors(['numero_transferencia' => 'Escribe el número de la transferencia.']);

    $this->actingAs($this->asesor)
        ->post(route('ventas.store'), datosDeVenta([...$conInicial, 'numero_transferencia' => 'TRF-998877']))
        ->assertSessionHasNoErrors();

    expect(Venta::sole())
        ->forma_pago_inicial->toBe(FormaPagoInicial::Transferencia)
        ->numero_transferencia->toBe('TRF-998877');
});

test('a down payment in cash does not keep a transfer number', function () {
    $convenio = Convenio::factory()->create();

    $this->actingAs($this->asesor)
        ->post(route('ventas.store'), datosDeVenta([
            'valor_inicial' => '200000',
            'forma_pago_inicial' => 'efectivo',
            'numero_transferencia' => 'TRF-1',
            'convenios' => [convenioDeVenta($convenio, '1050000')],
        ]))
        ->assertSessionHasNoErrors();

    expect(Venta::sole())
        ->forma_pago_inicial->toBe(FormaPagoInicial::Efectivo)
        ->numero_transferencia->toBeNull();
});

test('without a down payment the payment method is not asked nor kept', function () {
    $this->actingAs($this->asesor)
        ->post(route('ventas.store'), datosDeVenta([
            'valor_inicial' => '0',
            'forma_pago_inicial' => 'transferencia',
            'numero_transferencia' => 'TRF-1',
        ]))
        ->assertSessionHasNoErrors();

    expect(Venta::sole())
        ->forma_pago_inicial->toBeNull()
        ->numero_transferencia->toBeNull();
});

test('the sale is rejected when the data is invalid', function (array $cambios, string $campo) {
    $this->actingAs($this->asesor)
        ->post(route('ventas.store'), datosDeVenta($cambios))
        ->assertSessionHasErrors($campo);

    expect(Venta::count())->toBe(0);
})->with([
    'inicial mayor que el total' => [['valor_inicial' => '1250001', 'forma_pago_inicial' => 'efectivo'], 'valor_inicial'],
    'sin productos' => [['productos' => []], 'productos'],
    'producto sin artículo' => [['productos' => [['articulo' => '', 'marca' => 'LG', 'referencia' => 'X', 'serial' => '', 'valor' => '100']]], 'productos.0.articulo'],
    'producto en cero' => [['productos' => [['articulo' => 'TV', 'marca' => 'LG', 'referencia' => 'X', 'serial' => '', 'valor' => '0']]], 'productos.0.valor'],
    'sin convenios' => [['convenios' => []], 'convenios'],
    'convenio inactivo' => [fn () => ['convenios' => [convenioDeVenta(Convenio::factory()->inactivo()->create(), '1250000')]], 'convenios.0.convenio_id'],
    'celular sin 10 dígitos' => [['cliente_celular' => '300123'], 'cliente_celular'],
    'cédula con letras' => [['cliente_cedula' => '10A2345'], 'cliente_cedula'],
    'sin teléfono' => [['cliente_telefono' => ''], 'cliente_telefono'],
    'teléfono muy corto' => [['cliente_telefono' => '12345'], 'cliente_telefono'],
    'otro celular sin 10 dígitos' => [['cliente_celular_2' => '31012'], 'cliente_celular_2'],
    'sin correo' => [['cliente_correo' => ''], 'cliente_correo'],
    'correo inválido' => [['cliente_correo' => 'no-es-correo'], 'cliente_correo'],
    'sin dirección' => [['cliente_direccion' => ''], 'cliente_direccion'],
    'sin ciudad' => [['cliente_ciudad' => ''], 'cliente_ciudad'],
    'inicial sin forma de pago' => [['valor_inicial' => '100000'], 'forma_pago_inicial'],
    'forma de pago inventada' => [['valor_inicial' => '100000', 'forma_pago_inicial' => 'cheque'], 'forma_pago_inicial'],
]);

test('asesores only see their own recent sales and active convenios', function () {
    $propia = Venta::factory()->for($this->asesor, 'asesor')->create();
    Venta::factory()->create();
    Convenio::factory()->inactivo()->create();

    $this->actingAs($this->asesor)
        ->get(route('ventas.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('ventas/registrar')
            ->has('misVentas', 1)
            ->where('misVentas.0.consecutivo', $propia->consecutivo)
            ->where('misVentas.0.estado', 'pendiente')
            ->has('convenios', 2),
        );
});

test('only asesores can register sales', function () {
    $facturador = User::factory()->create(['rol' => Rol::Facturador]);

    $this->actingAs($facturador)
        ->post(route('ventas.store'), datosDeVenta())
        ->assertForbidden();

    expect(Venta::count())->toBe(0);
});
