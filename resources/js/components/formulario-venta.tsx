import type { InertiaFormProps } from '@inertiajs/react';
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
import type { Convenio } from '@/types';

export const ventaVacia = {
    cliente_nombre: '',
    cliente_cedula: '',
    cliente_celular: '',
    cliente_correo: '',
    articulo: '',
    marca: '',
    referencia: '',
    serial: '',
    valor_venta: '',
    valor_inicial: '',
    convenio_id: '',
    gasto_administrativo: '',
    observaciones: '',
};

export type DatosVenta = typeof ventaVacia;

type CampoVenta = keyof DatosVenta;

type Props = {
    form: InertiaFormProps<DatosVenta>;
    convenios: Convenio[];
    textoBoton: string;
    onSubmit: (event: FormEvent) => void;
};

/**
 * Formulario de venta del asesor, usado para registrar y para corregir.
 */
export default function FormularioVenta({
    form,
    convenios,
    textoBoton,
    onSubmit,
}: Props) {
    const { data, setData, errors, processing } = form;

    const convenio = convenios.find((c) => String(c.id) === data.convenio_id);
    const requiereGasto = convenio?.requiere_gasto ?? false;

    function campoTexto(
        campo: CampoVenta,
        etiqueta: string,
        props: ComponentProps<typeof Input> = {},
    ) {
        return (
            <Campo id={campo} etiqueta={etiqueta} error={errors[campo]}>
                <Input
                    id={campo}
                    value={data[campo]}
                    onChange={(e) => setData(campo, e.target.value)}
                    aria-invalid={Boolean(errors[campo])}
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
                </div>
                {campoTexto('cliente_correo', 'Correo (opcional)', {
                    type: 'email',
                    autoComplete: 'off',
                })}
            </Seccion>

            <Seccion titulo="Artículo">
                {campoTexto('articulo', 'Artículo', { required: true })}
                <div className="grid gap-4 sm:grid-cols-2">
                    {campoTexto('marca', 'Marca', { required: true })}
                    {campoTexto('referencia', 'Referencia', { required: true })}
                </div>
                {campoTexto('serial', 'Serial', { required: true })}
            </Seccion>

            <Seccion titulo="Valores">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Campo
                        id="valor_venta"
                        etiqueta="Valor de venta"
                        error={errors.valor_venta}
                    >
                        <CampoPesos
                            id="valor_venta"
                            required
                            placeholder="$0"
                            value={data.valor_venta}
                            onValueChange={(v) => setData('valor_venta', v)}
                            aria-invalid={Boolean(errors.valor_venta)}
                        />
                    </Campo>
                    <Campo
                        id="valor_inicial"
                        etiqueta="Valor de inicial"
                        error={errors.valor_inicial}
                    >
                        <CampoPesos
                            id="valor_inicial"
                            placeholder="$0"
                            value={data.valor_inicial}
                            onValueChange={(v) => setData('valor_inicial', v)}
                            aria-invalid={Boolean(errors.valor_inicial)}
                        />
                    </Campo>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Campo
                        id="convenio_id"
                        etiqueta="Convenio"
                        error={errors.convenio_id}
                    >
                        <Select
                            value={data.convenio_id}
                            onValueChange={(v) =>
                                setData((actual) => ({
                                    ...actual,
                                    convenio_id: v,
                                    gasto_administrativo: '',
                                }))
                            }
                        >
                            <SelectTrigger
                                id="convenio_id"
                                className="w-full"
                                aria-invalid={Boolean(errors.convenio_id)}
                            >
                                <SelectValue placeholder="Selecciona un convenio" />
                            </SelectTrigger>
                            <SelectContent>
                                {convenios.map((c) => (
                                    <SelectItem key={c.id} value={String(c.id)}>
                                        {c.nombre}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Campo>

                    {requiereGasto && (
                        <Campo
                            id="gasto_administrativo"
                            etiqueta="Gasto administrativo"
                            error={errors.gasto_administrativo}
                        >
                            <CampoPesos
                                id="gasto_administrativo"
                                required
                                placeholder="$0"
                                value={data.gasto_administrativo}
                                onValueChange={(v) =>
                                    setData('gasto_administrativo', v)
                                }
                                aria-invalid={Boolean(
                                    errors.gasto_administrativo,
                                )}
                            />
                        </Campo>
                    )}
                </div>

                <Campo
                    id="observaciones"
                    etiqueta="Observaciones (opcional)"
                    error={errors.observaciones}
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

            <InputError
                message={(errors as Record<string, string>).venta}
                className="text-center"
            />

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
