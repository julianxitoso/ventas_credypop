export type User = {
    id: number;
    name: string;
    usuario: string;
    email: string | null;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Permisos = {
    registrarVentas: boolean;
    verVentas: boolean;
    facturar: boolean;
    verDashboard: boolean;
    administrar: boolean;
};

export type Auth = {
    user: User;
    permisos: Permisos | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
