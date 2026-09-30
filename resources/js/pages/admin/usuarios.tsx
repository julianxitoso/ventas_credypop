import { Head, router, useForm } from '@inertiajs/react';
import { KeyRound, UserPlus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { contrasena, estado, index, store } from '@/routes/admin/usuarios';

type Usuario = {
    id: number;
    name: string;
    usuario: string;
    email: string | null;
    rol: string;
    rol_etiqueta: string;
    activo: boolean;
    ventas_count: number;
    es_actual: boolean;
};

type Rol = { value: string; etiqueta: string };

type Props = {
    usuarios: Usuario[];
    roles: Rol[];
};

export default function Usuarios({ usuarios, roles }: Props) {
    const [creando, setCreando] = useState(false);
    const [restableciendo, setRestableciendo] = useState<Usuario | null>(null);

    function cambiarEstado(usuario: Usuario) {
        if (
            usuario.activo &&
            !window.confirm(
                `¿Desactivar a ${usuario.name} (${usuario.usuario})? No podrá ingresar al sistema.`,
            )
        ) {
            return;
        }

        router.patch(
            estado(usuario.id).url,
            { activo: !usuario.activo },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Usuarios" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-bold">Usuarios</h1>
                    <Button onClick={() => setCreando(true)}>
                        <UserPlus />
                        Nuevo usuario
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Nombre
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Usuario
                                </th>
                                <th className="px-4 py-3 font-medium">Rol</th>
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
                            {usuarios.map((usuario) => (
                                <tr
                                    key={usuario.id}
                                    className={
                                        usuario.activo
                                            ? 'border-t'
                                            : 'border-t text-muted-foreground'
                                    }
                                >
                                    <td className="px-4 py-3">
                                        <p className="font-medium">
                                            {usuario.name}
                                        </p>
                                        {usuario.email && (
                                            <p className="text-muted-foreground">
                                                {usuario.email}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 font-mono">
                                        {usuario.usuario}
                                    </td>
                                    <td className="px-4 py-3">
                                        {usuario.rol_etiqueta}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {usuario.ventas_count}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                usuario.activo
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {usuario.activo
                                                ? 'Activo'
                                                : 'Inactivo'}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex justify-end gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setRestableciendo(usuario)
                                                }
                                            >
                                                <KeyRound />
                                                Contraseña
                                            </Button>
                                            {!usuario.es_actual && (
                                                <Button
                                                    size="sm"
                                                    variant={
                                                        usuario.activo
                                                            ? 'outline'
                                                            : 'secondary'
                                                    }
                                                    onClick={() =>
                                                        cambiarEstado(usuario)
                                                    }
                                                >
                                                    {usuario.activo
                                                        ? 'Desactivar'
                                                        : 'Activar'}
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <NuevoUsuarioDialog
                abierto={creando}
                roles={roles}
                onClose={() => setCreando(false)}
            />

            <RestablecerContrasenaDialog
                usuario={restableciendo}
                onClose={() => setRestableciendo(null)}
            />
        </>
    );
}

function NuevoUsuarioDialog({
    abierto,
    roles,
    onClose,
}: {
    abierto: boolean;
    roles: Rol[];
    onClose: () => void;
}) {
    const form = useForm({
        name: '',
        usuario: '',
        rol: 'asesor',
        email: '',
        password: '',
        password_confirmation: '',
    });

    function cerrar() {
        form.reset();
        form.clearErrors();
        onClose();
    }

    function crear(event: FormEvent) {
        event.preventDefault();
        form.post(store().url, { preserveScroll: true, onSuccess: cerrar });
    }

    return (
        <Dialog open={abierto} onOpenChange={(open) => !open && cerrar()}>
            <DialogContent>
                <form onSubmit={crear} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>Nuevo usuario</DialogTitle>
                        <DialogDescription>
                            Comunícale a la persona su usuario y contraseña.
                        </DialogDescription>
                    </DialogHeader>

                    <Campo
                        id="name"
                        etiqueta="Nombre completo"
                        error={form.errors.name}
                    >
                        <Input
                            id="name"
                            required
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                    </Campo>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo
                            id="usuario"
                            etiqueta="Usuario"
                            error={form.errors.usuario}
                        >
                            <Input
                                id="usuario"
                                required
                                autoComplete="off"
                                autoCapitalize="none"
                                placeholder="Ej. A-014"
                                value={form.data.usuario}
                                onChange={(e) =>
                                    form.setData('usuario', e.target.value)
                                }
                            />
                        </Campo>

                        <Campo id="rol" etiqueta="Rol" error={form.errors.rol}>
                            <Select
                                value={form.data.rol}
                                onValueChange={(v) => form.setData('rol', v)}
                            >
                                <SelectTrigger id="rol" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {roles.map((rol) => (
                                        <SelectItem
                                            key={rol.value}
                                            value={rol.value}
                                        >
                                            {rol.etiqueta}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Campo>
                    </div>

                    <Campo
                        id="email"
                        etiqueta="Correo (opcional)"
                        error={form.errors.email}
                    >
                        <Input
                            id="email"
                            type="email"
                            autoComplete="off"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                        />
                    </Campo>

                    <CamposContrasena form={form} />

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={cerrar}
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Crear usuario
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function RestablecerContrasenaDialog({
    usuario,
    onClose,
}: {
    usuario: Usuario | null;
    onClose: () => void;
}) {
    const form = useForm({ password: '', password_confirmation: '' });

    function cerrar() {
        form.reset();
        form.clearErrors();
        onClose();
    }

    function guardar(event: FormEvent) {
        event.preventDefault();

        if (!usuario) {
            return;
        }

        form.put(contrasena(usuario.id).url, {
            preserveScroll: true,
            onSuccess: cerrar,
        });
    }

    return (
        <Dialog
            open={usuario !== null}
            onOpenChange={(open) => !open && cerrar()}
        >
            <DialogContent>
                <form onSubmit={guardar} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>Restablecer contraseña</DialogTitle>
                        <DialogDescription>
                            {usuario?.name} ({usuario?.usuario}). Se cerrarán
                            las sesiones que tenga abiertas.
                        </DialogDescription>
                    </DialogHeader>

                    <CamposContrasena form={form} />

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={cerrar}
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Guardar contraseña
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function CamposContrasena({
    form,
}: {
    form: {
        data: { password: string; password_confirmation: string };
        setData: (
            campo: 'password' | 'password_confirmation',
            valor: string,
        ) => void;
        errors: Partial<Record<'password' | 'password_confirmation', string>>;
    };
}) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <Campo
                id="password"
                etiqueta="Contraseña (mín. 10)"
                error={form.errors.password}
            >
                <PasswordInput
                    id="password"
                    required
                    minLength={10}
                    autoComplete="new-password"
                    value={form.data.password}
                    onChange={(e) => form.setData('password', e.target.value)}
                />
            </Campo>
            <Campo
                id="password_confirmation"
                etiqueta="Repite la contraseña"
                error={form.errors.password_confirmation}
            >
                <PasswordInput
                    id="password_confirmation"
                    required
                    minLength={10}
                    autoComplete="new-password"
                    value={form.data.password_confirmation}
                    onChange={(e) =>
                        form.setData('password_confirmation', e.target.value)
                    }
                />
            </Campo>
        </div>
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

Usuarios.layout = {
    breadcrumbs: [{ title: 'Usuarios', href: index() }],
};
