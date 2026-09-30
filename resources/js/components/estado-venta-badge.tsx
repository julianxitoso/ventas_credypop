import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { EstadoVenta } from '@/types';

const estilos: Record<EstadoVenta, { etiqueta: string; clases: string }> = {
    pendiente: {
        etiqueta: 'Pendiente',
        clases: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    },
    facturada: {
        etiqueta: 'Facturada',
        clases: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    },
    devuelta: {
        etiqueta: 'Devuelta',
        clases: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    },
    caida: {
        etiqueta: 'Caída',
        clases: 'bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300',
    },
};

export default function EstadoVentaBadge({
    estado,
    className,
}: {
    estado: EstadoVenta;
    className?: string;
}) {
    const { etiqueta, clases } = estilos[estado];

    return (
        <Badge
            variant="outline"
            className={cn('border-transparent', clases, className)}
        >
            {etiqueta}
        </Badge>
    );
}
