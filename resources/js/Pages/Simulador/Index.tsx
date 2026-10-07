import { useMemo, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, FlaskConical, Ban, UserCheck, UserX } from 'lucide-react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Label } from '@/Components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Separator } from '@/Components/ui/separator';
import { Combobox } from '@/Components/app/Combobox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { usePermissions } from '@/hooks/usePermissions';
import { PageProps } from '@/types';

interface UserRef {
    id: number;
    name: string;
    igg: string;
}
interface AppWithRoles {
    id: number;
    name: string;
    roles: { id: number; application_id: number; name: string }[];
}
interface Conflict {
    role_id: number;
    role_name: string;
    risk_level: 'alto' | 'medio' | 'bajo';
    description: string | null;
}
interface Evaluation {
    cargo: { status: 'permitido' | 'no_permitido' | 'sin_regla'; allowed: boolean };
    conflicts: Conflict[];
    has_conflict: boolean;
    already_assigned: boolean;
    viable: boolean;
}
interface AuthStep {
    authorizer: string;
    authorizer_type: 'fixed' | 'supervisor';
    reasons: string[];
    unresolved: boolean;
}
interface Authorization {
    matched: boolean;
    steps: AuthStep[];
    supervisor_required: boolean;
    supervisor_missing: boolean;
}

type Props = PageProps<{
    usuarios: UserRef[];
    aplicaciones: AppWithRoles[];
    filters: { managed_user_id: number | null; role_id: number | null };
    evaluation: Evaluation | null;
    authorization: Authorization | null;
    target: {
        user: { id: number; name: string; igg: string };
        role: { id: number; name: string; application: string | null };
    } | null;
}>;

const riskVariant = (r: string) => (r === 'alto' ? 'destructive' : r === 'medio' ? 'warning' : 'success');

export default function SimuladorIndex({ usuarios, aplicaciones, filters, evaluation, authorization, target }: Props) {
    const [userId, setUserId] = useState(filters.managed_user_id ? String(filters.managed_user_id) : '');
    const [appId, setAppId] = useState<string>(() => {
        if (!filters.role_id) return '';
        const app = aplicaciones.find((a) => a.roles.some((r) => r.id === filters.role_id));
        return app ? String(app.id) : '';
    });
    const [roleId, setRoleId] = useState(filters.role_id ? String(filters.role_id) : '');

    const roles = useMemo(() => aplicaciones.find((a) => String(a.id) === appId)?.roles ?? [], [appId, aplicaciones]);
    const { can } = usePermissions();
    const page = usePage<PageProps>().props;
    const flash = page.flash;
    const errors = (page as { errors?: Record<string, string> }).errors ?? {};
    const errorMessages = Object.values(errors);
    const [submitting, setSubmitting] = useState(false);

    const simulate = () => {
        if (!userId || !roleId) return;
        router.get(route('simulador.index'), { managed_user_id: userId, role_id: roleId }, { preserveState: true });
    };

    const createRequest = () => {
        if (!target) return;
        setSubmitting(true);
        router.post(
            route('solicitudes.store'),
            { managed_user_id: target.user.id, requested_role_id: target.role.id },
            { preserveScroll: true, onFinish: () => setSubmitting(false) },
        );
    };

    const cargoLabel = {
        permitido: 'Permitido por el cargo',
        no_permitido: 'No permitido por el cargo',
        sin_regla: 'Sin regla de cargo (no permitido por defecto)',
    };

    return (
        <AppLayout title="Simulador SoD">
            <Head title="Simulador SoD" />

            <div className="grid w-full gap-4 lg:grid-cols-2">
                {/* Panel de selección */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <FlaskConical className="size-5" /> Simular acceso
                        </CardTitle>
                        <CardDescription>Verifica cargo y conflictos SoD antes de solicitar un rol.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="sim-user">Usuario gestionado</Label>
                            <Combobox
                                id="sim-user"
                                value={userId}
                                onChange={setUserId}
                                placeholder="Selecciona un usuario…"
                                searchPlaceholder="Buscar por nombre o IGG…"
                                options={usuarios.map((u) => ({ value: String(u.id), label: u.name, description: u.igg }))}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="sim-app">Aplicación</Label>
                            <Select value={appId} onValueChange={(v) => { setAppId(v); setRoleId(''); }}>
                                <SelectTrigger id="sim-app"><SelectValue placeholder="Selecciona…" /></SelectTrigger>
                                <SelectContent>
                                    {aplicaciones.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)}>{a.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="sim-role">Rol deseado</Label>
                            <Combobox
                                id="sim-role"
                                value={roleId}
                                onChange={setRoleId}
                                disabled={!appId}
                                placeholder={appId ? 'Selecciona un rol…' : 'Primero elige la aplicación'}
                                searchPlaceholder="Buscar rol…"
                                options={roles.map((r) => ({ value: String(r.id), label: r.name }))}
                            />
                        </div>
                        <Button onClick={simulate} disabled={!userId || !roleId} className="w-full">
                            Simular
                        </Button>
                    </CardContent>
                </Card>

                {/* Panel de resultado */}
                <Card>
                    <CardHeader>
                        <CardTitle>Resultado</CardTitle>
                        <CardDescription>
                            {target ? `${target.user.name} → ${target.role.name} (${target.role.application})` : 'Ejecuta una simulación.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {!evaluation && <p className="text-sm text-muted-foreground">Sin resultados todavía.</p>}

                        {evaluation && (
                            <>
                                {/* Regla de cargo */}
                                <Alert variant={evaluation.cargo.allowed ? 'success' : 'destructive'}>
                                    {evaluation.cargo.allowed ? <CheckCircle2 className="size-4" /> : <Ban className="size-4" />}
                                    <AlertTitle>Regla de cargo</AlertTitle>
                                    <AlertDescription>{cargoLabel[evaluation.cargo.status]}</AlertDescription>
                                </Alert>

                                {/* Conflictos SoD */}
                                {evaluation.has_conflict ? (
                                    <Alert variant="destructive">
                                        <AlertTriangle className="size-4" />
                                        <AlertTitle>Conflicto de Segregación de Funciones</AlertTitle>
                                        <AlertDescription>
                                            <p className="mb-2">El rol entra en conflicto con la foto actual:</p>
                                            <ul className="space-y-1">
                                                {evaluation.conflicts.map((c) => (
                                                    <li key={c.role_id} className="flex items-center gap-2">
                                                        <Badge variant={riskVariant(c.risk_level)}>{c.risk_level}</Badge>
                                                        <span>{c.role_name}</span>
                                                        {c.description && <span className="text-xs opacity-80">— {c.description}</span>}
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

                                <Separator />

                                {/* Veredicto */}
                                {evaluation.already_assigned ? (
                                    <Alert variant="info">
                                        <AlertTitle>El usuario ya posee este rol</AlertTitle>
                                    </Alert>
                                ) : evaluation.viable ? (
                                    <Alert variant="success">
                                        <CheckCircle2 className="size-4" />
                                        <AlertTitle>Viable</AlertTitle>
                                        <AlertDescription>Se puede proceder con la solicitud.</AlertDescription>
                                    </Alert>
                                ) : (
                                    <Alert variant="warning">
                                        <AlertTriangle className="size-4" />
                                        <AlertTitle>Requiere revisión</AlertTitle>
                                        <AlertDescription>
                                            La asignación no es directa; necesitará autorización (o está bloqueada por cargo).
                                        </AlertDescription>
                                    </Alert>
                                )}

                                {/* Autorizadores que aplicarían (matriz de autorizaciones) */}
                                {authorization && (authorization.matched || authorization.supervisor_missing) && (
                                    <div className="space-y-2 rounded-md border p-3">
                                        <p className="flex items-center gap-2 text-sm font-medium">
                                            <UserCheck className="size-4" /> Autorizadores requeridos
                                        </p>
                                        {authorization.supervisor_missing && (
                                            <Alert variant="warning">
                                                <UserX className="size-4" />
                                                <AlertTitle>El usuario no tiene jefe/supervisor asignado</AlertTitle>
                                                <AlertDescription>
                                                    Una regla exige la autorización de "su supervisor". Asigna un jefe al
                                                    usuario gestionado antes de crear la solicitud.
                                                </AlertDescription>
                                            </Alert>
                                        )}
                                        {authorization.matched ? (
                                            <ul className="space-y-2">
                                                {authorization.steps.map((s, i) => (
                                                    <li key={i} className="rounded border bg-muted/30 p-2 text-sm">
                                                        <div className="flex items-center gap-2">
                                                            <Badge variant={s.authorizer_type === 'supervisor' ? 'info' : 'secondary'}>
                                                                {s.authorizer_type === 'supervisor' ? 'Su supervisor' : 'Persona fija'}
                                                            </Badge>
                                                            <span className={s.unresolved ? 'text-amber-600' : 'font-medium'}>
                                                                {s.authorizer}
                                                            </span>
                                                        </div>
                                                        <ul className="mt-1 list-disc pl-5 text-xs text-muted-foreground">
                                                            {s.reasons.map((r, j) => (
                                                                <li key={j}>{r}</li>
                                                            ))}
                                                        </ul>
                                                    </li>
                                                ))}
                                            </ul>
                                        ) : (
                                            <p className="text-xs text-muted-foreground">
                                                Sin reglas configuradas; se usaría el flujo por defecto (owner de aplicación y/o seguridad).
                                            </p>
                                        )}
                                        <p className="text-xs text-muted-foreground">
                                            Un mismo autorizador que cubra varios motivos aprueba una sola vez.
                                        </p>
                                    </div>
                                )}

                                {can('solicitudes.crear') && !evaluation.already_assigned && (
                                    <Button onClick={createRequest} disabled={submitting} className="w-full">
                                        {submitting ? 'Creando…' : 'Crear solicitud'}
                                    </Button>
                                )}

                                {flash?.success && (
                                    <Alert variant="success">
                                        <CheckCircle2 className="size-4" />
                                        <AlertTitle>{flash.success}</AlertTitle>
                                        <AlertDescription>
                                            <Link href={route('solicitudes.index')} className="font-medium underline">
                                                Ver solicitudes
                                            </Link>
                                        </AlertDescription>
                                    </Alert>
                                )}

                                {errorMessages.length > 0 && (
                                    <Alert variant="destructive">
                                        <Ban className="size-4" />
                                        <AlertTitle>No se pudo crear la solicitud</AlertTitle>
                                        <AlertDescription>
                                            <ul className="list-disc pl-4">
                                                {errorMessages.map((m, i) => (
                                                    <li key={i}>{m}</li>
                                                ))}
                                            </ul>
                                        </AlertDescription>
                                    </Alert>
                                )}
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
