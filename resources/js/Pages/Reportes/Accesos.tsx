import { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Download, Filter, RotateCcw, Table2 } from 'lucide-react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { PageProps } from '@/types';

interface Option {
    id: number;
    name: string;
}
interface RoleOption extends Option {
    application_id: number;
}
interface Row {
    user_id: number;
    external_id: string;
    igg: string;
    user_name: string;
    area: string | null;
    cargo: string | null;
    app_name: string;
    criticality: string;
    role_name: string;
    option_name: string;
    option_key: string;
    source: string;
    assigned_at: string;
    request_number: string;
    requested_at: string;
    approved_at: string;
    request_status: string;
}
interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}
type Nivel = 'app' | 'rol' | 'opc';
interface Filters {
    level: Nivel;
    search: string;
    application_id: number | null;
    role_id: number | null;
    area_id: number | null;
    job_position_id: number | null;
    source: string | null;
    date_from: string | null;
    date_to: string | null;
}

type Props = PageProps<{
    filas: Paginated<Row>;
    filters: Filters;
    nivel: Nivel;
    resumen: { usuarios: number; aplicaciones: number; accesos: number };
    opciones: { aplicaciones: Option[]; roles: RoleOption[]; areas: Option[]; cargos: Option[] };
    can: { export: boolean };
}>;

const ALL = 'all';
const critVariant = (c: string) => (c === 'alta' ? 'destructive' : c === 'media' ? 'warning' : 'success');
const nivelLabel: Record<Nivel, string> = {
    app: 'Aplicación (app)',
    rol: 'Rol (app / rol)',
    opc: 'Opción (app / rol / opción)',
};

export default function ReporteAccesos({ filas, filters, nivel, resumen, opciones, can }: Props) {
    const [f, setF] = useState<Filters>(filters);

    const set = <K extends keyof Filters>(k: K, v: Filters[K]) => setF((p) => ({ ...p, [k]: v }));

    // Roles disponibles según la aplicación elegida (para buscar "por app/rol").
    const roles = useMemo(
        () => (f.application_id ? opciones.roles.filter((r) => r.application_id === f.application_id) : opciones.roles),
        [f.application_id, opciones.roles],
    );

    const query = (over: Partial<Filters> = {}) => {
        const merged = { ...f, ...over };
        const params: Record<string, string> = {};
        Object.entries(merged).forEach(([k, v]) => {
            if (v !== null && v !== '' && v !== undefined) params[k] = String(v);
        });
        return params;
    };

    const apply = (over: Partial<Filters> = {}) => {
        setF((p) => ({ ...p, ...over }));
        router.get(route('reportes.accesos'), query(over), { preserveState: true, preserveScroll: true });
    };

    const reset = () => {
        const cleared: Filters = {
            level: f.level, search: '', application_id: null, role_id: null, area_id: null,
            job_position_id: null, source: null, date_from: null, date_to: null,
        };
        setF(cleared);
        router.get(route('reportes.accesos'), { level: f.level }, { preserveState: true, preserveScroll: true });
    };

    const exportUrl = route('reportes.accesos.export') + '?' + new URLSearchParams(query()).toString();

    const showRole = nivel !== 'app';
    const showOption = nivel === 'opc';
    const showRequest = nivel !== 'app';
    const colCount = 7 + (showRole ? 1 : 0) + (showOption ? 1 : 0) + (showRequest ? 4 : 0);

    return (
        <AppLayout title="Reporte de accesos">
            <Head title="Reporte de accesos" />

            <Card className="w-full">
                <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            <Table2 className="size-5" /> Reporte de accesos
                        </CardTitle>
                        <CardDescription>
                            Todos los usuarios gestionados con sus aplicaciones, roles y opciones (la "foto"), con
                            número de solicitud y fechas de solicitud/aprobación.
                        </CardDescription>
                    </div>
                    {can.export && (
                        <Button variant="outline" asChild>
                            <a href={exportUrl}>
                                <Download className="size-4" /> Exportar CSV
                            </a>
                        </Button>
                    )}
                </CardHeader>

                <CardContent className="space-y-4">
                    {/* Barra de filtros */}
                    <div className="grid grid-cols-1 gap-3 rounded-lg border bg-muted/20 p-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="space-y-1.5">
                            <Label className="text-xs">Nivel de detalle</Label>
                            <Select value={f.level} onValueChange={(v) => apply({ level: v as Nivel })}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="app">{nivelLabel.app}</SelectItem>
                                    <SelectItem value="rol">{nivelLabel.rol}</SelectItem>
                                    <SelectItem value="opc">{nivelLabel.opc}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label className="text-xs">Buscar usuario (nombre / IGG / ID)</Label>
                            <Input
                                value={f.search}
                                onChange={(e) => set('search', e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && apply()}
                                placeholder="Escribe y Enter…"
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label className="text-xs">Aplicación</Label>
                            <Select
                                value={f.application_id ? String(f.application_id) : ALL}
                                onValueChange={(v) => set('application_id', v === ALL ? null : Number(v))}
                            >
                                <SelectTrigger><SelectValue placeholder="Todas" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>Todas</SelectItem>
                                    {opciones.aplicaciones.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)}>{a.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label className="text-xs">Rol</Label>
                            <Select
                                value={f.role_id ? String(f.role_id) : ALL}
                                onValueChange={(v) => set('role_id', v === ALL ? null : Number(v))}
                            >
                                <SelectTrigger><SelectValue placeholder="Todos" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>Todos</SelectItem>
                                    {roles.map((r) => (
                                        <SelectItem key={r.id} value={String(r.id)}>{r.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label className="text-xs">Área</Label>
                            <Select
                                value={f.area_id ? String(f.area_id) : ALL}
                                onValueChange={(v) => set('area_id', v === ALL ? null : Number(v))}
                            >
                                <SelectTrigger><SelectValue placeholder="Todas" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>Todas</SelectItem>
                                    {opciones.areas.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)}>{a.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label className="text-xs">Cargo</Label>
                            <Select
                                value={f.job_position_id ? String(f.job_position_id) : ALL}
                                onValueChange={(v) => set('job_position_id', v === ALL ? null : Number(v))}
                            >
                                <SelectTrigger><SelectValue placeholder="Todos" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>Todos</SelectItem>
                                    {opciones.cargos.map((c) => (
                                        <SelectItem key={c.id} value={String(c.id)}>{c.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label className="text-xs">Origen del acceso</Label>
                            <Select
                                value={f.source ?? ALL}
                                onValueChange={(v) => set('source', v === ALL ? null : v)}
                            >
                                <SelectTrigger><SelectValue placeholder="Todos" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>Todos</SelectItem>
                                    <SelectItem value="request">Solicitud</SelectItem>
                                    <SelectItem value="import">Importación</SelectItem>
                                    <SelectItem value="manual">Manual</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <div className="space-y-1.5">
                                <Label className="text-xs">Asignado desde</Label>
                                <Input type="date" value={f.date_from ?? ''} onChange={(e) => set('date_from', e.target.value || null)} />
                            </div>
                            <div className="space-y-1.5">
                                <Label className="text-xs">Asignado hasta</Label>
                                <Input type="date" value={f.date_to ?? ''} onChange={(e) => set('date_to', e.target.value || null)} />
                            </div>
                        </div>

                        <div className="flex items-end gap-2 sm:col-span-2 lg:col-span-4">
                            <Button onClick={() => apply()}>
                                <Filter className="size-4" /> Aplicar filtros
                            </Button>
                            <Button variant="outline" onClick={reset}>
                                <RotateCcw className="size-4" /> Limpiar
                            </Button>
                            <div className="ml-auto flex flex-wrap gap-2 text-sm">
                                <Badge variant="secondary">Usuarios: {resumen.usuarios}</Badge>
                                <Badge variant="secondary">Aplicaciones: {resumen.aplicaciones}</Badge>
                                <Badge variant="secondary">Accesos: {resumen.accesos}</Badge>
                            </div>
                        </div>
                    </div>

                    {/* Tabla */}
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>ID</TableHead>
                                    <TableHead>IGG</TableHead>
                                    <TableHead>Usuario</TableHead>
                                    <TableHead>Área</TableHead>
                                    <TableHead>Cargo</TableHead>
                                    <TableHead>Aplicación</TableHead>
                                    <TableHead>Criticidad</TableHead>
                                    {showRole && <TableHead>Rol</TableHead>}
                                    {showOption && <TableHead>Opción</TableHead>}
                                    {showRequest && <TableHead>Origen</TableHead>}
                                    {showRequest && <TableHead>Asignado</TableHead>}
                                    {showRequest && <TableHead>N° Solicitud</TableHead>}
                                    {showRequest && <TableHead>Solicitado / Aprobado</TableHead>}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filas.data.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={colCount} className="py-8 text-center text-muted-foreground">
                                            No hay accesos que coincidan con los filtros.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {filas.data.map((r, i) => (
                                    <TableRow key={i}>
                                        <TableCell className="font-mono text-xs">{r.external_id}</TableCell>
                                        <TableCell className="font-mono text-xs">{r.igg}</TableCell>
                                        <TableCell className="font-medium">{r.user_name}</TableCell>
                                        <TableCell className="text-muted-foreground">{r.area ?? '—'}</TableCell>
                                        <TableCell className="text-muted-foreground">{r.cargo ?? '—'}</TableCell>
                                        <TableCell>{r.app_name}</TableCell>
                                        <TableCell>
                                            <Badge variant={critVariant(r.criticality) as never}>{r.criticality}</Badge>
                                        </TableCell>
                                        {showRole && <TableCell>{r.role_name || '—'}</TableCell>}
                                        {showOption && (
                                            <TableCell>
                                                {r.option_name}
                                                {r.option_key && <span className="ml-1 font-mono text-[10px] text-muted-foreground">{r.option_key}</span>}
                                            </TableCell>
                                        )}
                                        {showRequest && (
                                            <TableCell>
                                                <Badge variant={r.source === 'request' ? 'info' : 'secondary'}>{r.source || '—'}</Badge>
                                            </TableCell>
                                        )}
                                        {showRequest && <TableCell className="text-xs text-muted-foreground">{r.assigned_at || '—'}</TableCell>}
                                        {showRequest && <TableCell className="font-medium">{r.request_number || '—'}</TableCell>}
                                        {showRequest && (
                                            <TableCell className="text-xs text-muted-foreground">
                                                {r.requested_at || r.approved_at ? (
                                                    <div>
                                                        <div>Sol: {r.requested_at || '—'}</div>
                                                        <div>Apr: {r.approved_at || '—'}</div>
                                                    </div>
                                                ) : (
                                                    '—'
                                                )}
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>

                    {/* Paginación */}
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>{filas.total} fila(s)</span>
                        <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" disabled={!filas.prev_page_url} onClick={() => filas.prev_page_url && router.get(filas.prev_page_url, {}, { preserveState: true, preserveScroll: true })}>
                                Anterior
                            </Button>
                            <span>Página {filas.current_page} de {filas.last_page}</span>
                            <Button variant="outline" size="sm" disabled={!filas.next_page_url} onClick={() => filas.next_page_url && router.get(filas.next_page_url, {}, { preserveState: true, preserveScroll: true })}>
                                Siguiente
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
