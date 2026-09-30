const formatoMiles = new Intl.NumberFormat('es-CO', {
    maximumFractionDigits: 0,
});

const formatoCompacto = new Intl.NumberFormat('es-CO', {
    notation: 'compact',
    maximumFractionDigits: 1,
});

/**
 * Formato colombiano de pesos enteros: 1250000 → "$1.250.000".
 */
export function formatearPesos(valor: number): string {
    return `$${formatoMiles.format(valor)}`;
}

/**
 * Pesos abreviados para ejes de gráficas: 1250000 → "$1,3 M".
 */
export function formatearPesosCorto(valor: number): string {
    return `$${formatoCompacto.format(valor)}`;
}
