import { useFlashToast } from '@/hooks/use-flash-toast';
import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            position="bottom-right"
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                    // Debajo de las ventanas (z-50) para que un aviso que se queda
                    // abierto nunca tape los botones de una ventana de detalle.
                    zIndex: 40,
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };
