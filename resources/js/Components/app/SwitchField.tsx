import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { cn } from '@/lib/utils';

/** Interruptor con su etiqueta asociada (clic en el texto también lo cambia). */
export function SwitchField({
    id,
    label,
    checked,
    onCheckedChange,
    disabled,
    className,
}: {
    id: string;
    label: string;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    disabled?: boolean;
    className?: string;
}) {
    return (
        <div className={cn('flex items-center gap-2', className)}>
            <Switch
                id={id}
                checked={checked}
                onCheckedChange={onCheckedChange}
                disabled={disabled}
            />
            <Label htmlFor={id} className="font-normal">
                {label}
            </Label>
        </div>
    );
}
