import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { Paginado } from '@/types';

export default function Paginacion<T>({ pagina }: { pagina: Paginado<T> }) {
    const { meta, links } = pagina;

    if (meta.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex items-center justify-between gap-3 text-sm">
            <p className="text-muted-foreground">
                {meta.from}–{meta.to} de {meta.total}
            </p>
            <div className="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!links.prev}
                    asChild={Boolean(links.prev)}
                >
                    {links.prev ? (
                        <Link href={links.prev} preserveState preserveScroll>
                            Anterior
                        </Link>
                    ) : (
                        <span>Anterior</span>
                    )}
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!links.next}
                    asChild={Boolean(links.next)}
                >
                    {links.next ? (
                        <Link href={links.next} preserveState preserveScroll>
                            Siguiente
                        </Link>
                    ) : (
                        <span>Siguiente</span>
                    )}
                </Button>
            </div>
        </div>
    );
}
