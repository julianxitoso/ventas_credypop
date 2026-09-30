import AppLogoIcon from '@/components/app-logo-icon';
import type { AuthLayoutProps } from '@/types';

/**
 * Pantallas de acceso: panel de marca a la izquierda (solo en escritorio)
 * y formulario a la derecha.
 */
export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="grid min-h-dvh bg-background lg:grid-cols-[1.1fr_1fr]">
            <aside className="relative hidden overflow-hidden bg-[#2B3086] p-12 text-white lg:flex lg:flex-col lg:justify-between">
                <div
                    aria-hidden
                    className="absolute -right-40 -bottom-40 size-[34rem] rounded-full bg-[#EB4F1A]"
                />
                <div
                    aria-hidden
                    className="absolute -bottom-24 -left-24 size-[26rem] rounded-full bg-white/[0.06]"
                />

                <div className="relative flex items-center gap-3">
                    <AppLogoIcon className="h-8 w-auto [&_circle:first-child]:fill-white/90" />
                    <span className="text-lg font-extrabold tracking-tight">
                        CREDYPOP
                    </span>
                </div>

                <div className="relative max-w-md">
                    <p className="text-sm font-medium tracking-widest text-white/60 uppercase">
                        Electrodomésticos y Tecnología
                    </p>
                    <h2 className="mt-4 text-4xl leading-tight font-bold">
                        Registra, factura y sigue cada venta en un solo lugar.
                    </h2>
                </div>
            </aside>

            <main className="flex items-center justify-center p-6 sm:p-12">
                <div className="flex w-full max-w-sm flex-col gap-8">
                    <div className="flex flex-col items-center gap-6 text-center">
                        <img
                            src="/images/logo-credypop.png"
                            alt="CREDYPOP · Electrodomésticos y Tecnología"
                            className="h-24 w-auto dark:hidden"
                        />
                        <div className="hidden items-center gap-3 dark:flex">
                            <AppLogoIcon className="h-9 w-auto" />
                            <span className="text-2xl font-extrabold tracking-tight">
                                CREDYPOP
                            </span>
                        </div>

                        <div className="space-y-1.5">
                            <h1 className="text-2xl font-bold">{title}</h1>
                            <p className="text-sm text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </div>

                    {children}
                </div>
            </main>
        </div>
    );
}
