import { Bar, BarChart, CartesianGrid, LabelList, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

import { ChartTooltipBox } from './ChartTooltip';

export interface BarDatum {
    label: string;
    value: number;
    detail?: string;
}

const ROW = 38; // alto por fila: barra de 20px con aire alrededor

/** Barras horizontales de una sola serie: un color, valor en la punta, esquina redondeada solo en el extremo de datos. */
export function HBarChart({ data, unit }: { data: BarDatum[]; unit: string }) {
    if (data.every((d) => d.value === 0)) {
        return <p className="py-10 text-center text-sm text-muted-foreground">Sin datos en el período.</p>;
    }

    return (
        <div style={{ height: data.length * ROW + 16 }} role="img" aria-label={data.map((d) => `${d.label}: ${d.value}`).join('; ')}>
            <ResponsiveContainer width="100%" height="100%">
                <BarChart data={data} layout="vertical" margin={{ top: 4, right: 36, bottom: 4, left: 0 }} barCategoryGap={8}>
                    <CartesianGrid horizontal={false} stroke="var(--chart-grid)" strokeWidth={1} />
                    <XAxis type="number" hide allowDecimals={false} domain={[0, 'dataMax']} />
                    <YAxis
                        type="category"
                        dataKey="label"
                        width={130}
                        tickLine={false}
                        axisLine={{ stroke: 'var(--chart-axis)' }}
                        tick={{ fill: 'hsl(var(--muted-foreground))', fontSize: 12 }}
                    />
                    <Tooltip
                        cursor={{ fill: 'hsl(var(--muted))', opacity: 0.5 }}
                        content={({ active, payload }) => {
                            const d = active ? (payload?.[0]?.payload as BarDatum | undefined) : undefined;
                            return d ? (
                                <ChartTooltipBox title={d.label} note={d.detail} rows={[{ color: 'var(--chart-1)', label: unit, value: d.value }]} />
                            ) : null;
                        }}
                    />
                    <Bar dataKey="value" fill="var(--chart-1)" barSize={20} radius={[0, 4, 4, 0]} isAnimationActive={false}>
                        <LabelList dataKey="value" position="right" fill="hsl(var(--foreground))" fontSize={12} />
                    </Bar>
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}
