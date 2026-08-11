import { FormEvent, useEffect, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';

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
    filters: { search: string };
    can: { create: boolean; update: boolean; delete: boolean };
}>;

const NONE = 'none';

export default function GestionadosIndex({ usuarios, areas, cargos, supervisores, filters, can }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Managed | null>(null);
    const [deleting, setDeleting] = useState<Managed | null>(null);

    useEffect(() => {
        const t = setTimeout(() => {
            if (search === (filters.search ?? '')) return;
            router.get(route('gestionados.index'), { search }, { preserveState: true, replace: true });
        }, 300);
        return () => clearTimeout(t);
    }, [search]); // eslint-disable-line react-hooks/exhaustive-deps

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
                    <div className="relative max-w-sm">
                        <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                        <Input
                            placeholder="Buscar por nombre, ID o IGG…"
                            className="pl-8"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
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
                                <Label>Área</Label>
                                <Select value={form.data.area_id} onValueChange={(v) => form.setData('area_id', v)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Selecciona…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {areas.map((a) => (
                                            <SelectItem key={a.id} value={String(a.id)}>
                                                {a.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {form.errors.area_id && <p className="text-sm text-destructive">{form.errors.area_id}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label>Cargo</Label>
                                <Select value={form.data.job_position_id} onValueChange={(v) => form.setData('job_position_id', v)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Selecciona…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {cargos.map((c) => (
                                            <SelectItem key={c.id} value={String(c.id)}>
                                                {c.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {form.errors.job_position_id && <p className="text-sm text-destructive">{form.errors.job_position_id}</p>}
                            </div>
                            <div className="space-y-1.5 sm:col-span-2">
                                <Label>Jefe / Supervisor (agente autorizador)</Label>
                                <Select value={form.data.supervisor_id} onValueChange={(v) => form.setData('supervisor_id', v)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Sin supervisor" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NONE}>Sin supervisor</SelectItem>
                                        {supervisores.map((s) => (
                                            <SelectItem key={s.id} value={String(s.id)}>
                                                {s.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-xs text-muted-foreground">
                                    Se usa cuando una regla de autorización indica "su supervisor".
                                </p>
                                {form.errors.supervisor_id && <p className="text-sm text-destructive">{form.errors.supervisor_id}</p>}
                            </div>
                            <label className="flex items-center gap-2 text-sm sm:col-span-2">
                                <input
                                    type="checkbox"
                                    className="rounded border-input"
                                    checked={form.data.is_active}
                                    onChange={(e) => form.setData('is_active', e.target.checked)}
                                />
                                Activo
                            </label>
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
