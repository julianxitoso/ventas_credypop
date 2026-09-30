export type EstadoVenta = 'pendiente' | 'facturada' | 'devuelta' | 'caida';

export type Convenio = {
    id: number;
    nombre: string;
    requiere_gasto: boolean;
};

export type VentaPanel = {
    id: number;
    consecutivo: string;
    fecha: string;
    asesor: string;
    cliente_nombre: string;
    cliente_cedula: string;
    cliente_celular: string;
    cliente_correo: string | null;
    articulo: string;
    marca: string;
    referencia: string;
    serial: string;
    valor_venta: number;
    valor_inicial: number;
    convenio: string;
    gasto_administrativo: number | null;
    observaciones: string | null;
    estado: EstadoVenta;
    numero_factura: string | null;
    facturada_en: string | null;
    facturada_por: string | null;
    motivo_devolucion: string | null;
    devuelta_en: string | null;
    devuelta_por: string | null;
    corregida_en: string | null;
    motivo_caida: string | null;
    caida_en: string | null;
};

export type PestanaPanel = 'pendientes' | 'historial';

export type FiltrosPanel = {
    pestana: PestanaPanel;
    buscar: string;
    estado: EstadoVenta | '';
};

export type IndicadoresPanel = {
    pendientes: number;
    facturadasHoy: number;
    devueltasHoy: number;
    valorPendiente: number;
};

export type Paginado<T> = {
    data: T[];
    links: {
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
};

export type VentaReciente = {
    id: number;
    consecutivo: string;
    fecha: string;
    cliente_nombre: string;
    articulo: string;
    valor_venta: number;
    estado: EstadoVenta;
    numero_factura: string | null;
    motivo_devolucion: string | null;
    motivo_caida: string | null;
    corregida: boolean;
    puede_marcar_caida: boolean;
};
