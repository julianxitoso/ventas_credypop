export type EstadoVenta =
    | 'pendiente'
    | 'con_serie'
    | 'facturada'
    | 'devuelta'
    | 'caida';

/** Quién debe corregir una venta devuelta. */
export type ResponsableDevolucion = 'asesor' | 'bodega';

export type Convenio = {
    id: number;
    nombre: string;
    requiere_gasto: boolean;
};

export type ProductoVenta = {
    id: number;
    articulo: string;
    marca: string;
    referencia: string;
    serial: string | null;
    valor: number;
};

export type ConvenioVenta = {
    nombre: string;
    /** Valor que cubre el convenio en esta venta. */
    valor: number;
    gasto_administrativo: number | null;
};

export type VentaPanel = {
    id: number;
    consecutivo: string;
    fecha: string;
    asesor: string;
    cliente_nombre: string;
    cliente_cedula: string;
    cliente_celular: string;
    cliente_telefono: string | null;
    cliente_celular_2: string | null;
    cliente_correo: string | null;
    cliente_direccion: string | null;
    cliente_ciudad: string | null;
    /** "Televisor (+2 más)". */
    productos_resumen: string;
    /** Series de los productos separadas por coma, o null si ninguno tiene. */
    serial_resumen: string | null;
    productos: ProductoVenta[];
    /** Total de la venta: suma de los productos. */
    valor_venta: number;
    valor_inicial: number;
    /** Etiqueta legible ("Efectivo", "Transferencia electrónica") o null si no hubo inicial. */
    forma_pago_inicial: string | null;
    numero_transferencia: string | null;
    convenios: ConvenioVenta[];
    observaciones: string | null;
    estado: EstadoVenta;
    numero_factura: string | null;
    facturada_en: string | null;
    facturada_por: string | null;
    series_asignadas_en: string | null;
    series_asignadas_por: string | null;
    motivo_devolucion: string | null;
    devuelta_en: string | null;
    devuelta_por: string | null;
    devuelta_a: ResponsableDevolucion | null;
    devolucion_por_serie: boolean;
    devolucion_por_otros: boolean;
    /** Bodega debe revisar las series cuando el asesor corrija. */
    revisar_serie: boolean;
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
    /** Listas para facturar. */
    conSerie: number;
    /** Esperando que bodega asigne la serie. */
    sinSerie: number;
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
    devuelta_a: ResponsableDevolucion | null;
    motivo_caida: string | null;
    corregida: boolean;
    puede_marcar_caida: boolean;
};
