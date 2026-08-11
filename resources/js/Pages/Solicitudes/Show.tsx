import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Ban, CheckCircle2, Clock, MessageSquare, ShieldAlert, Zap } from 'lucide-react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Separator } from '@/Components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { PageProps } from '@/types';

interface Finding {
    role: string;
    risk_level: 'alto' | 'medio' | 'bajo';
}
interface Approval {
    step_order: number;
    approver_role: string;
    reasons: string[];
    approver: string | null;
    status: 'pendiente' | 'aprobado' | 'rechazado' | 'omitido';
    is_auto: boolean;
    comments: string | null;
    resolved_at: string | null;
    is_current: boolean;
}
interface Solicitud {
    id: number;
    type: string;
    status: string;
    status_label: string;
    status_variant: string;
    is_major: boolean;
    auto_resolved: boolean;
    has_sod_conflict: boolean;
    justification: string | null;
    created_at: string;
    requester: string;
    user: { name: string; igg: string; area: string | null; cargo: string | null };
    role: string;
    application: string;
    criticality: 'baja' | 'media' | 'alta';
    cargo: { status: 'permitido' | 'no_permitido' | 'sin_regla'; allowed: boolean } | null;
    reasons: string[];
    supervisor_missing: boolean;
    findings: Finding[];
    approvals: Approval[];
}

type Props = PageProps<{ solicitud: Solicitud; canApprove: boolean }>;

const riskVariant = (r: string) => (r === 'alto' ? 'destructive' : r === 'medio' ? 'warning' : 'success');
const critVariant = (c: string) => (c === 'alta' ? 'destructive' : c === 'media' ? 'warning' : 'success');
const cargoLabel: Record<string, string> = {
    permitido: 'Permitido por el cargo',
    no_permitido: 'No permitido por el cargo',
    sin_regla: 'Sin regla de cargo (no permitido por defecto)',
};
const roleLabel: Record<string, string> = {
    owner_app: 'Owner de la aplicación',
    seguridad: 'Gestor de Seguridad',
    jefe: 'Su supervisor (jefe)',
    autorizador: 'Autorizador designado',
    sistema: 'Sistema (automático)',
};
const apprVariant = (s: string) => (s === 'aprobado' ? 'success' : s === 'rechazado' ? 'destructive' : s === 'omitido' ? 'secondary' : 'warning');

export default function SolicitudShow({ solicitud: s, canApprove }: Props) {
    const [comments, setComments] = useState('');

    const act = (action: 'aprobar' | 'rechazar') => {
        router.post(route(action === 'aprobar' ? 'solicitudes.approve' : 'solicitudes.reject', s.id), { comments }, { preserveScroll: true });
    };

    return (
        <AppLayout title={`Solicitud #${s.id}`}>
            <Head title={`Solicitud #${s.id}`} />

            <div className="mx-auto max-w-4xl space-y-4">
                <div className="flex items-center justify-between">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={route('solicitudes.index')}>
                            <ArrowLeft className="size-4" /> Solicitudes
                        </Link>
                    </Button>
                    <Badge variant={s.status_variant as never}>{s.status_label}</Badge>
                </div>

                {/* Resumen */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {s.user.name} <span className="font-mono text-xs text-muted-foreground">({s.user.igg})</span>
                        </CardTitle>
                        <CardDescription>
                            Solicita <strong>{s.role}</strong> en <strong>{s.application}</strong>
                            <Badge variant={critVariant(s.criticality) as never} className="ml-2">criticidad {s.criticality}</Badge>
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid grid-cols-2 gap-2 text-sm">
                        <div><span className="text-muted-foreground">Área:</span> {s.user.area ?? '—'}</div>
                        <div><span className="text-muted-foreground">Cargo:</span> {s.user.cargo ?? '—'}</div>
                        <div><span className="text-muted-foreground">Solicitante:</span> {s.requester}</div>
                        <div><span className="text-muted-foreground">Fecha:</span> {s.created_at}</div>
                        {s.justification && <div className="col-span-2"><span className="text-muted-foreground">Justificación:</span> {s.justification}</div>}
                    </CardContent>
                </Card>

                {/* Detalle de la validación */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Validación</CardTitle>
                        <CardDescription>Resultado de las reglas evaluadas al crear la solicitud.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {/* Regla de cargo */}
                        {s.cargo && (
                            <Alert variant={s.cargo.allowed ? 'success' : 'destructive'}>
                                {s.cargo.allowed ? <CheckCircle2 className="size-4" /> : <Ban className="size-4" />}
                                <AlertTitle>Regla de cargo</AlertTitle>
                                <AlertDescription>{cargoLabel[s.cargo.status]}</AlertDescription>
                            </Alert>
                        )}

                        {/* Conflictos de matriz */}
                        {s.has_sod_conflict ? (
                            <Alert variant="destructive">
                                <ShieldAlert className="size-4" />
                                <AlertTitle>Conflictos de Segregación de Funciones</AlertTitle>
                                <AlertDescription>
                                    <ul className="mt-1 space-y-1">
                                        {s.findings.map((f, i) => (
                                            <li key={i} className="flex items-center gap-2">
                                                <Badge variant={riskVariant(f.risk_level) as never}>{f.risk_level}</Badge>
                                                <span>en conflicto con <strong>{f.role}</strong> (foto actual)</span>
                                            </li>
                                        ))}
                                    </ul>
                                </AlertDescription>
                            </Alert>
                        ) : (
                            <Alert variant="success">
                                <CheckCircle2 className="size-4" />
                                <AlertTitle>Sin conflictos SoD</AlertTitle>
                                <AlertDescription>El rol no genera conflicto contra la foto actual.</AlertDescription>
                            </Alert>
                        )}

                        {s.supervisor_missing && (
                            <Alert variant="warning">
                                <ShieldAlert className="size-4" />
                                <AlertTitle>Sin jefe/supervisor asignado</AlertTitle>
                                <AlertDescription>
                                    Una regla exige la autorización de "su supervisor" pero el usuario no lo tiene
                                    definido; el paso se derivó a Seguridad como respaldo.
                                </AlertDescription>
                            </Alert>
                        )}

                        {/* Por qué requiere aprobación */}
                        {s.reasons.length > 0 && (
                            <div>
                                <p className="mb-1 text-sm font-medium">¿Por qué requiere aprobación?</p>
                                <ul className="list-disc space-y-0.5 pl-5 text-sm text-muted-foreground">
                                    {s.reasons.map((r, i) => (
                                        <li key={i}>{r}</li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Cadena de aprobación */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Flujo de autorización</CardTitle>
                        <CardDescription>Pasos requeridos y su estado.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-10">#</TableHead>
                                    <TableHead>Paso</TableHead>
                                    <TableHead>Aprobador</TableHead>
                                    <TableHead>Estado</TableHead>
                                    <TableHead>Resuelto</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {s.approvals.map((a) => (
                                    <TableRow key={a.step_order} className={a.is_current ? 'bg-muted/50' : ''}>
                                        <TableCell>{a.step_order}</TableCell>
                                        <TableCell>
                                            {roleLabel[a.approver_role] ?? a.approver_role}
                                            {a.is_current && <Badge variant="info" className="ml-2">actual</Badge>}
                                            {a.reasons.length > 0 && (
                                                <ul className="mt-1 list-disc pl-4 text-xs text-muted-foreground">
                                                    {a.reasons.map((r, i) => (
                                                        <li key={i}>{r}</li>
                                                    ))}
                                                </ul>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">{a.approver ?? (a.is_auto ? '—' : 'Sin asignar')}</TableCell>
                                        <TableCell><Badge variant={apprVariant(a.status) as never}>{a.status}</Badge></TableCell>
                                        <TableCell className="text-xs text-muted-foreground">{a.resolved_at ?? '—'}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {/* Bitácora / timeline de la autorización */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Bitácora de la autorización</CardTitle>
                        <CardDescription>Qué decidió cada autorizador, con su comentario y fecha.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ol className="relative space-y-6 border-l pl-6">
                            {s.approvals.map((a) => {
                                const resolved = a.status === 'aprobado' || a.status === 'rechazado';
                                const dot =
                                    a.status === 'aprobado'
                                        ? 'bg-green-600 text-white'
                                        : a.status === 'rechazado'
                                          ? 'bg-destructive text-white'
                                          : a.status === 'omitido'
                                            ? 'bg-muted text-muted-foreground'
                                            : 'bg-background text-muted-foreground ring-1 ring-border';
                                const Icon = a.is_auto
                                    ? Zap
                                    : a.status === 'aprobado'
                                      ? CheckCircle2
                                      : a.status === 'rechazado'
                                        ? Ban
                                        : Clock;
                                const verb =
                                    a.status === 'aprobado'
                                        ? a.is_auto
                                            ? 'Aprobado automáticamente'
                                            : 'Aprobó'
                                        : a.status === 'rechazado'
                                          ? 'Rechazó'
                                          : a.status === 'omitido'
                                            ? 'Omitido'
                                            : 'En espera de decisión';

                                return (
                                    <li key={a.step_order} className="ml-1">
                                        <span className={`absolute -left-[13px] flex size-6 items-center justify-center rounded-full ${dot}`}>
                                            <Icon className="size-3.5" />
                                        </span>
                                        <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <span className="text-xs font-medium text-muted-foreground">Paso {a.step_order}</span>
                                            <span className="font-medium">{a.approver ?? (a.is_auto ? 'Sistema' : 'Sin asignar')}</span>
                                            <Badge variant="secondary" className="text-[10px]">
                                                {roleLabel[a.approver_role] ?? a.approver_role}
                                            </Badge>
                                            {a.is_current && <Badge variant="info" className="text-[10px]">actual</Badge>}
                                        </div>
                                        <p className="mt-0.5 text-sm">
                                            <span className={a.status === 'rechazado' ? 'text-destructive' : resolved ? 'text-green-700' : 'text-muted-foreground'}>
                                                {verb}
                                            </span>
                                            {a.resolved_at && <span className="text-muted-foreground"> · {a.resolved_at}</span>}
                                        </p>
                                        {a.comments && (
                                            <div className="mt-1.5 flex gap-2 rounded-md border bg-muted/40 p-2 text-sm">
                                                <MessageSquare className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                                <p className="italic">"{a.comments}"</p>
                                            </div>
                                        )}
                                    </li>
                                );
                            })}
                        </ol>
                    </CardContent>
                </Card>

                {/* Acción del aprobador */}
                {canApprove && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Tu decisión</CardTitle>
                            <CardDescription>Eres el aprobador del paso actual.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <textarea
                                className="min-h-20 w-full rounded-md border border-input bg-transparent p-2 text-sm"
                                placeholder="Comentario (opcional)…"
                                value={comments}
                                onChange={(e) => setComments(e.target.value)}
                            />
                            <Separator />
                            <div className="flex justify-end gap-2">
                                <Button variant="destructive" onClick={() => act('rechazar')}>Rechazar</Button>
                                <Button variant="success" onClick={() => act('aprobar')}>Aprobar</Button>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
