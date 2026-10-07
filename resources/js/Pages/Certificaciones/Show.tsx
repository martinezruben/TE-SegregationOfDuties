import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Download, Filter, Lock } from 'lucide-react';

import AppLayout from '@/Layouts/AppLayout';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

interface Item {
    id: number;
    user_name: string;
    user_igg: string;
    area: string | null;
    cargo: string | null;
    app_name: string;
    role_name: string;
    source: string | null;
    assigned_at: string | null;
    expires_at: string | null;
    conflict_risk: 'alto' | 'medio' | 'bajo' | null;
    reviewer: string | null;
    decision: 'pendiente' | 'mantener' | 'revocar';
    decided_by: string | null;
    decided_at: string | null;
    comments: string | null;
    can_decide: boolean;
}
interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}
interface Props {
    campana: { id: number; name: string; status: 'abierta' | 'cerrada'; due_date: string; overdue: boolean; closed_at: string | null; creator: string | null; filters: Record<string, unknown> | null };
    items: Paginated<Item>;
    filters: { decision: string | null; reviewer_id: number | null; search: string };
    resumen: { total: number; pendiente: number; mantener: number; revocar: number };
    revisores: { id: number | null; name: string; total: number; pendientes: number }[];
    can: { review: boolean; close: boolean; export: boolean; seeAll: boolean };
}

const ALL = 'all';
const riskVariant = { alto: 'destructive', medio: 'warning', bajo: 'success' } as const;
const decisionMeta = {
    pendiente: { label: 'Pendiente', variant: 'secondary' },
    mantener: { label: 'Mantener', variant: 'success' },
    revocar: { label: 'Revocar', variant: 'destructive' },
} as const;

export default function Show({ campana, items, filters, resumen, revisores, can }: Props) {
    const [decision, setDecision] = useState<string | null>(filters.decision);
    const [reviewer, setReviewer] = useState<number | null>(filters.reviewer_id);
    const [search, setSearch] = useState(filters.search);
    const [selected, setSelected] = useState<number[]>([]);
    const [comments, setComments] = useState('');
    const [confirm, setConfirm] = useState<'mantener' | 'revocar' | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const decidable = items.data.filter((i) => i.can_decide);
    const allSelected = decidable.length > 0 && decidable.every((i) => selected.includes(i.id));

    const query = () => {
        const q: Record<string, string> = {};
        if (decision) q.decision = decision;
        if (reviewer) q.reviewer_id = String(reviewer);
        if (search) q.search = search;
        return q;
    };
    const apply = () => router.get(route('certificaciones.show', campana.id), query(), { preserveState: true, preserveScroll: true });

    const send = (d: 'mantener' | 'revocar') => {
        if (d === 'revocar' && comments.trim() === '') {
            setError('Indica el motivo de la revocación.');
            setConfirm(null);
            return;
        }
        setError(null);
        setBusy(true);
        router.post(
            route('certificaciones.decide', campana.id),
            { item_ids: selected, decision: d, comments },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelected([]);
                    setComments('');
                },
                onFinish: () => {
                    setBusy(false);
                    setConfirm(null);
                },
            },
        );
    };

    return (
        <AppLayout title={campana.name}>
            <Head title={campana.name} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link href={route('certificaciones.index')} className="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                        <ArrowLeft className="size-4" /> Campañas
                    </Link>
                    <div className="flex items-center gap-2">
                        {can.export && (
                            <Button variant="outline" size="sm" asChild>
                                <a href={route('certificaciones.export', campana.id)}><Download className="size-4" /> Exportar evidencia (CSV)</a>
                            </Button>
                        )}
                        {can.close && (
                            <Button variant="outline" size="sm" onClick={() => router.post(route('certificaciones.close', campana.id), {}, { preserveScroll: true })}>
                                <Lock className="size-4" /> Cerrar campaña
                            </Button>
                        )}
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex flex-wrap items-center gap-2">
                            {campana.name}
                            <Badge variant={campana.status === 'abierta' ? 'info' : 'secondary'}>{campana.status === 'abierta' ? 'Abierta' : 'Cerrada'}</Badge>
                        </CardTitle>
                        <CardDescription>
                            Vence el <span className={campana.overdue ? 'font-medium text-destructive' : ''}>{campana.due_date}{campana.overdue && ' (vencida)'}</span>
                            {campana.creator && <> · creada por {campana.creator}</>}
                            {campana.closed_at && <> · cerrada el {campana.closed_at}</>}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                            {([['Total', resumen.total], ['Pendientes', resumen.pendiente], ['Mantenidos', resumen.mantener], ['Revocados', resumen.revocar]] as const).map(([l, v]) => (
                                <div key={l} className="rounded-lg border p-3">
                                    <div className="text-xs text-muted-foreground">{l}{!can.seeAll && ' (míos)'}</div>
                                    <div className="text-2xl font-semibold">{v}</div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {can.seeAll && revisores.length > 0 && (
                    <Card>
                        <CardHeader><CardTitle className="text-base">Avance por revisor</CardTitle></CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader><TableRow><TableHead>Revisor</TableHead><TableHead>Asignados</TableHead><TableHead>Pendientes</TableHead></TableRow></TableHeader>
                                <TableBody>
                                    {revisores.map((r) => (
                                        <TableRow key={r.id ?? 'none'}>
                                            <TableCell>{r.name}</TableCell>
                                            <TableCell>{r.total}</TableCell>
                                            <TableCell>{r.pendientes > 0 ? <Badge variant="warning">{r.pendientes}</Badge> : <Badge variant="success">0</Badge>}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardContent className="space-y-4 pt-6">
                        <div className="grid grid-cols-1 gap-3 rounded-lg border bg-muted/20 p-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="space-y-1.5">
                                <Label className="text-xs">Buscar (usuario / IGG / rol / app)</Label>
                                <Input value={search} onChange={(e) => setSearch(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && apply()} />
                            </div>
                            <div className="space-y-1.5">
                                <Label className="text-xs">Decisión</Label>
                                <Select value={decision ?? ALL} onValueChange={(v) => setDecision(v === ALL ? null : v)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ALL}>Todas</SelectItem>
                                        <SelectItem value="pendiente">Pendientes</SelectItem>
                                        <SelectItem value="mantener">Mantenidos</SelectItem>
                                        <SelectItem value="revocar">Revocados</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            {can.seeAll && revisores.length > 0 && (
                                <div className="space-y-1.5">
                                    <Label className="text-xs">Revisor</Label>
                                    <Select value={reviewer ? String(reviewer) : ALL} onValueChange={(v) => setReviewer(v === ALL ? null : Number(v))}>
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={ALL}>Todos</SelectItem>
                                            {revisores.filter((r) => r.id).map((r) => <SelectItem key={r.id} value={String(r.id)}>{r.name}</SelectItem>)}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}
                            <div className="flex items-end">
                                <Button onClick={apply}><Filter className="size-4" /> Aplicar</Button>
                            </div>
                        </div>

                        {can.review && campana.status === 'abierta' && decidable.length > 0 && (
                            <div className="space-y-2 rounded-lg border border-primary/30 bg-primary/5 p-3">
                                <div className="text-sm font-medium">{selected.length} seleccionado(s)</div>
                                <Input
                                    value={comments}
                                    onChange={(e) => setComments(e.target.value)}
                                    placeholder="Comentario (obligatorio para revocar)"
                                    aria-label="Comentario de la decisión"
                                />
                                {error && <p className="text-sm text-destructive">{error}</p>}
                                <div className="flex gap-2">
                                    <Button size="sm" variant="success" disabled={selected.length === 0 || busy} onClick={() => setConfirm('mantener')}>Mantener</Button>
                                    <Button size="sm" variant="destructive" disabled={selected.length === 0 || busy} onClick={() => (comments.trim() ? setConfirm('revocar') : setError('Indica el motivo de la revocación.'))}>Revocar</Button>
                                </div>
                            </div>
                        )}

                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        {decidable.length > 0 && (
                                            <TableHead className="w-10">
                                                <Checkbox aria-label="Seleccionar todos" checked={allSelected} onCheckedChange={(v) => setSelected(v === true ? decidable.map((i) => i.id) : [])} />
                                            </TableHead>
                                        )}
                                        <TableHead>Usuario</TableHead>
                                        <TableHead>Acceso</TableHead>
                                        <TableHead>Origen</TableHead>
                                        <TableHead>Riesgo SoD</TableHead>
                                        {can.seeAll && <TableHead>Revisor</TableHead>}
                                        <TableHead>Decisión</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {items.data.length === 0 && (
                                        <TableRow><TableCell colSpan={7} className="py-8 text-center text-muted-foreground">Sin accesos con estos filtros.</TableCell></TableRow>
                                    )}
                                    {items.data.map((i) => (
                                        <TableRow key={i.id}>
                                            {decidable.length > 0 && (
                                                <TableCell>
                                                    {i.can_decide && (
                                                        <Checkbox aria-label={`Seleccionar ${i.user_name} ${i.role_name}`} checked={selected.includes(i.id)} onCheckedChange={(v) => setSelected((s) => (v === true ? [...s, i.id] : s.filter((x) => x !== i.id)))} />
                                                    )}
                                                </TableCell>
                                            )}
                                            <TableCell>
                                                <div className="font-medium">{i.user_name}</div>
                                                <div className="font-mono text-xs text-muted-foreground">{i.user_igg}</div>
                                                <div className="text-xs text-muted-foreground">{[i.area, i.cargo].filter(Boolean).join(' · ')}</div>
                                            </TableCell>
                                            <TableCell>
                                                {i.role_name}
                                                <div className="text-xs text-muted-foreground">{i.app_name}</div>
                                            </TableCell>
                                            <TableCell className="text-xs text-muted-foreground">
                                                {i.source}
                                                <div>{i.assigned_at}{i.expires_at && ` → ${i.expires_at}`}</div>
                                            </TableCell>
                                            <TableCell>{i.conflict_risk ? <Badge variant={riskVariant[i.conflict_risk]}>{i.conflict_risk}</Badge> : <span className="text-muted-foreground">—</span>}</TableCell>
                                            {can.seeAll && <TableCell className="text-sm">{i.reviewer ?? '—'}</TableCell>}
                                            <TableCell>
                                                <Badge variant={decisionMeta[i.decision].variant}>{decisionMeta[i.decision].label}</Badge>
                                                {i.decided_at && (
                                                    <div className="mt-1 text-xs text-muted-foreground">{i.decided_by} · {i.decided_at}</div>
                                                )}
                                                {i.comments && <div className="mt-0.5 max-w-56 text-xs">{i.comments}</div>}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <div className="flex items-center justify-between text-sm text-muted-foreground">
                            <span>{items.total} acceso(s)</span>
                            <div className="flex items-center gap-2">
                                <Button variant="outline" size="sm" disabled={!items.prev_page_url} onClick={() => items.prev_page_url && router.get(items.prev_page_url, {}, { preserveState: true })}>Anterior</Button>
                                <span>Página {items.current_page} de {items.last_page}</span>
                                <Button variant="outline" size="sm" disabled={!items.next_page_url} onClick={() => items.next_page_url && router.get(items.next_page_url, {}, { preserveState: true })}>Siguiente</Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <AlertDialog open={confirm !== null} onOpenChange={(o) => !o && setConfirm(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{confirm === 'revocar' ? '¿Revocar los accesos seleccionados?' : '¿Mantener los accesos seleccionados?'}</AlertDialogTitle>
                        <AlertDialogDescription>
                            {confirm === 'revocar'
                                ? `Se retirarán ${selected.length} acceso(s) de la foto de inmediato. La baja técnica en el sistema destino debe ejecutarse por separado.`
                                : `Certificas que ${selected.length} acceso(s) siguen siendo necesarios. Queda registrado con tu nombre y fecha.`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={busy}>Cancelar</AlertDialogCancel>
                        <AlertDialogAction disabled={busy} onClick={(e) => { e.preventDefault(); confirm && send(confirm); }}>
                            {busy ? 'Guardando…' : 'Confirmar'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
