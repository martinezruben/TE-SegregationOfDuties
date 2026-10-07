import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Separator } from '@/Components/ui/separator';
import { ChartCard, LegendKey } from '@/Components/app/charts/ChartCard';
import { BarDatum, HBarChart } from '@/Components/app/charts/HBarChart';
import { TrendChart } from '@/Components/app/charts/TrendChart';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { ShieldAlert, Clock, CheckCircle2, FileWarning } from 'lucide-react';
import { usePermissions } from '@/hooks/usePermissions';
import { PageProps } from '@/types';

interface Pending {
    id: number;
    user: string;
    igg: string;
    role: string;
    application: string;
    has_sod_conflict: boolean;
    is_major: boolean;
}

interface Charts {
    days: number;
    by_status: BarDatum[];
    conflicts_trend: { label: string; detectados: number; aceptados: number }[];
    aging: BarDatum[];
    top_apps: BarDatum[];
    top_roles: BarDatum[];
}

const PERIODS = [30, 90, 180, 365];

type Props = PageProps<{
    kpis: {
        autorizaciones_pendientes: number;
        conflictos_abiertos: number;
        conflictos_activos: number | null;
        aprobadas_hoy: number;
        mis_solicitudes: number;
    };
    pendientes: Pending[];
    charts: Charts | null;
}>;

export default function Dashboard({ kpis, pendientes, charts }: Props) {
    const { can } = usePermissions();
    const canApprove = can('solicitudes.autorizar');
    const [loading, setLoading] = useState(false);

    // Un único filtro de período para todos los gráficos; mientras recarga se conserva el render anterior.
    const changePeriod = (days: string) =>
        router.get(route('dashboard'), { days }, {
            only: ['charts'],
            preserveState: true,
            preserveScroll: true,
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });

    const act = (id: number, action: 'aprobar' | 'rechazar') => {
        router.post(route(action === 'aprobar' ? 'solicitudes.approve' : 'solicitudes.reject', id), {}, { preserveScroll: true });
    };

    const cards = [
        { label: 'Autorizaciones pendientes', value: kpis.autorizaciones_pendientes, icon: <Clock className="text-info" />, hint: 'Asignadas a ti' },
        { label: 'Conflictos SoD abiertos', value: kpis.conflictos_abiertos, icon: <ShieldAlert className="text-destructive" />, hint: 'En solicitudes' },
        ...(kpis.conflictos_activos !== null
            ? [{ label: 'Conflictos en la foto', value: kpis.conflictos_activos, icon: <ShieldAlert className="text-warning" />, hint: 'Accesos vigentes en conflicto', href: route('reportes.conflictos') }]
            : []),
        { label: 'Aprobadas hoy', value: kpis.aprobadas_hoy, icon: <CheckCircle2 className="text-success" />, hint: 'Últimas 24h' },
        { label: 'Mis solicitudes', value: kpis.mis_solicitudes, icon: <FileWarning className="text-warning" />, hint: 'En proceso' },
    ];

    return (
        <AppLayout title="Dashboard">
            <Head title="Dashboard" />

            <div className="w-full space-y-6">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    {cards.map((k) => (
                        <Card key={k.label} className={'href' in k ? 'transition-colors hover:bg-accent/40' : undefined}>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium text-muted-foreground">{k.label}</CardTitle>
                                <span className="[&_svg]:size-5">{k.icon}</span>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{k.value}</div>
                                <p className="text-xs text-muted-foreground">
                                    {'href' in k ? <Link href={k.href as string} className="underline">{k.hint}</Link> : k.hint}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {charts && (
                    <section aria-labelledby="charts-title" className="space-y-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h2 id="charts-title" className="text-lg font-semibold">Indicadores</h2>
                            <div className="flex items-center gap-2">
                                <Label htmlFor="period" className="text-sm text-muted-foreground">Período</Label>
                                <Select value={String(charts.days)} onValueChange={changePeriod}>
                                    <SelectTrigger id="period" className="w-44"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        {PERIODS.map((d) => (
                                            <SelectItem key={d} value={String(d)}>Últimos {d} días</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className={`grid grid-cols-1 gap-4 transition-opacity lg:grid-cols-3 ${loading ? 'opacity-60' : ''}`} aria-busy={loading}>
                            <ChartCard
                                title="Solicitudes por estado"
                                description={`Creadas en los últimos ${charts.days} días.`}
                                table={{ columns: ['Estado', 'Solicitudes'], rows: charts.by_status.map((d) => [d.label, d.value]) }}
                            >
                                <HBarChart data={charts.by_status} unit="solicitudes" />
                            </ChartCard>

                            <ChartCard
                                className="lg:col-span-2"
                                title="Conflictos SoD: detectados vs aceptados"
                                description="Solicitudes con conflicto por mes, y cuántas se aprobaron pese a él."
                                legend={<><LegendKey color="var(--chart-1)" label="Detectados" /><LegendKey color="var(--chart-2)" label="Aceptados (aprobados)" /></>}
                                table={{ columns: ['Mes', 'Detectados', 'Aceptados'], rows: charts.conflicts_trend.map((d) => [d.label, d.detectados, d.aceptados]) }}
                            >
                                <TrendChart
                                    data={charts.conflicts_trend}
                                    series={[
                                        { key: 'detectados', label: 'Detectados', color: 'var(--chart-1)' },
                                        { key: 'aceptados', label: 'Aceptados (aprobados)', color: 'var(--chart-2)' },
                                    ]}
                                />
                            </ChartCard>

                            <ChartCard
                                title="Autorizaciones pendientes por antigüedad"
                                description="Pasos abiertos hoy; no depende del período."
                                table={{ columns: ['Antigüedad', 'Pasos pendientes'], rows: charts.aging.map((d) => [d.label, d.value]) }}
                            >
                                <HBarChart data={charts.aging} unit="pasos pendientes" />
                            </ChartCard>

                            <ChartCard
                                title="Aplicaciones más solicitadas"
                                description="Las 5 con más solicitudes en el período."
                                table={{ columns: ['Aplicación', 'Solicitudes'], rows: charts.top_apps.map((d) => [d.label, d.value]) }}
                            >
                                <HBarChart data={charts.top_apps} unit="solicitudes" />
                            </ChartCard>

                            <ChartCard
                                title="Roles más solicitados"
                                description="Los 5 con más solicitudes en el período."
                                table={{ columns: ['Rol', 'Aplicación', 'Solicitudes'], rows: charts.top_roles.map((d) => [d.label, d.detail ?? '', d.value]) }}
                            >
                                <HBarChart data={charts.top_roles} unit="solicitudes" />
                            </ChartCard>
                        </div>
                    </section>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Autorizaciones pendientes</CardTitle>
                        <CardDescription>Solicitudes que esperan tu aprobación.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {pendientes.length === 0 && (
                            <p className="py-6 text-center text-sm text-muted-foreground">No tienes autorizaciones pendientes. 🎉</p>
                        )}
                        {pendientes.map((p, i) => (
                            <div key={p.id}>
                                {i > 0 && <Separator className="mb-3" />}
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="space-y-0.5">
                                        <div className="font-medium">
                                            {p.user} <span className="text-xs text-muted-foreground">({p.igg})</span>
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            {p.role} · {p.application}
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {p.is_major && <Badge variant="info">Autorización mayor</Badge>}
                                        {p.has_sod_conflict ? (
                                            <Badge variant="destructive">Conflicto SoD</Badge>
                                        ) : (
                                            <Badge variant="secondary">Sin conflicto</Badge>
                                        )}
                                        <Button size="sm" variant="outline" asChild>
                                            <Link href={route('solicitudes.show', p.id)}>Ver detalle</Link>
                                        </Button>
                                        {canApprove && (
                                            <>
                                                <Button size="sm" variant="success" onClick={() => act(p.id, 'aprobar')}>
                                                    Aprobar
                                                </Button>
                                                <Button size="sm" variant="destructive" onClick={() => act(p.id, 'rechazar')}>
                                                    Rechazar
                                                </Button>
                                            </>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
