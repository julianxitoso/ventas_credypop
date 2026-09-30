import { Head, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index, store, update } from '@/routes/admin/convenios';

type ConvenioAdmin = {
    id: number;
    nombre: string;
    requiere_gasto: boolean;
    activo: boolean;
    ventas_count: number;
};

type Props = {
    convenios: ConvenioAdmin[];
};

export default function Convenios({ convenios }: Props) {
    const form = useForm({ nombre: '', requiere_gasto: false });

    function crear(event: FormEvent) {
        event.preventDefault();
        form.post(store().url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    function actualizar(
        convenio: ConvenioAdmin,
        cambios: Partial<Pick<ConvenioAdmin, 'activo' | 'requiere_gasto'>>,
    ) {
        router.patch(update(convenio.id).url, cambios, {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="Convenios" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <h1 className="text-2xl font-bold">Convenios</h1>

                <form
                    onSubmit={crear}
                    className="flex flex-col gap-4 rounded-xl border p-4 md:flex-row md:items-end"
                >
                    <div className="grid flex-1 gap-2">
                        <Label htmlFor="nombre">Nuevo convenio</Label>
                        <Input
                            id="nombre"
                            required
                            maxLength={100}
                            placeholder="Nombre del convenio"
                            value={form.data.nombre}
                            onChange={(e) =>
                                form.setData('nombre', e.target.value)
                            }
                        />
                        <InputError message={form.errors.nombre} />
                    </div>
                    <div className="flex items-center gap-2 md:pb-2">
                        <Checkbox
                            id="requiere_gasto"
                            checked={form.data.requiere_gasto}
                            onCheckedChange={(v) =>
                                form.setData('requiere_gasto', v === true)
                            }
                        />
                        <Label htmlFor="requiere_gasto">
                            Requiere gasto administrativo
                        </Label>
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? <Spinner /> : <Plus />}
                        Crear convenio
                    </Button>
                </form>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Convenio
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Requiere gasto
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Ventas
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Estado
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {convenios.map((convenio) => (
                                <tr
                                    key={convenio.id}
                                    className={
                                        convenio.activo
                                            ? 'border-t'
                                            : 'border-t text-muted-foreground'
                                    }
                                >
                                    <td className="px-4 py-3 font-medium">
                                        {convenio.nombre}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Checkbox
                                            aria-label={`Requiere gasto: ${convenio.nombre}`}
                                            checked={convenio.requiere_gasto}
                                            onCheckedChange={(v) =>
                                                actualizar(convenio, {
                                                    requiere_gasto: v === true,
                                                })
                                            }
                                        />
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {convenio.ventas_count}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                convenio.activo
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {convenio.activo
                                                ? 'Activo'
                                                : 'Inactivo'}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                actualizar(convenio, {
                                                    activo: !convenio.activo,
                                                })
                                            }
                                        >
                                            {convenio.activo
                                                ? 'Desactivar'
                                                : 'Activar'}
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <p className="text-sm text-muted-foreground">
                    Un convenio desactivado deja de aparecer en el formulario
                    del asesor. Sus ventas anteriores no cambian.
                </p>
            </div>
        </>
    );
}

Convenios.layout = {
    breadcrumbs: [{ title: 'Convenios', href: index() }],
};
