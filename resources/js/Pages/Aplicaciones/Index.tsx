import { FormEvent, useEffect, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { KeyRound, Pencil, Plus, Search, Trash2 } from 'lucide-react';

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

interface Owner {
    id: number;
    name: string;
}

interface App {
    id: number;
    name: string;
    description: string | null;
    criticality_level: 'baja' | 'media' | 'alta';
    is_active: boolean;
    owner_id: number | null;
    owner?: Owner | null;
    roles_count: number;
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
    aplicaciones: Paginated<App>;
    owners: Owner[];
    criticidades: { value: string; label: string }[];
    filters: { search: string };
    can: { create: boolean; update: boolean; delete: boolean };
}>;

const NONE = 'none';

const critVariant = (c: App['criticality_level']) =>
    c === 'alta' ? 'destructive' : c === 'media' ? 'warning' : 'success';

export default function AplicacionesIndex({ aplicaciones, owners, criticidades, filters, can }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<App | null>(null);
    const [deleting, setDeleting] = useState<App | null>(null);

    useEffect(() => {
        const t = setTimeout(() => {
            if (search === (filters.search ?? '')) return;
            router.get(route('aplicaciones.index'), { search }, { preserveState: true, replace: true });
        }, 300);
        return () => clearTimeout(t);
    }, [search]); // eslint-disable-line react-hooks/exhaustive-deps

    const form = useForm<{
        name: string;
        description: string;
        criticality_level: string;
        owner_id: string;
        is_active: boolean;
    }>({ name: '', description: '', criticality_level: 'media', owner_id: NONE, is_active: true });

    const openCreate = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
        setFormOpen(true);
    };

    const openEdit = (a: App) => {
        setEditing(a);
        form.setData({
            name: a.name,
            description: a.description ?? '',
            criticality_level: a.criticality_level,
            owner_id: a.owner_id ? String(a.owner_id) : NONE,
            is_active: a.is_active,
        });
        form.clearErrors();
        setFormOpen(true);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => setFormOpen(false) };
        form.transform((data) => ({
            ...data,
            owner_id: data.owner_id === NONE ? null : Number(data.owner_id),
        }));
        if (editing) {
            form.put(route('aplicaciones.update', editing.id), opts);
        } else {
            form.post(route('aplicaciones.store'), opts);
        }
    };

    const confirmDelete = () => {
        if (!deleting) return;
        router.delete(route('aplicaciones.destroy', deleting.id), {
            preserveScroll: true,
            onFinish: () => setDeleting(null),
        });
    };

    return (
        <AppLayout title="Aplicaciones">
            <Head title="Aplicaciones" />

            <Card className="mx-auto max-w-5xl">
                <CardHeader className="flex flex-row items-center justify-between gap-4 space-y-0">
                    <div>
                        <CardTitle>Aplicaciones</CardTitle>
                        <CardDescription>Sistemas administrados; su criticidad define el flujo de aprobación.</CardDescription>
                    </div>
                    {can.create && (
                        <Button onClick={openCreate}>
                            <Plus className="size-4" /> Nueva aplicación
                        </Button>
                    )}
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="relative max-w-sm">
                        <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                        <Input
                            placeholder="Buscar por nombre…"
                            className="pl-8"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>

                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nombre</TableHead>
                                <TableHead>Criticidad</TableHead>
                                <TableHead>Responsable</TableHead>
                                <TableHead>Roles</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead className="w-24 text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {aplicaciones.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={6} className="py-8 text-center text-muted-foreground">
                                        No hay aplicaciones que coincidan.
                                    </TableCell>
                                </TableRow>
                            )}
                            {aplicaciones.data.map((a) => (
                                <TableRow key={a.id}>
                                    <TableCell className="font-medium">{a.name}</TableCell>
                                    <TableCell>
                                        <Badge variant={critVariant(a.criticality_level)}>
                                            {a.criticality_level}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">{a.owner?.name ?? '—'}</TableCell>
                                    <TableCell className="text-muted-foreground">{a.roles_count}</TableCell>
                                    <TableCell>
                                        <Badge variant={a.is_active ? 'success' : 'secondary'}>
                                            {a.is_active ? 'Activa' : 'Inactiva'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button variant="ghost" size="icon" asChild aria-label="Gestionar roles">
                                                <Link href={route('roles.index', a.id)}>
                                                    <KeyRound className="size-4" />
                                                </Link>
                                            </Button>
                                            {can.update && (
                                                <Button variant="ghost" size="icon" onClick={() => openEdit(a)} aria-label="Editar">
                                                    <Pencil className="size-4" />
                                                </Button>
                                            )}
                                            {can.delete && (
                                                <Button variant="ghost" size="icon" onClick={() => setDeleting(a)} aria-label="Eliminar">
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
                        <span>{aplicaciones.total} registro(s)</span>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!aplicaciones.prev_page_url}
                                onClick={() => aplicaciones.prev_page_url && router.get(aplicaciones.prev_page_url, {}, { preserveState: true })}
                            >
                                Anterior
                            </Button>
                            <span>
                                Página {aplicaciones.current_page} de {aplicaciones.last_page}
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!aplicaciones.next_page_url}
                                onClick={() => aplicaciones.next_page_url && router.get(aplicaciones.next_page_url, {}, { preserveState: true })}
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
                            <DialogTitle>{editing ? 'Editar aplicación' : 'Nueva aplicación'}</DialogTitle>
                            <DialogDescription>La criticidad determina la profundidad del flujo de aprobación.</DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4 py-4">
                            <div className="space-y-1.5">
                                <Label htmlFor="name">Nombre</Label>
                                <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} autoFocus />
                                {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="description">Descripción</Label>
                                <Input id="description" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                            </div>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label>Criticidad</Label>
                                    <Select value={form.data.criticality_level} onValueChange={(v) => form.setData('criticality_level', v)}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {criticidades.map((c) => (
                                                <SelectItem key={c.value} value={c.value}>
                                                    {c.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {form.errors.criticality_level && <p className="text-sm text-destructive">{form.errors.criticality_level}</p>}
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Responsable (owner)</Label>
                                    <Select value={form.data.owner_id} onValueChange={(v) => form.setData('owner_id', v)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Sin asignar" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NONE}>Sin asignar</SelectItem>
                                            {owners.map((o) => (
                                                <SelectItem key={o.id} value={String(o.id)}>
                                                    {o.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    className="rounded border-input"
                                    checked={form.data.is_active}
                                    onChange={(e) => form.setData('is_active', e.target.checked)}
                                />
                                Activa
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
                        <AlertDialogTitle>¿Eliminar la aplicación "{deleting?.name}"?</AlertDialogTitle>
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
