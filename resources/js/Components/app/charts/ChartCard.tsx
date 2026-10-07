import { ChartColumn, Table2 } from 'lucide-react';
import { PropsWithChildren, ReactNode, useState } from 'react';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { cn } from '@/lib/utils';

export interface ChartTable {
    columns: string[];
    rows: (string | number)[][];
}

/**
 * Tarjeta de gráfico con su gemelo accesible: el mismo dato como tabla.
 * Los valores nunca dependen del tooltip.
 */
export function ChartCard({
    title,
    description,
    table,
    legend,
    className,
    children,
}: PropsWithChildren<{ title: string; description?: ReactNode; table: ChartTable; legend?: ReactNode; className?: string }>) {
    const [view, setView] = useState<'chart' | 'table'>('chart');

    return (
        <Card className={className}>
            <CardHeader className="flex flex-row items-start justify-between gap-4 space-y-0">
                <div className="space-y-1">
                    <CardTitle className="text-base">{title}</CardTitle>
                    {description && <CardDescription>{description}</CardDescription>}
                </div>
                <div className="flex shrink-0 rounded-md border p-0.5" role="group" aria-label={`Vista de ${title}`}>
                    {([['chart', 'Gráfico', ChartColumn], ['table', 'Tabla', Table2]] as const).map(([v, label, Icon]) => (
                        <button
                            key={v}
                            type="button"
                            aria-pressed={view === v}
                            aria-label={label}
                            title={label}
                            onClick={() => setView(v)}
                            className={cn(
                                'rounded p-1.5 transition-colors',
                                view === v ? 'bg-secondary text-foreground' : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <Icon className="size-4" />
                        </button>
                    ))}
                </div>
            </CardHeader>
            <CardContent>
                {view === 'chart' ? (
                    <>
                        {legend && <div className="mb-3 flex flex-wrap gap-4 text-xs text-muted-foreground">{legend}</div>}
                        {children}
                    </>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {table.columns.map((c, i) => (
                                    <TableHead key={c} className={i > 0 ? 'text-right' : undefined}>
                                        {c}
                                    </TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {table.rows.map((r, i) => (
                                <TableRow key={i}>
                                    {r.map((cell, j) => (
                                        <TableCell key={j} className={j > 0 ? 'text-right tabular-nums' : undefined}>
                                            {cell}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </CardContent>
        </Card>
    );
}

/** Llave de leyenda: trazo corto del color de la serie + nombre en tinta secundaria. */
export function LegendKey({ color, label }: { color: string; label: string }) {
    return (
        <span className="inline-flex items-center gap-1.5">
            <span aria-hidden className="inline-block h-0.5 w-4 rounded" style={{ background: color }} />
            {label}
        </span>
    );
}
