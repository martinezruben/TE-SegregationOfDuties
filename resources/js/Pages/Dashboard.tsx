import { type ReactNode } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Separator } from '@/Components/ui/separator';
import { ShieldAlert, Clock, CheckCircle2, FileWarning } from 'lucide-react';

/**
 * Dashboard de Tareas Pendientes (context.md §8.8).
 * Datos de muestra; en producción llegan como props de Inertia desde el backend.
 */

type Kpi = { label: string; value: number; icon: ReactNode; hint: string };

const kpis: Kpi[] = [
    { label: 'Autorizaciones pendientes', value: 7, icon: <Clock className="text-info" />, hint: 'Asignadas a ti' },
    { label: 'Conflictos SoD abiertos', value: 3, icon: <ShieldAlert className="text-destructive" />, hint: 'Riesgo alto' },
    { label: 'Aprobadas hoy', value: 12, icon: <CheckCircle2 className="text-success" />, hint: 'Últimas 24h' },
    { label: 'Mis solicitudes', value: 2, icon: <FileWarning className="text-warning" />, hint: 'En proceso' },
];

type Pending = { id: number; user: string; role: string; app: string; risk: 'alto' | 'medio' | 'bajo' | null };

const pending: Pending[] = [
    { id: 1, user: 'María López (IGG 10245)', role: 'Aprobar Pago', app: 'ERP Finanzas', risk: 'alto' },
    { id: 2, user: 'Juan Pérez (IGG 10388)', role: 'Crear Orden', app: 'Compras', risk: 'medio' },
    { id: 3, user: 'Ana Ruiz (IGG 10502)', role: 'Consultar Reportes', app: 'BI', risk: null },
];

const riskVariant = (r: Pending['risk']) =>
    r === 'alto' ? 'destructive' : r === 'medio' ? 'warning' : r === 'bajo' ? 'success' : 'secondary';

export default function Dashboard() {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-foreground">Dashboard</h2>}
        >
            <Head title="Dashboard" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {/* KPIs */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {kpis.map((k) => (
                            <Card key={k.label}>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium text-muted-foreground">
                                        {k.label}
                                    </CardTitle>
                                    <span className="[&_svg]:size-5">{k.icon}</span>
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{k.value}</div>
                                    <p className="text-xs text-muted-foreground">{k.hint}</p>
                                </CardContent>
                            </Card>
                        ))}
                    </div>

                    {/* Bandeja de autorizaciones pendientes */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Autorizaciones pendientes</CardTitle>
                            <CardDescription>
                                Solicitudes que esperan tu aprobación. El color indica el nivel de riesgo SoD.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {pending.map((p, i) => (
                                <div key={p.id}>
                                    {i > 0 && <Separator className="mb-3" />}
                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="space-y-0.5">
                                            <div className="font-medium">{p.user}</div>
                                            <div className="text-sm text-muted-foreground">
                                                {p.role} · {p.app}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Badge variant={riskVariant(p.risk)}>
                                                {p.risk ? `Riesgo ${p.risk}` : 'Sin conflicto'}
                                            </Badge>
                                            <Button size="sm" variant="success">
                                                Aprobar
                                            </Button>
                                            <Button size="sm" variant="destructive">
                                                Rechazar
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
