import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center">
                <AppLogoIcon className="h-6 w-auto" />
            </div>
            <div className="ml-1 grid flex-1 text-left leading-tight">
                <span className="truncate text-sm font-extrabold tracking-tight">
                    CREDYPOP
                </span>
                <span className="truncate text-xs text-muted-foreground">
                    Registro de ventas
                </span>
            </div>
        </>
    );
}
