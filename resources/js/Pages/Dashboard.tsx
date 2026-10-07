import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Separator } from '@/Components/ui/separator';
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

type Props = PageProps<{
    kpis: {
        autorizaciones_pendientes: number;
        conflictos_abiertos: number;
        conflictos_activos: number | null;
        aprobadas_hoy: number;
        mis_solicitudes: number;
    };
    pendientes: Pending[];
}>;

export default function Dashboard({ kpis, pendientes }: Props) {
    const { can } = usePermissions();
    const canApprove = can('solicitudes.autorizar');

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
