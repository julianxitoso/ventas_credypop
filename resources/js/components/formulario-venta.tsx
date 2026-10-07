import type { InertiaFormProps } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useEffect } from 'react';
import type { ComponentProps, FormEvent, ReactNode } from 'react';
import CampoPesos from '@/components/campo-pesos';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { formatearPesos } from '@/lib/formato';
import { cn } from '@/lib/utils';
import type { Convenio } from '@/types';

/**
 * La serie la asigna bodega: aquí solo se muestra. El id identifica los
 * productos ya guardados al corregir, para conservar su serie.
 */
export type ProductoFormulario = {
    id?: number;
    articulo: string;
    marca: string;
    referencia: string;
    serial?: string | null;
    valor: string;
};

export type ConvenioFormulario = {
    convenio_id: string;
    valor: string;
    gasto_administrativo: string;
};

export type DatosVenta = {
    cliente_nombre: string;
    cliente_cedula: string;
    cliente_celular: string;
    cliente_telefono: string;
    cliente_celular_2: string;
    cliente_correo: string;
    cliente_direccion: string;
    cliente_ciudad: string;
    productos: ProductoFormulario[];
    valor_inicial: string;
    forma_pago_inicial: string;
    numero_transferencia: string;
    convenios: ConvenioFormulario[];
    observaciones: string;
    /** Solo al corregir: qué cambió el asesor. Siempre empieza vacío. */
    nota_correccion?: string;
};

const productoVacio: ProductoFormulario = {
    articulo: '',
    marca: '',
    referencia: '',
    valor: '',
};

const convenioVacio: ConvenioFormulario = {
    convenio_id: '',
    valor: '',
    gasto_administrativo: '',
};

export const ventaVacia: DatosVenta = {
    cliente_nombre: '',
    cliente_cedula: '',
    cliente_celular: '',
    cliente_telefono: '',
    cliente_celular_2: '',
    cliente_correo: '',
    cliente_direccion: '',
    cliente_ciudad: '',
    productos: [productoVacio],
    valor_inicial: '',
    forma_pago_inicial: '',
    numero_transferencia: '',
    convenios: [convenioVacio],
    observaciones: '',
};

type CampoTexto = Exclude<keyof DatosVenta, 'productos' | 'convenios'>;

type Props = {
    form: InertiaFormProps<DatosVenta>;
    convenios: Convenio[];
    textoBoton: string;
    onSubmit: (event: FormEvent) => void;
};

/**
 * Formulario de venta del asesor, usado para registrar y para corregir.
 * Una venta tiene uno o más productos (el total es su suma) y uno o más
 * convenios cuyos valores suman el total menos la cuota inicial.
 */
export default function FormularioVenta({
    form,
    convenios,
    textoBoton,
    onSubmit,
}: Props) {
    const { data, setData, processing } = form;
    const errores = form.errors as Record<string, string | undefined>;

    const total = data.productos.reduce(
        (suma, p) => suma + Number(p.valor || 0),
        0,
    );
    const inicial = Number(data.valor_inicial || 0);
    const hayInicial = inicial > 0;
    const porCubrir = Math.max(0, total - inicial);
    const asignado = data.convenios.reduce(
        (suma, c) => suma + Number(c.valor || 0),
        0,
    );
    const unSoloConvenio = data.convenios.length === 1;

    // Con un solo convenio su valor no puede ser otro que el total menos la inicial.
    useEffect(() => {
        if (unSoloConvenio && data.convenios[0].valor !== String(porCubrir)) {
            setData('convenios', [
                { ...data.convenios[0], valor: String(porCubrir) },
            ]);
        }
    }, [unSoloConvenio, porCubrir]);

    function actualizarProducto(
        posicion: number,
        cambios: Partial<ProductoFormulario>,
    ) {
        setData(
            'productos',
            data.productos.map((p, i) =>
                i === posicion ? { ...p, ...cambios } : p,
            ),
        );
    }

    function actualizarConvenio(
        posicion: number,
        cambios: Partial<ConvenioFormulario>,
    ) {
        setData(
            'convenios',
            data.convenios.map((c, i) =>
                i === posicion ? { ...c, ...cambios } : c,
            ),
        );
    }

    function campoTexto(
        campo: CampoTexto,
        etiqueta: string,
        props: ComponentProps<typeof Input> = {},
    ) {
        return (
            <Campo id={campo} etiqueta={etiqueta} error={errores[campo]}>
                <Input
                    id={campo}
                    value={data[campo]}
                    onChange={(e) => setData(campo, e.target.value)}
                    aria-invalid={Boolean(errores[campo])}
                    {...props}
                />
            </Campo>
        );
    }

    return (
        <form onSubmit={onSubmit} className="flex flex-col gap-6">
            <Seccion titulo="Cliente">
                {campoTexto('cliente_nombre', 'Nombres completos', {
                    required: true,
                    autoComplete: 'off',
                })}
                <div className="grid gap-4 sm:grid-cols-2">
                    {campoTexto('cliente_cedula', 'Cédula', {
                        required: true,
                        inputMode: 'numeric',
                        maxLength: 12,
                    })}
                    {campoTexto('cliente_celular', 'Celular', {
                        required: true,
                        inputMode: 'numeric',
                        maxLength: 10,
                        placeholder: '3001234567',
                    })}
                    {campoTexto('cliente_telefono', 'Teléfono', {
                        required: true,
                        inputMode: 'numeric',
                        maxLength: 10,
                        placeholder: 'Fijo o celular',
                    })}
                    {campoTexto(
                        'cliente_celular_2',
                        'Otro celular (opcional)',
                        {
                            inputMode: 'numeric',
                            maxLength: 10,
                        },
                    )}
                </div>
                {campoTexto('cliente_correo', 'Correo', {
                    required: true,
                    type: 'email',
                    autoComplete: 'off',
                })}
                <div className="grid gap-4 sm:grid-cols-2">
                    {campoTexto('cliente_direccion', 'Dirección', {
                        required: true,
                        maxLength: 200,
                        autoComplete: 'off',
                    })}
                    {campoTexto('cliente_ciudad', 'Ciudad', {
                        required: true,
                        maxLength: 100,
                    })}
                </div>
            </Seccion>

            <Seccion titulo="Productos">
                {data.productos.map((producto, i) => {
                    const id = (campo: string) => `productos.${i}.${campo}`;

                    return (
                        <div
                            key={i}
                            className="flex flex-col gap-4 rounded-lg border bg-muted/30 p-4"
                        >
                            <div className="flex items-center justify-between">
                                <p className="text-sm font-semibold">
                                    Producto {i + 1}
                                </p>
                                {data.productos.length > 1 && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            setData(
                                                'productos',
                                                data.productos.filter(
                                                    (_, j) => j !== i,
                                                ),
                                            )
                                        }
                                    >
                                        <Trash2 />
                                        Quitar
                                    </Button>
                                )}
                            </div>

                            <Campo
                                id={id('articulo')}
                                etiqueta="Artículo"
                                error={errores[id('articulo')]}
                            >
                                <Input
                                    id={id('articulo')}
                                    required
                                    maxLength={150}
                                    value={producto.articulo}
                                    onChange={(e) =>
                                        actualizarProducto(i, {
                                            articulo: e.target.value,
                                        })
                                    }
                                    aria-invalid={Boolean(
                                        errores[id('articulo')],
                                    )}
                                />
                            </Campo>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Campo
                                    id={id('marca')}
                                    etiqueta="Marca"
                                    error={errores[id('marca')]}
                                >
                                    <Input
                                        id={id('marca')}
                                        required
                                        maxLength={80}
                                        value={producto.marca}
                                        onChange={(e) =>
                                            actualizarProducto(i, {
                                                marca: e.target.value,
                                            })
                                        }
                                        aria-invalid={Boolean(
                                            errores[id('marca')],
                                        )}
                                    />
                                </Campo>
                                <Campo
                                    id={id('referencia')}
                                    etiqueta="Referencia"
                                    error={errores[id('referencia')]}
                                >
                                    <Input
                                        id={id('referencia')}
                                        required
                                        maxLength={80}
                                        value={producto.referencia}
                                        onChange={(e) =>
                                            actualizarProducto(i, {
                                                referencia: e.target.value,
                                            })
                                        }
                                        aria-invalid={Boolean(
                                            errores[id('referencia')],
                                        )}
                                    />
                                </Campo>
                                <Campo
                                    id={id('valor')}
                                    etiqueta="Valor"
                                    error={errores[id('valor')]}
                                >
                                    <CampoPesos
                                        id={id('valor')}
                                        required
                                        placeholder="$0"
                                        value={producto.valor}
                                        onValueChange={(v) =>
                                            actualizarProducto(i, { valor: v })
                                        }
                                        aria-invalid={Boolean(
                                            errores[id('valor')],
                                        )}
                                    />
                                </Campo>
                            </div>
                            {producto.serial && (
                                <p className="text-sm text-muted-foreground">
                                    Serie asignada por bodega:{' '}
                                    <span className="font-medium text-foreground">
                                        {producto.serial}
                                    </span>
                                </p>
                            )}
                        </div>
                    );
                })}

                <InputError message={errores.productos} />

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() =>
                            setData('productos', [
                                ...data.productos,
                                productoVacio,
                            ])
                        }
                    >
                        <Plus />
                        Agregar producto
                    </Button>
                    <p className="text-right">
                        <span className="text-sm text-muted-foreground">
                            Total de la venta{' '}
                        </span>
                        <span className="text-xl font-bold">
                            {formatearPesos(total)}
                        </span>
                    </p>
                </div>
            </Seccion>

            <Seccion titulo="Cuota inicial">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Campo
                        id="valor_inicial"
                        etiqueta="Valor de inicial"
                        error={errores.valor_inicial}
                    >
                        <CampoPesos
                            id="valor_inicial"
                            placeholder="$0"
                            value={data.valor_inicial}
                            onValueChange={(v) =>
                                setData((actual) =>
                                    Number(v) > 0
                                        ? { ...actual, valor_inicial: v }
                                        : {
                                              ...actual,
                                              valor_inicial: v,
                                              forma_pago_inicial: '',
                                              numero_transferencia: '',
                                          },
                                )
                            }
                            aria-invalid={Boolean(errores.valor_inicial)}
                        />
                    </Campo>

                    {hayInicial && (
                        <Campo
                            id="forma_pago_inicial"
                            etiqueta="¿Cómo pagó la cuota inicial?"
                            error={errores.forma_pago_inicial}
                        >
                            <Select
                                value={data.forma_pago_inicial}
                                onValueChange={(v) =>
                                    setData((actual) => ({
                                        ...actual,
                                        forma_pago_inicial: v,
                                        numero_transferencia:
                                            v === 'transferencia'
                                                ? actual.numero_transferencia
                                                : '',
                                    }))
                                }
                            >
                                <SelectTrigger
                                    id="forma_pago_inicial"
                                    className="w-full"
                                    aria-invalid={Boolean(
                                        errores.forma_pago_inicial,
                                    )}
                                >
                                    <SelectValue placeholder="Selecciona la forma de pago" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="efectivo">
                                        Efectivo
                                    </SelectItem>
                                    <SelectItem value="transferencia">
                                        Transferencia electrónica
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </Campo>
                    )}

                    {hayInicial &&
                        data.forma_pago_inicial === 'transferencia' &&
                        campoTexto(
                            'numero_transferencia',
                            'Número de transferencia',
                            {
                                required: true,
                                maxLength: 50,
                            },
                        )}
                </div>
            </Seccion>

            <Seccion titulo="Convenios">
                {data.convenios.map((fila, i) => {
                    const id = (campo: string) => `convenios.${i}.${campo}`;
                    const elegido = convenios.find(
                        (c) => String(c.id) === fila.convenio_id,
                    );
                    const usadosEnOtrasFilas = data.convenios
                        .filter((_, j) => j !== i)
                        .map((c) => c.convenio_id);

                    return (
                        <div
                            key={i}
                            className="grid gap-4 rounded-lg border bg-muted/30 p-4 sm:grid-cols-[1fr_1fr_auto] sm:items-start"
                        >
                            <Campo
                                id={id('convenio_id')}
                                etiqueta="Convenio"
                                error={errores[id('convenio_id')]}
                            >
                                <Select
                                    value={fila.convenio_id}
                                    onValueChange={(v) =>
                                        actualizarConvenio(i, {
                                            convenio_id: v,
                                            gasto_administrativo: '',
                                        })
                                    }
                                >
                                    <SelectTrigger
                                        id={id('convenio_id')}
                                        className="w-full"
                                        aria-invalid={Boolean(
                                            errores[id('convenio_id')],
                                        )}
                                    >
                                        <SelectValue placeholder="Selecciona un convenio" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {convenios
                                            .filter(
                                                (c) =>
                                                    !usadosEnOtrasFilas.includes(
                                                        String(c.id),
                                                    ),
                                            )
                                            .map((c) => (
                                                <SelectItem
                                                    key={c.id}
                                                    value={String(c.id)}
                                                >
                                                    {c.nombre}
                                                </SelectItem>
                                            ))}
                                    </SelectContent>
                                </Select>
                            </Campo>

                            <Campo
                                id={id('valor')}
                                etiqueta="Valor que cubre"
                                error={errores[id('valor')]}
                            >
                                <CampoPesos
                                    id={id('valor')}
                                    required
                                    placeholder="$0"
                                    value={fila.valor}
                                    readOnly={unSoloConvenio}
                                    onValueChange={(v) =>
                                        actualizarConvenio(i, { valor: v })
                                    }
                                    aria-invalid={Boolean(errores[id('valor')])}
                                    className={cn(unSoloConvenio && 'bg-muted')}
                                />
                            </Campo>

                            {data.convenios.length > 1 ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="sm:mt-7"
                                    onClick={() =>
                                        setData(
                                            'convenios',
                                            data.convenios.filter(
                                                (_, j) => j !== i,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 />
                                    Quitar
                                </Button>
                            ) : (
                                <span className="hidden sm:block" />
                            )}

                            {elegido?.requiere_gasto && (
                                <Campo
                                    id={id('gasto_administrativo')}
                                    etiqueta={`Gasto administrativo de ${elegido.nombre}`}
                                    error={errores[id('gasto_administrativo')]}
                                >
                                    <CampoPesos
                                        id={id('gasto_administrativo')}
                                        required
                                        placeholder="$0"
                                        value={fila.gasto_administrativo}
                                        onValueChange={(v) =>
                                            actualizarConvenio(i, {
                                                gasto_administrativo: v,
                                            })
                                        }
                                        aria-invalid={Boolean(
                                            errores[id('gasto_administrativo')],
                                        )}
                                    />
                                </Campo>
                            )}
                        </div>
                    );
                })}

                <InputError message={errores.convenios} />

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={data.convenios.length >= convenios.length}
                        onClick={() =>
                            setData('convenios', [
                                ...data.convenios,
                                convenioVacio,
                            ])
                        }
                    >
                        <Plus />
                        Agregar convenio
                    </Button>
                    <CuadreConvenios
                        porCubrir={porCubrir}
                        asignado={asignado}
                    />
                </div>
            </Seccion>

            <Seccion titulo="Observaciones">
                <Campo
                    id="observaciones"
                    etiqueta="Observaciones (opcional)"
                    error={errores.observaciones}
                >
                    <textarea
                        id="observaciones"
                        rows={3}
                        maxLength={1000}
                        value={data.observaciones}
                        onChange={(e) =>
                            setData('observaciones', e.target.value)
                        }
                        className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                    />
                </Campo>
            </Seccion>

            {data.nota_correccion !== undefined && (
                <Seccion titulo="Corrección">
                    <Campo
                        id="nota_correccion"
                        etiqueta="¿Qué corregiste?"
                        error={errores.nota_correccion}
                    >
                        <textarea
                            id="nota_correccion"
                            required
                            rows={3}
                            maxLength={1000}
                            value={data.nota_correccion}
                            onChange={(e) =>
                                setData('nota_correccion', e.target.value)
                            }
                            placeholder="Ej. Corregí la cédula del cliente"
                            aria-invalid={Boolean(errores.nota_correccion)}
                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                        />
                    </Campo>
                </Seccion>
            )}

            <InputError message={errores.venta} className="text-center" />

            <Button
                type="submit"
                size="lg"
                className="w-full text-base font-semibold"
                disabled={processing}
            >
                {processing && <Spinner />}
                {textoBoton}
            </Button>
        </form>
    );
}

/**
 * Muestra en vivo cuánto falta o sobra para que los convenios cuadren.
 */
function CuadreConvenios({
    porCubrir,
    asignado,
}: {
    porCubrir: number;
    asignado: number;
}) {
    const diferencia = porCubrir - asignado;
    const cuadra = diferencia === 0;

    return (
        <p
            className={cn(
                'rounded-md px-3 py-1.5 text-sm',
                cuadra
                    ? 'bg-green-50 text-green-800 dark:bg-green-950 dark:text-green-200'
                    : 'bg-amber-50 text-amber-900 dark:bg-amber-950 dark:text-amber-100',
            )}
        >
            Debe sumar <strong>{formatearPesos(porCubrir)}</strong> (total −
            inicial) · Asignado <strong>{formatearPesos(asignado)}</strong>
            {!cuadra &&
                (diferencia > 0
                    ? ` · Falta ${formatearPesos(diferencia)}`
                    : ` · Sobra ${formatearPesos(-diferencia)}`)}
        </p>
    );
}

function Seccion({
    titulo,
    children,
}: {
    titulo: string;
    children: ReactNode;
}) {
    return (
        <Card className="gap-4">
            <CardHeader>
                <CardTitle>{titulo}</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {children}
            </CardContent>
        </Card>
    );
}

function Campo({
    id,
    etiqueta,
    error,
    children,
}: {
    id: string;
    etiqueta: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{etiqueta}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
