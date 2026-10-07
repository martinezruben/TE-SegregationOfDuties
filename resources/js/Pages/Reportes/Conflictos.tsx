import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Download, Filter, RotateCcw, ShieldAlert } from 'lucide-react';

import AppLayout from '@/Layouts/AppLayout';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { PageProps } from '@/types';

interface Option {
    id: number;
    name: string;
}
interface Row {
    user_id: number;
    external_id: string;
    igg: string;
    user_name: string;
    active: boolean;
    area: string | null;
    cargo: string | null;
    risk_level: 'alto' | 'medio' | 'bajo';
    description: string | null;
    role_a: string;
    app_a: string;
    source_a: string;
    assigned_a: string;
    role_b: string;
    app_b: string;
    source_b: string;
    assigned_b: string;
    exception_request_id: number | null;
}
interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}
interface Filters {
    search: string;
    application_id: number | null;
    area_id: number | null;
    job_position_id: number | null;
    risk_level: string | null;
    exception: string | null;
}
type Props = PageProps<{
    filas: Paginated<Row>;
    filters: Filters;
    resumen: { total: number; usuarios: number; alto: number; medio: number; bajo: number; con_excepcion: number; sin_excepcion: number };
    opciones: { aplicaciones: Option[]; areas: Option[]; cargos: Option[] };
    can: { export: boolean };
}>;

const ALL = 'all';
const riskVariant = { alto: 'destructive', medio: 'warning', bajo: 'success' } as const;

export default function Conflictos({ filas, filters, resumen, opciones, can }: Props) {
    const [f, setF] = useState<Filters>(filters);
    const set = <K extends keyof Filters>(k: K, v: Filters[K]) => setF((p) => ({ ...p, [k]: v }));

    const params = (over: Partial<Filters> = {}) => {
        const out: Record<string, string> = {};
        Object.entries({ ...f, ...over }).forEach(([k, v]) => {
            if (v !== null && v !== '' && v !== undefined) out[k] = String(v);
        });
        return out;
    };
    const apply = (over: Partial<Filters> = {}) => {
        setF((p) => ({ ...p, ...over }));
        router.get(route('reportes.conflictos'), params(over), { preserveState: true, preserveScroll: true });
    };
    const reset = () => {
        setF({ search: '', application_id: null, area_id: null, job_position_id: null, risk_level: null, exception: null });
        router.get(route('reportes.conflictos'), {}, { preserveState: true, preserveScroll: true });
    };
    const exportUrl = route('reportes.conflictos.export') + '?' + new URLSearchParams(params()).toString();

    const pick = (label: string, value: string | null, items: { value: string; label: string }[], on: (v: string | null) => void) => (
        <div className="space-y-1.5">
            <Label className="text-xs">{label}</Label>
            <Select value={value ?? ALL} onValueChange={(v) => on(v === ALL ? null : v)}>
                <SelectTrigger><SelectValue placeholder="Todos" /></SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>Todos</SelectItem>
                    {items.map((i) => <SelectItem key={i.value} value={i.value}>{i.label}</SelectItem>)}
                </SelectContent>
            </Select>
        </div>
    );

    return (
        <AppLayout title="Conflictos activos">
            <Head title="Conflictos activos" />

            <Card className="w-full">
                <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            <ShieldAlert className="size-5" /> Conflictos activos
                        </CardTitle>
                        <CardDescription>
                            Usuarios que hoy poseen combinaciones de roles en conflicto según la matriz SoD (accesos vigentes,
                            sin importar si vinieron de solicitud, importación o carga manual).
                        </CardDescription>
                    </div>
                    {can.export && (
                        <Button variant="outline" asChild>
                            <a href={exportUrl}><Download className="size-4" /> Exportar CSV</a>
                        </Button>
                    )}
                </CardHeader>

                <CardContent className="space-y-4">
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-5">
                        <Card><CardHeader className="p-4 pb-1"><CardDescription>Conflictos</CardDescription><CardTitle className="text-2xl">{resumen.total}</CardTitle></CardHeader><CardContent className="px-4 pb-4 text-xs text-muted-foreground">{resumen.usuarios} usuario(s)</CardContent></Card>
                        <Card><CardHeader className="p-4 pb-1"><CardDescription>Riesgo alto</CardDescription><CardTitle className="text-2xl text-destructive">{resumen.alto}</CardTitle></CardHeader></Card>
                        <Card><CardHeader className="p-4 pb-1"><CardDescription>Riesgo medio</CardDescription><CardTitle className="text-2xl">{resumen.medio}</CardTitle></CardHeader></Card>
                        <Card><CardHeader className="p-4 pb-1"><CardDescription>Riesgo bajo</CardDescription><CardTitle className="text-2xl">{resumen.bajo}</CardTitle></CardHeader></Card>
                        <Card><CardHeader className="p-4 pb-1"><CardDescription>Sin excepción formal</CardDescription><CardTitle className="text-2xl text-warning">{resumen.sin_excepcion}</CardTitle></CardHeader><CardContent className="px-4 pb-4 text-xs text-muted-foreground">{resumen.con_excepcion} con excepción aprobada</CardContent></Card>
                    </div>

                    <div className="grid grid-cols-1 gap-3 rounded-lg border bg-muted/20 p-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="space-y-1.5">
                            <Label className="text-xs">Buscar usuario (nombre / IGG / ID)</Label>
                            <Input value={f.search} onChange={(e) => set('search', e.target.value)} onKeyDown={(e) => e.key === 'Enter' && apply()} placeholder="Escribe y Enter…" />
                        </div>
                        {pick('Aplicación (cualquiera de los dos roles)', f.application_id ? String(f.application_id) : null, opciones.aplicaciones.map((a) => ({ value: String(a.id), label: a.name })), (v) => set('application_id', v ? Number(v) : null))}
                        {pick('Área', f.area_id ? String(f.area_id) : null, opciones.areas.map((a) => ({ value: String(a.id), label: a.name })), (v) => set('area_id', v ? Number(v) : null))}
                        {pick('Cargo', f.job_position_id ? String(f.job_position_id) : null, opciones.cargos.map((a) => ({ value: String(a.id), label: a.name })), (v) => set('job_position_id', v ? Number(v) : null))}
                        {pick('Riesgo', f.risk_level, [{ value: 'alto', label: 'Alto' }, { value: 'medio', label: 'Medio' }, { value: 'bajo', label: 'Bajo' }], (v) => set('risk_level', v))}
                        {pick('Excepción formal', f.exception, [{ value: 'sin', label: 'Sin excepción' }, { value: 'con', label: 'Con excepción aprobada' }], (v) => set('exception', v))}
                        <div className="flex items-end gap-2 sm:col-span-2">
                            <Button onClick={() => apply()}><Filter className="size-4" /> Aplicar filtros</Button>
                            <Button variant="outline" onClick={reset}><RotateCcw className="size-4" /> Limpiar</Button>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Usuario</TableHead>
                                    <TableHead>Área / Cargo</TableHead>
                                    <TableHead>Riesgo</TableHead>
                                    <TableHead>Rol A</TableHead>
                                    <TableHead>Rol B</TableHead>
                                    <TableHead>Excepción</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filas.data.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={6} className="py-8 text-center text-muted-foreground">
                                            No hay conflictos activos con estos filtros. 🎉
                                        </TableCell>
                                    </TableRow>
                                )}
                                {filas.data.map((r, i) => (
                                    <TableRow key={`${r.user_id}-${i}`}>
                                        <TableCell>
                                            <div className="font-medium">{r.user_name}</div>
                                            <div className="font-mono text-xs text-muted-foreground">{r.igg}{!r.active && ' · inactivo'}</div>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">{r.area ?? '—'}<div className="text-xs">{r.cargo ?? '—'}</div></TableCell>
                                        <TableCell>
                                            <Badge variant={riskVariant[r.risk_level]}>{r.risk_level}</Badge>
                                            {r.description && <div className="mt-1 max-w-48 text-xs text-muted-foreground">{r.description}</div>}
                                        </TableCell>
                                        <TableCell>{r.role_a}<div className="text-xs text-muted-foreground">{r.app_a} · {r.source_a} · {r.assigned_a}</div></TableCell>
                                        <TableCell>{r.role_b}<div className="text-xs text-muted-foreground">{r.app_b} · {r.source_b} · {r.assigned_b}</div></TableCell>
                                        <TableCell>
                                            {r.exception_request_id ? (
                                                <Link className="underline" href={route('solicitudes.show', r.exception_request_id)}>
                                                    <Badge variant="info">Solicitud #{r.exception_request_id}</Badge>
                                                </Link>
                                            ) : (
                                                <Badge variant="warning">Sin excepción formal</Badge>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>

                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>{filas.total} conflicto(s)</span>
                        <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" disabled={!filas.prev_page_url} onClick={() => filas.prev_page_url && router.get(filas.prev_page_url, {}, { preserveState: true })}>Anterior</Button>
                            <span>Página {filas.current_page} de {filas.last_page}</span>
                            <Button variant="outline" size="sm" disabled={!filas.next_page_url} onClick={() => filas.next_page_url && router.get(filas.next_page_url, {}, { preserveState: true })}>Siguiente</Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
