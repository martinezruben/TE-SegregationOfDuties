import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

import { ChartTooltipBox } from './ChartTooltip';

export interface TrendSeries {
    key: string;
    label: string;
    color: string; // var(--chart-n)
}

type Datum = { label: string } & Record<string, number | string>;

/** Líneas de 2px, marcadores de 8px con anillo del color de la superficie, crosshair que busca la X. */
export function TrendChart({ data, series }: { data: Datum[]; series: TrendSeries[] }) {
    if (data.every((d) => series.every((s) => Number(d[s.key]) === 0))) {
        return <p className="py-10 text-center text-sm text-muted-foreground">Sin datos en el período.</p>;
    }

    return (
        <div
            style={{ height: 260 }}
            role="img"
            aria-label={data.map((d) => `${d.label}: ${series.map((s) => `${d[s.key]} ${s.label}`).join(', ')}`).join('; ')}
        >
            <ResponsiveContainer width="100%" height="100%">
                <LineChart data={data} margin={{ top: 8, right: 16, bottom: 4, left: -12 }}>
                    <CartesianGrid vertical={false} stroke="var(--chart-grid)" strokeWidth={1} />
                    <XAxis
                        dataKey="label"
                        tickLine={false}
                        axisLine={{ stroke: 'var(--chart-axis)' }}
                        tick={{ fill: 'hsl(var(--muted-foreground))', fontSize: 12 }}
                        tickMargin={8}
                    />
                    <YAxis
                        allowDecimals={false}
                        tickLine={false}
                        axisLine={false}
                        tick={{ fill: 'hsl(var(--muted-foreground))', fontSize: 12 }}
                    />
                    <Tooltip
                        cursor={{ stroke: 'var(--chart-axis)', strokeWidth: 1 }}
                        content={({ active, payload, label }) =>
                            active && payload?.length ? (
                                <ChartTooltipBox
                                    title={String(label)}
                                    rows={series.map((s) => ({
                                        color: s.color,
                                        label: s.label,
                                        value: Number(payload.find((p) => p.dataKey === s.key)?.value ?? 0),
                                    }))}
                                />
                            ) : null
                        }
                    />
                    {series.map((s) => (
                        <Line
                            key={s.key}
                            type="linear"
                            dataKey={s.key}
                            stroke={s.color}
                            strokeWidth={2}
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            dot={{ r: 4, fill: s.color, stroke: 'hsl(var(--card))', strokeWidth: 2 }}
                            activeDot={{ r: 5, fill: s.color, stroke: 'hsl(var(--card))', strokeWidth: 2 }}
                            isAnimationActive={false}
                        />
                    ))}
                </LineChart>
            </ResponsiveContainer>
        </div>
    );
}
