import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, TriangleAlert } from 'lucide-react';
import type { FormEvent } from 'react';
import FormularioVenta from '@/components/formulario-venta';
import type { DatosVenta } from '@/components/formulario-venta';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { create, update } from '@/routes/ventas';
import type { Convenio } from '@/types';

type Props = {
    convenios: Convenio[];
    venta: {
        id: number;
        consecutivo: string;
        motivo_devolucion: string | null;
        datos: DatosVenta;
    };
};

export default function CorregirVenta({ convenios, venta }: Props) {
    const form = useForm<DatosVenta>(venta.datos);

    function guardar(event: FormEvent) {
        event.preventDefault();
        form.put(update(venta.id).url);
    }

    return (
        <>
            <Head title={`Corregir venta #${venta.consecutivo}`} />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
                <div className="flex items-center gap-3">
                    <Link
                        href={create()}
                        className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" />
                        Volver
                    </Link>
                    <h1 className="text-2xl font-bold">
                        Corregir VENTA #{venta.consecutivo}
                    </h1>
                </div>

                {venta.motivo_devolucion && (
                    <Alert className="border-red-300 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-100">
                        <TriangleAlert />
                        <AlertTitle>Motivo de la devolución</AlertTitle>
                        <AlertDescription className="whitespace-pre-line text-red-900 dark:text-red-100">
                            {venta.motivo_devolucion}
                        </AlertDescription>
                    </Alert>
                )}

                <FormularioVenta
                    form={form}
                    convenios={convenios}
                    textoBoton="GUARDAR CORRECCIÓN"
                    onSubmit={guardar}
                />
            </div>
        </>
    );
}

CorregirVenta.layout = {
    breadcrumbs: [{ title: 'Registrar venta', href: create() }],
};
