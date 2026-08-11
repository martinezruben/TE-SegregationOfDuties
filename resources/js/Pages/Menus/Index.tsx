import { FormEvent, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';

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

interface MenuRow {
    id: number;
    parent_id: number | null;
    parent: string | null;
    label: string;
    icon: string | null;
    route: string | null;
    permission_name: string | null;
    order: number;
    is_active: boolean;
}

type Props = PageProps<{
    menus: MenuRow[];
    parents: { id: number; label: string }[];
    permissions: string[];
    can: { create: boolean; update: boolean; delete: boolean };
}>;

const NONE = 'none';

export default function MenusIndex({ menus, parents, permissions, can }: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<MenuRow | null>(null);
    const [deleting, setDeleting] = useState<MenuRow | null>(null);

    const form = useForm<{
        label: string;
        icon: string;
        route: string;
        permission_name: string;
        parent_id: string;
        order: string;
        is_active: boolean;
    }>({ label: '', icon: '', route: '', permission_name: NONE, parent_id: NONE, order: '0', is_active: true });

    const openCreate = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
        setFormOpen(true);
    };
    const openEdit = (m: MenuRow) => {
        setEditing(m);
        form.setData({
            label: m.label,
            icon: m.icon ?? '',
            route: m.route ?? '',
            permission_name: m.permission_name ?? NONE,
            parent_id: m.parent_id ? String(m.parent_id) : NONE,
            order: String(m.order),
            is_active: m.is_active,
        });
        form.clearErrors();
        setFormOpen(true);
    };
    const submit = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => setFormOpen(false) };
        form.transform((data) => ({
            ...data,
            permission_name: data.permission_name === NONE ? null : data.permission_name,
            parent_id: data.parent_id === NONE ? null : Number(data.parent_id),
            order: Number(data.order) || 0,
        }));
        if (editing) form.put(route('menus.update', editing.id), opts);
        else form.post(route('menus.store'), opts);
    };

    return (
        <AppLayout title="Opciones de menú">
            <Head title="Opciones de menú" />

            <Card className="w-full">
                <CardHeader className="flex flex-row items-center justify-between gap-4 space-y-0">
                    <div>
                        <CardTitle>Opciones de menú</CardTitle>
                        <CardDescription>Configura el menú lateral. La visibilidad depende del permiso asociado.</CardDescription>
                    </div>
                    {can.create && (
                        <Button onClick={openCreate}>
                            <Plus className="size-4" /> Nueva opción
                        </Button>
                    )}
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Etiqueta</TableHead>
                                <TableHead>Padre</TableHead>
                                <TableHead>Ruta</TableHead>
                                <TableHead>Permiso</TableHead>
                                <TableHead>Orden</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead className="w-24 text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {menus.map((m) => (
                                <TableRow key={m.id}>
                                    <TableCell className={m.parent_id ? 'pl-6 font-medium' : 'font-semibold'}>{m.label}</TableCell>
                                    <TableCell className="text-muted-foreground">{m.parent ?? '—'}</TableCell>
                                    <TableCell className="font-mono text-xs text-muted-foreground">{m.route ?? '—'}</TableCell>
                                    <TableCell className="font-mono text-xs text-muted-foreground">{m.permission_name ?? '—'}</TableCell>
                                    <TableCell className="text-muted-foreground">{m.order}</TableCell>
                                    <TableCell>
                                        <Badge variant={m.is_active ? 'success' : 'secondary'}>{m.is_active ? 'Activa' : 'Inactiva'}</Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-1">
                                            {can.update && (
                                                <Button variant="ghost" size="icon" onClick={() => openEdit(m)} aria-label="Editar">
                                                    <Pencil className="size-4" />
                                                </Button>
                                            )}
                                            {can.delete && (
                                                <Button variant="ghost" size="icon" onClick={() => setDeleting(m)} aria-label="Eliminar">
                                                    <Trash2 className="size-4 text-destructive" />
                                                </Button>
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent>
                    <form onSubmit={submit}>
                        <DialogHeader>
                            <DialogTitle>{editing ? 'Editar opción' : 'Nueva opción'}</DialogTitle>
                            <DialogDescription>Los agrupadores no tienen ruta; las hojas sí.</DialogDescription>
                        </DialogHeader>
                        <div className="grid grid-cols-1 gap-4 py-4 sm:grid-cols-2">
                            <div className="space-y-1.5">
                                <Label htmlFor="label">Etiqueta</Label>
                                <Input id="label" value={form.data.label} onChange={(e) => form.setData('label', e.target.value)} autoFocus />
                                {form.errors.label && <p className="text-sm text-destructive">{form.errors.label}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="icon">Ícono (lucide)</Label>
                                <Input id="icon" value={form.data.icon} onChange={(e) => form.setData('icon', e.target.value)} placeholder="folder" />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="route">Ruta (nombre)</Label>
                                <Input id="route" value={form.data.route} onChange={(e) => form.setData('route', e.target.value)} placeholder="areas.index" />
                            </div>
                            <div className="space-y-1.5">
                                <Label>Padre</Label>
                                <Select value={form.data.parent_id} onValueChange={(v) => form.setData('parent_id', v)}>
                                    <SelectTrigger><SelectValue placeholder="Nivel superior" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NONE}>Nivel superior</SelectItem>
                                        {parents.filter((p) => p.id !== editing?.id).map((p) => (
                                            <SelectItem key={p.id} value={String(p.id)}>{p.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {form.errors.parent_id && <p className="text-sm text-destructive">{form.errors.parent_id}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label>Permiso (ver)</Label>
                                <Select value={form.data.permission_name} onValueChange={(v) => form.setData('permission_name', v)}>
                                    <SelectTrigger><SelectValue placeholder="Sin permiso" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NONE}>Sin permiso (visible)</SelectItem>
                                        {permissions.map((p) => (
                                            <SelectItem key={p} value={p}>{p}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="order">Orden</Label>
                                <Input id="order" type="number" value={form.data.order} onChange={(e) => form.setData('order', e.target.value)} />
                            </div>
                            <label className="flex items-center gap-2 text-sm sm:col-span-2">
                                <input type="checkbox" className="rounded border-input" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} />
                                Activa
                            </label>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setFormOpen(false)}>Cancelar</Button>
                            <Button type="submit" disabled={form.processing}>{editing ? 'Guardar' : 'Crear'}</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <AlertDialog open={!!deleting} onOpenChange={(o) => !o && setDeleting(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>¿Eliminar "{deleting?.label}"?</AlertDialogTitle>
                        <AlertDialogDescription>También se eliminarán sus subopciones.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction onClick={() => deleting && router.delete(route('menus.destroy', deleting.id), { preserveScroll: true, onFinish: () => setDeleting(null) })}>
                            Eliminar
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
