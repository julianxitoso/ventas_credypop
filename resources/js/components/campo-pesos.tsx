import type { ComponentProps } from 'react';
import { Input } from '@/components/ui/input';
import { formatearPesos } from '@/lib/formato';

type Props = Omit<ComponentProps<'input'>, 'value' | 'onChange' | 'type'> & {
    /** Solo dígitos, sin puntos ni signo: "1250000". */
    value: string;
    onValueChange: (digitos: string) => void;
};

/**
 * Campo de dinero que muestra "$1.250.000" mientras se escribe
 * y entrega únicamente los dígitos.
 */
export default function CampoPesos({ value, onValueChange, ...props }: Props) {
    return (
        <Input
            {...props}
            type="text"
            inputMode="numeric"
            autoComplete="off"
            value={value === '' ? '' : formatearPesos(Number(value))}
            onChange={(event) =>
                onValueChange(
                    event.target.value.replace(/\D/g, '').slice(0, 12),
                )
            }
        />
    );
}
