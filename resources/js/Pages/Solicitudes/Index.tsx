import { useMemo, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Eye, Filter, RotateCcw } from 'lucide-react';

import AppLayout from '@/Layouts/AppLayout';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Combobox } from '@/Components/app/Combobox';
import { DatePicker } from '@/Components/app/DatePicker';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { PageProps } from '@/types';

interface Solicitud {
    id: number;
    user: string;
    igg: string;
    role: string;
    application: string;
    type: string;
    status: string;
    status_label: string;
    status_variant: string;
    has_sod_conflict: boolean;
    requester: string;
    created_at: string;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

interface Option {
    id: number;
    name: string;
}
interface RoleOption extends Option {
    application_id: number;
}
interface Filters {
    search: string;
    id: number | null;
    application_id: number | null;
    role_id: number | null;
    status: string | null;
    sod: string | null;
    date_from: string | null;
    date_to: string | null;
}

type Props = PageProps<{
    solicitudes: Paginated<Solicitud>;
    filters: Filters;
    opciones: {
        aplicaciones: Option[];
        roles: RoleOption[];
        estados: { value: string; label: string }[];
    };
}>;

const ALL = 'all';

export default function SolicitudesIndex({ solicitudes, filters, opciones }: Props) {
    const [f, setF] = useState<Filters>(filters);
    const set = <K extends keyof Filters>(k: K, v: Filters[K]) => setF((p) => ({ ...p, [k]: v }));

    const roles = useMemo(
        () => (f.application_id ? opciones.roles.filter((r) => r.application_id === f.application_id) : opciones.roles),
        [f.application_id, opciones.roles],
    );

    const apply = (over: Partial<Filters> = {}) => {
        const merged = { ...f, ...over };
        setF(merged);
        const params: Record<string, string> = {};
        Object.entries(merged).forEach(([k, v]) => {
            if (v !== null && v !== '' && v !== undefined) params[k] = String(v);
        });
        router.get(route('solicitudes.index'), params, { preserveState: true, preserveScroll: true });
    };

    const reset = () => {
        const cleared: Filters = {
            search: '', id: null, application_id: null, role_id: null,
            status: null, sod: null, date_from: null, date_to: null,
        };
        setF(cleared);
        router.get(route('solicitudes.index'), {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout title="Solicitudes">
            <Head title="Solicitudes" />

            <Card className="w-full">
                <CardHeader>
                    <CardTitle>Solicitudes de acceso</CardTitle>
                    <CardDescription>Historial y estado de las solicitudes de asignación de roles.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    {/* Filtros */}
                    <div className="grid grid-cols-1 gap-3 rounded-lg border bg-muted/20 p-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="space-y-1.5">
                            <Label className="text-xs">Usuario (nombre / IGG / ID)</Label>
                            <Input
                                value={f.search}
                                onChange={(e) => set('search', e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && apply()}
                                placeholder="Escribe y Enter…"
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label className="text-xs">N° de solicitud</Label>
                            <Input
                                value={f.id ?? ''}
                                onChange={(e) => set('id', e.target.value ? Number(e.target.value.replace(/\D/g, '')) : null)}
                                onKeyDown={(e) => e.key === 'Enter' && apply()}
                                placeholder="Ej. 12"
                                inputMode="numeric"
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
                            <Label htmlFor="filtro-role_id" className="text-xs">Rol</Label>
                            <Combobox
                                id="filtro-role_id"
                                value={f.role_id ? String(f.role_id) : ''}
                                onChange={(v) => set('role_id', v === '' ? null : Number(v))}
                                placeholder="Todos"
                                clearLabel="Todos"
                                searchPlaceholder="Buscar rol…"
                                options={roles.map((r) => ({ value: String(r.id), label: r.name, description: opciones.aplicaciones.find((a) => a.id === r.application_id)?.name }))}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label className="text-xs">Estado</Label>
                            <Select
                                value={f.status ?? ALL}
                                onValueChange={(v) => set('status', v === ALL ? null : v)}
                            >
                                <SelectTrigger><SelectValue placeholder="Todos" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>Todos</SelectItem>
                                    {opciones.estados.map((e) => (
                                        <SelectItem key={e.value} value={e.value}>{e.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label className="text-xs">Conflicto SoD</Label>
                            <Select
                                value={f.sod ?? ALL}
                                onValueChange={(v) => set('sod', v === ALL ? null : v)}
                            >
                                <SelectTrigger><SelectValue placeholder="Todos" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>Todos</SelectItem>
                                    <SelectItem value="1">Con conflicto</SelectItem>
                                    <SelectItem value="0">Sin conflicto</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="filtro-date_from" className="text-xs">Desde (fecha)</Label>
                            <DatePicker id="filtro-date_from" value={f.date_from} max={f.date_to ?? undefined} placeholder="Desde" onChange={(v) => set('date_from', v || null)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="filtro-date_to" className="text-xs">Hasta (fecha)</Label>
                            <DatePicker id="filtro-date_to" value={f.date_to} min={f.date_from ?? undefined} placeholder="Hasta" onChange={(v) => set('date_to', v || null)} />
                        </div>

                        <div className="flex items-end gap-2 sm:col-span-2 lg:col-span-4">
                            <Button onClick={() => apply()}>
                                <Filter className="size-4" /> Aplicar filtros
                            </Button>
                            <Button variant="outline" onClick={reset}>
                                <RotateCcw className="size-4" /> Limpiar
                            </Button>
                            <span className="ml-auto self-center text-sm text-muted-foreground">{solicitudes.total} resultado(s)</span>
                        </div>
                    </div>

                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>#</TableHead>
                                <TableHead>Usuario</TableHead>
                                <TableHead>Rol</TableHead>
                                <TableHead>Aplicación</TableHead>
                                <TableHead>SoD</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead>Solicitante</TableHead>
                                <TableHead>Fecha</TableHead>
                                <TableHead className="w-16 text-right">Detalle</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {solicitudes.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={9} className="py-8 text-center text-muted-foreground">
                                        Aún no hay solicitudes.
                                    </TableCell>
                                </TableRow>
                            )}
                            {solicitudes.data.map((s) => (
                                <TableRow key={s.id}>
                                    <TableCell>
                                        <Link href={route('solicitudes.show', s.id)} className="font-medium text-primary underline-offset-2 hover:underline">
                                            #{s.id}
                                        </Link>
                                    </TableCell>
                                    <TableCell>
                                        <div className="font-medium">{s.user}</div>
                                        <div className="font-mono text-xs text-muted-foreground">{s.igg}</div>
                                    </TableCell>
                                    <TableCell>{s.role}</TableCell>
                                    <TableCell className="text-muted-foreground">{s.application}</TableCell>
                                    <TableCell>
                                        {s.has_sod_conflict ? (
                                            <Badge variant="destructive">Conflicto</Badge>
                                        ) : (
                                            <span className="text-muted-foreground">—</span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={s.status_variant as never}>{s.status_label}</Badge>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">{s.requester}</TableCell>
                                    <TableCell className="text-xs text-muted-foreground">{s.created_at}</TableCell>
                                    <TableCell className="text-right">
                                        <Button variant="ghost" size="icon" asChild aria-label="Ver detalle y bitácora">
                                            <Link href={route('solicitudes.show', s.id)}>
                                                <Eye className="size-4" />
                                            </Link>
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>

                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>{solicitudes.total} registro(s)</span>
                        <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" disabled={!solicitudes.prev_page_url} onClick={() => solicitudes.prev_page_url && router.get(solicitudes.prev_page_url, {}, { preserveState: true })}>
                                Anterior
                            </Button>
                            <span>Página {solicitudes.current_page} de {solicitudes.last_page}</span>
                            <Button variant="outline" size="sm" disabled={!solicitudes.next_page_url} onClick={() => solicitudes.next_page_url && router.get(solicitudes.next_page_url, {}, { preserveState: true })}>
                                Siguiente
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
