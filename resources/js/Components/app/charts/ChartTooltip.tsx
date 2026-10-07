/** Tooltip: el valor manda (tinta fuerte), el nombre de la serie va detrás; llaves de línea, no cajas. */
interface Row {
    color: string;
    label: string;
    value: number;
}

export function ChartTooltipBox({ title, rows, note }: { title: string; rows: Row[]; note?: string }) {
    return (
        <div className="rounded-md border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-md">
            <div className="mb-1 font-medium">{title}</div>
            {note && <div className="mb-1 text-muted-foreground">{note}</div>}
            {rows.map((r) => (
                <div key={r.label} className="flex items-center gap-2">
                    <span aria-hidden className="inline-block h-0.5 w-3 rounded" style={{ background: r.color }} />
                    <span className="text-sm font-semibold tabular-nums">{r.value}</span>
                    <span className="text-muted-foreground">{r.label}</span>
                </div>
            ))}
        </div>
    );
}
