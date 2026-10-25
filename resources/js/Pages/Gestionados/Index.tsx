import { FormEvent, useEffect, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, RotateCcw, Search, Trash2 } from 'lucide-react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
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
import { Combobox } from '@/Components/app/Combobox';
import { SwitchField } from '@/Components/app/SwitchField';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { PageProps } from '@/types';

interface Option {
    id: number;
    name: string;
}

interface Managed {
    id: number;
    external_id: string;
    igg: string;
    name: string;
    is_active: boolean;
    area_id: number;
    job_position_id: number;
    supervisor_id: number | null;
    area?: Option | null;
    job_position?: Option | null;
    supervisor?: Option | null;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

type Props = PageProps<{
    usuarios: Paginated<Managed>;
    areas: Option[];
    cargos: Option[];
    supervisores: Option[];
    filters: Filters;
    can: { create: boolean; update: boolean; delete: boolean };
}>;

interface Filters {
    search: string;
    area_id: number | null;
    job_position_id: number | null;
    supervisor: string | null;
    status: string | null;
}

const NONE = 'none';
const ALL = 'all';

export default function GestionadosIndex({ usuarios, areas, cargos, supervisores, filters, can }: Props) {
    const [f, setF] = useState<Filters>(filters);
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Managed | null>(null);
    const [deleting, setDeleting] = useState<Managed | null>(null);
    const hasFilters = !!(f.search || f.area_id || f.job_position_id || f.supervisor || f.status);

    const go = (next: Filters) => {
        const params: Record<string, string> = {};
        Object.entries(next).forEach(([k, v]) => {
            if (v !== null && v !== '') params[k] = String(v);
        });
        router.get(route('gestionados.index'), params, { preserveState: true, preserveScroll: true, replace: true });
    };
    const apply = (over: Partial<Filters>) => {
        const next = { ...f, ...over };
        setF(next);
        go(next);
    };
    const reset = () => {
        const empty: Filters = { search: '', area_id: null, job_position_id: null, supervisor: null, status: null };
        setF(empty);
        go(empty);
    };

    // La búsqueda de texto se envía sola tras una pausa al escribir.
    useEffect(() => {
        if (f.search === (filters.search ?? '')) return;
        const t = setTimeout(() => go(f), 300);
        return () => clearTimeout(t);
    }, [f.search]); // eslint-disable-line react-hooks/exhaustive-deps

    const form = useForm<{
        external_id: string;
        igg: string;
        name: string;
        area_id: string;
        job_position_id: string;
        supervisor_id: string;
        is_active: boolean;
    }>({ external_id: '', igg: '', name: '', area_id: '', job_position_id: '', supervisor_id: NONE, is_active: true });

    const toPayload = (data: typeof form.data) => ({
        ...data,
        supervisor_id: data.supervisor_id === NONE ? '' : data.supervisor_id,
    });

    const openCreate = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
        setFormOpen(true);
    };

    const openEdit = (u: Managed) => {
        setEditing(u);
        form.setData({
            external_id: u.external_id,
            igg: u.igg,
            name: u.name,
            area_id: String(u.area_id),
            job_position_id: String(u.job_position_id),
            supervisor_id: u.supervisor_id ? String(u.supervisor_id) : NONE,
            is_active: u.is_active,
        });
        form.clearErrors();
        setFormOpen(true);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => setFormOpen(false) };
        form.transform((data) => toPayload(data as typeof form.data));
        if (editing) {
            form.put(route('gestionados.update', editing.id), opts);
        } else {
            form.post(route('gestionados.store'), opts);
        }
    };

    const confirmDelete = () => {
        if (!deleting) return;
        router.delete(route('gestionados.destroy', deleting.id), {
            preserveScroll: true,
            onFinish: () => setDeleting(null),
        });
    };

    return (
        <AppLayout title="Usuarios gestionados">
            <Head title="Usuarios gestionados" />

            <Card className="w-full">
                <CardHeader className="flex flex-row items-center justify-between gap-4 space-y-0">
                    <div>
                        <CardTitle>Usuarios gestionados</CardTitle>
                        <CardDescription>Población gobernada sobre la que se aplican accesos y validaciones SoD.</CardDescription>
                    </div>
                    {can.create && (
                        <Button onClick={openCreate}>
                            <Plus className="size-4" /> Nuevo usuario
                        </Button>
                    )}
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid grid-cols-1 gap-3 rounded-lg border bg-muted/20 p-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div className="space-y-1.5">
                            <Label htmlFor="gestionados-search" className="text-xs">Buscar (nombre, ID o IGG)</Label>
                            <div className="relative">
                                <Search className="pointer-events-none absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                                <Input id="gestionados-search" className="pl-8" placeholder="Escribe para filtrar…" value={f.search} onChange={(e) => setF((p) => ({ ...p, search: e.target.value }))} />
                            </div>
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="gestionados-area" className="text-xs">Área</Label>
                            <Combobox
                                id="gestionados-area"
                                value={f.area_id ? String(f.area_id) : ''}
                                onChange={(v) => apply({ area_id: v ? Number(v) : null })}
                                placeholder="Todas"
                                clearLabel="Todas"
                                searchPlaceholder="Buscar área…"
                                options={areas.map((a) => ({ value: String(a.id), label: a.name }))}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="gestionados-cargo" className="text-xs">Cargo</Label>
                            <Combobox
                                id="gestionados-cargo"
                                value={f.job_position_id ? String(f.job_position_id) : ''}
                                onChange={(v) => apply({ job_position_id: v ? Number(v) : null })}
                                placeholder="Todos"
                                clearLabel="Todos"
                                searchPlaceholder="Buscar cargo…"
                                options={cargos.map((c) => ({ value: String(c.id), label: c.name }))}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="gestionados-supervisor" className="text-xs">Jefe / Supervisor</Label>
                            <Combobox
                                id="gestionados-supervisor"
                                value={f.supervisor ?? ''}
                                onChange={(v) => apply({ supervisor: v || null })}
                                placeholder="Todos"
                                clearLabel="Todos"
                                searchPlaceholder="Buscar agente…"
                                options={[{ value: 'none', label: 'Sin supervisor asignado' }, ...supervisores.map((a) => ({ value: String(a.id), label: a.name }))]}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label className="text-xs">Estado</Label>
                            <Select value={f.status ?? ALL} onValueChange={(v) => apply({ status: v === ALL ? null : v })}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>Todos</SelectItem>
                                    <SelectItem value="activo">Activo</SelectItem>
                                    <SelectItem value="inactivo">Inactivo</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="flex items-end">
                            <Button variant="outline" onClick={reset} disabled={!hasFilters}><RotateCcw className="size-4" /> Limpiar filtros</Button>
                        </div>
                    </div>

                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>ID</TableHead>
                                <TableHead>IGG</TableHead>
                                <TableHead>Nombre</TableHead>
                                <TableHead>Área</TableHead>
                                <TableHead>Cargo</TableHead>
                                <TableHead>Jefe / Supervisor</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead className="w-24 text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {usuarios.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={8} className="py-8 text-center text-muted-foreground">
                                        No hay usuarios que coincidan.
                                    </TableCell>
                                </TableRow>
                            )}
                            {usuarios.data.map((u) => (
                                <TableRow key={u.id}>
                                    <TableCell className="font-mono text-xs">{u.external_id}</TableCell>
                                    <TableCell className="font-mono text-xs">{u.igg}</TableCell>
                                    <TableCell className="font-medium">{u.name}</TableCell>
                                    <TableCell className="text-muted-foreground">{u.area?.name ?? '—'}</TableCell>
                                    <TableCell className="text-muted-foreground">{u.job_position?.name ?? '—'}</TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {u.supervisor?.name ?? <span className="text-amber-600">Sin asignar</span>}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={u.is_active ? 'success' : 'secondary'}>
                                            {u.is_active ? 'Activo' : 'Inactivo'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-1">
                                            {can.update && (
                                                <Button variant="ghost" size="icon" onClick={() => openEdit(u)} aria-label="Editar">
                                                    <Pencil className="size-4" />
                                                </Button>
                                            )}
                                            {can.delete && (
                                                <Button variant="ghost" size="icon" onClick={() => setDeleting(u)} aria-label="Eliminar">
                                                    <Trash2 className="size-4 text-destructive" />
                                                </Button>
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>

                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>{usuarios.total} registro(s)</span>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!usuarios.prev_page_url}
                                onClick={() => usuarios.prev_page_url && router.get(usuarios.prev_page_url, {}, { preserveState: true })}
                            >
                                Anterior
                            </Button>
                            <span>
                                Página {usuarios.current_page} de {usuarios.last_page}
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!usuarios.next_page_url}
                                onClick={() => usuarios.next_page_url && router.get(usuarios.next_page_url, {}, { preserveState: true })}
                            >
                                Siguiente
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent>
                    <form onSubmit={submit}>
                        <DialogHeader>
                            <DialogTitle>{editing ? 'Editar usuario' : 'Nuevo usuario gestionado'}</DialogTitle>
                            <DialogDescription>Datos de identidad y su asignación organizativa.</DialogDescription>
                        </DialogHeader>

                        <div className="grid grid-cols-1 gap-4 py-4 sm:grid-cols-2">
                            <div className="space-y-1.5">
                                <Label htmlFor="external_id">ID</Label>
                                <Input id="external_id" value={form.data.external_id} onChange={(e) => form.setData('external_id', e.target.value)} autoFocus />
                                {form.errors.external_id && <p className="text-sm text-destructive">{form.errors.external_id}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="igg">IGG</Label>
                                <Input id="igg" value={form.data.igg} onChange={(e) => form.setData('igg', e.target.value)} />
                                {form.errors.igg && <p className="text-sm text-destructive">{form.errors.igg}</p>}
                            </div>
                            <div className="space-y-1.5 sm:col-span-2">
                                <Label htmlFor="name">Nombre</Label>
                                <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                                {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="gest-area">Área</Label>
                                <Combobox
                                    id="gest-area"
                                    value={form.data.area_id}
                                    onChange={(v) => form.setData('area_id', v)}
                                    searchPlaceholder="Buscar área…"
                                    options={areas.map((a) => ({ value: String(a.id), label: a.name }))}
                                />
                                {form.errors.area_id && <p className="text-sm text-destructive">{form.errors.area_id}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="gest-cargo">Cargo</Label>
                                <Combobox
                                    id="gest-cargo"
                                    value={form.data.job_position_id}
                                    onChange={(v) => form.setData('job_position_id', v)}
                                    searchPlaceholder="Buscar cargo…"
                                    options={cargos.map((c) => ({ value: String(c.id), label: c.name }))}
                                />
                                {form.errors.job_position_id && <p className="text-sm text-destructive">{form.errors.job_position_id}</p>}
                            </div>
                            <div className="space-y-1.5 sm:col-span-2">
                                <Label htmlFor="gest-supervisor">Jefe / Supervisor (agente autorizador)</Label>
                                <Combobox
                                    id="gest-supervisor"
                                    value={form.data.supervisor_id === NONE ? '' : form.data.supervisor_id}
                                    onChange={(v) => form.setData('supervisor_id', v === '' ? NONE : v)}
                                    placeholder="Sin supervisor"
                                    clearLabel="Sin supervisor"
                                    searchPlaceholder="Buscar agente…"
                                    options={supervisores.map((u) => ({ value: String(u.id), label: u.name }))}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Se usa cuando una regla de autorización indica "su supervisor".
                                </p>
                                {form.errors.supervisor_id && <p className="text-sm text-destructive">{form.errors.supervisor_id}</p>}
                            </div>
                            <SwitchField id="gest-active" label="Activo" className="sm:col-span-2" checked={form.data.is_active} onCheckedChange={(v) => form.setData('is_active', v)} />
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setFormOpen(false)}>
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {editing ? 'Guardar cambios' : 'Crear'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <AlertDialog open={!!deleting} onOpenChange={(o) => !o && setDeleting(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>¿Eliminar a "{deleting?.name}"?</AlertDialogTitle>
                        <AlertDialogDescription>Esta acción es lógica y reversible.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction onClick={confirmDelete}>Eliminar</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
