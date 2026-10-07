import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Download, FileWarning, Filter, RotateCcw } from 'lucide-react';

import AppLayout from '@/Layouts/AppLayout';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { PageProps } from '@/types';

interface Row {
    id: number;
    igg: string | null;
    user: string | null;
    application: string | null;
    role: string | null;
    max_risk: 'alto' | 'medio' | 'bajo' | null;
    conflicts: { role: string | null; risk: string }[];
    justification: string | null;
    requester: string | null;
    approved_at: string | null;
    approvers: { name: string; role: string | null; comments: string | null }[];
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
    risk_level: string | null;
    date_from: string | null;
    date_to: string | null;
}
type Props = PageProps<{
    filas: Paginated<Row>;
    filters: Filters;
    opciones: { aplicaciones: { id: number; name: string }[] };
    can: { export: boolean };
}>;

const ALL = 'all';
const riskVariant = { alto: 'destructive', medio: 'warning', bajo: 'success' } as const;

export default function Excepciones({ filas, filters, opciones, can }: Props) {
    const [f, setF] = useState<Filters>(filters);
    const set = <K extends keyof Filters>(k: K, v: Filters[K]) => setF((p) => ({ ...p, [k]: v }));

    const params = () => {
        const out: Record<string, string> = {};
        Object.entries(f).forEach(([k, v]) => {
            if (v !== null && v !== '' && v !== undefined) out[k] = String(v);
        });
        return out;
    };
    const apply = () => router.get(route('reportes.excepciones'), params(), { preserveState: true, preserveScroll: true });
    const reset = () => {
        setF({ search: '', application_id: null, risk_level: null, date_from: null, date_to: null });
        router.get(route('reportes.excepciones'), {}, { preserveState: true, preserveScroll: true });
    };
    const exportUrl = route('reportes.excepciones.export') + '?' + new URLSearchParams(params()).toString();

    return (
        <AppLayout title="Excepciones">
            <Head title="Excepciones" />

            <Card className="w-full">
                <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            <FileWarning className="size-5" /> Reporte de excepciones
                        </CardTitle>
                        <CardDescription>
                            Accesos otorgados pese a un conflicto SoD: justificación, conflictos aceptados y quién los aprobó.
                        </CardDescription>
                    </div>
                    {can.export && (
                        <Button variant="outline" asChild>
                            <a href={exportUrl}><Download className="size-4" /> Exportar CSV</a>
                        </Button>
                    )}
                </CardHeader>

                <CardContent className="space-y-4">
                    <div className="grid grid-cols-1 gap-3 rounded-lg border bg-muted/20 p-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="space-y-1.5">
                            <Label className="text-xs">Buscar (usuario / IGG / N° solicitud)</Label>
                            <Input value={f.search} onChange={(e) => set('search', e.target.value)} onKeyDown={(e) => e.key === 'Enter' && apply()} placeholder="Escribe y Enter…" />
                        </div>
                        <div className="space-y-1.5">
                            <Label className="text-xs">Aplicación</Label>
                            <Select value={f.application_id ? String(f.application_id) : ALL} onValueChange={(v) => set('application_id', v === ALL ? null : Number(v))}>
                                <SelectTrigger><SelectValue placeholder="Todas" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>Todas</SelectItem>
                                    {opciones.aplicaciones.map((a) => <SelectItem key={a.id} value={String(a.id)}>{a.name}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label className="text-xs">Riesgo del conflicto</Label>
                            <Select value={f.risk_level ?? ALL} onValueChange={(v) => set('risk_level', v === ALL ? null : v)}>
                                <SelectTrigger><SelectValue placeholder="Todos" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>Todos</SelectItem>
                                    <SelectItem value="alto">Alto</SelectItem>
                                    <SelectItem value="medio">Medio</SelectItem>
                                    <SelectItem value="bajo">Bajo</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <div className="space-y-1.5"><Label className="text-xs">Aprobada desde</Label><Input type="date" value={f.date_from ?? ''} onChange={(e) => set('date_from', e.target.value || null)} /></div>
                            <div className="space-y-1.5"><Label className="text-xs">Aprobada hasta</Label><Input type="date" value={f.date_to ?? ''} onChange={(e) => set('date_to', e.target.value || null)} /></div>
                        </div>
                        <div className="flex items-end gap-2 sm:col-span-2 lg:col-span-4">
                            <Button onClick={apply}><Filter className="size-4" /> Aplicar filtros</Button>
                            <Button variant="outline" onClick={reset}><RotateCcw className="size-4" /> Limpiar</Button>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Solicitud</TableHead>
                                    <TableHead>Usuario</TableHead>
                                    <TableHead>Acceso otorgado</TableHead>
                                    <TableHead>Conflictos aceptados</TableHead>
                                    <TableHead>Justificación</TableHead>
                                    <TableHead>Aprobación</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filas.data.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={6} className="py-8 text-center text-muted-foreground">No hay excepciones con estos filtros.</TableCell>
                                    </TableRow>
                                )}
                                {filas.data.map((r) => (
                                    <TableRow key={r.id}>
                                        <TableCell>
                                            <Link className="font-medium underline" href={route('solicitudes.show', r.id)}>#{r.id}</Link>
                                            <div className="text-xs text-muted-foreground">por {r.requester ?? '—'}</div>
                                        </TableCell>
                                        <TableCell>{r.user}<div className="font-mono text-xs text-muted-foreground">{r.igg}</div></TableCell>
                                        <TableCell>{r.role}<div className="text-xs text-muted-foreground">{r.application}</div></TableCell>
                                        <TableCell>
                                            <div className="space-y-1">
                                                {r.conflicts.map((c, i) => (
                                                    <div key={i} className="flex items-center gap-2 text-sm">
                                                        <Badge variant={riskVariant[c.risk as keyof typeof riskVariant] ?? 'secondary'}>{c.risk}</Badge> {c.role}
                                                    </div>
                                                ))}
                                            </div>
                                        </TableCell>
                                        <TableCell className="max-w-64 text-sm">{r.justification || <span className="text-muted-foreground">Sin justificación</span>}</TableCell>
                                        <TableCell className="text-xs">
                                            <div className="text-muted-foreground">{r.approved_at}</div>
                                            {r.approvers.map((a, i) => (
                                                <div key={i}><span className="font-medium">{a.name}</span>{a.comments && <span className="text-muted-foreground"> — {a.comments}</span>}</div>
                                            ))}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>

                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>{filas.total} excepción(es)</span>
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
