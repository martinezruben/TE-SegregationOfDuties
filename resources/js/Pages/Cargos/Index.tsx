import { FormEvent, useEffect, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ListChecks, Pencil, Plus, Search, Trash2 } from 'lucide-react';

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
import { SwitchField } from '@/Components/app/SwitchField';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { PageProps } from '@/types';

interface Generic {
    id: number;
    name: string;
}

interface Cargo {
    id: number;
    name: string;
    description: string | null;
    is_generic: boolean;
    is_active: boolean;
    generic_position_id: number | null;
    generic_position?: Generic | null;
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
    cargos: Paginated<Cargo>;
    genericos: Generic[];
    filters: { search: string };
    can: { create: boolean; update: boolean; delete: boolean };
}>;

const NONE = 'none';

export default function CargosIndex({ cargos, genericos, filters, can }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Cargo | null>(null);
    const [deleting, setDeleting] = useState<Cargo | null>(null);

    useEffect(() => {
        const t = setTimeout(() => {
            if (search === (filters.search ?? '')) return;
            router.get(route('cargos.index'), { search }, { preserveState: true, replace: true });
        }, 300);
        return () => clearTimeout(t);
    }, [search]); // eslint-disable-line react-hooks/exhaustive-deps

    const form = useForm<{
        name: string;
        description: string;
        is_generic: boolean;
        is_active: boolean;
        generic_position_id: string;
    }>({ name: '', description: '', is_generic: false, is_active: true, generic_position_id: NONE });

    const openCreate = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
        setFormOpen(true);
    };

    const openEdit = (c: Cargo) => {
        setEditing(c);
        form.setData({
            name: c.name,
            description: c.description ?? '',
            is_generic: c.is_generic,
            is_active: c.is_active,
            generic_position_id: c.generic_position_id ? String(c.generic_position_id) : NONE,
        });
        form.clearErrors();
        setFormOpen(true);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const opts = {
            preserveScroll: true,
            onSuccess: () => setFormOpen(false),
        };
        const transform = (data: typeof form.data) => ({
            ...data,
            generic_position_id: data.is_generic || data.generic_position_id === NONE ? null : Number(data.generic_position_id),
        });
        form.transform(transform);
        if (editing) {
            form.put(route('cargos.update', editing.id), opts);
        } else {
            form.post(route('cargos.store'), opts);
        }
    };

    const confirmDelete = () => {
        if (!deleting) return;
        router.delete(route('cargos.destroy', deleting.id), {
            preserveScroll: true,
            onFinish: () => setDeleting(null),
        });
    };

    return (
        <AppLayout title="Cargos">
            <Head title="Cargos" />

            <Card className="w-full">
                <CardHeader className="flex flex-row items-center justify-between gap-4 space-y-0">
                    <div>
                        <CardTitle>Cargos</CardTitle>
                        <CardDescription>Puestos y su equivalencia a un cargo genérico (para validaciones).</CardDescription>
                    </div>
                    {can.create && (
                        <Button onClick={openCreate}>
                            <Plus className="size-4" /> Nuevo cargo
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
                                <TableHead>Tipo</TableHead>
                                <TableHead>Cargo genérico</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead className="w-24 text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {cargos.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={5} className="py-8 text-center text-muted-foreground">
                                        No hay cargos que coincidan.
                                    </TableCell>
                                </TableRow>
                            )}
                            {cargos.data.map((c) => (
                                <TableRow key={c.id}>
                                    <TableCell className="font-medium">{c.name}</TableCell>
                                    <TableCell>
                                        <Badge variant={c.is_generic ? 'info' : 'secondary'}>
                                            {c.is_generic ? 'Genérico' : 'Específico'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {c.generic_position?.name ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={c.is_active ? 'success' : 'secondary'}>
                                            {c.is_active ? 'Activo' : 'Inactivo'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button variant="ghost" size="icon" asChild aria-label="Reglas de rol">
                                                <Link href={route('reglas.index', c.id)}>
                                                    <ListChecks className="size-4" />
                                                </Link>
                                            </Button>
                                            {can.update && (
                                                <Button variant="ghost" size="icon" onClick={() => openEdit(c)} aria-label="Editar">
                                                    <Pencil className="size-4" />
                                                </Button>
                                            )}
                                            {can.delete && (
                                                <Button variant="ghost" size="icon" onClick={() => setDeleting(c)} aria-label="Eliminar">
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
                        <span>{cargos.total} registro(s)</span>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!cargos.prev_page_url}
                                onClick={() => cargos.prev_page_url && router.get(cargos.prev_page_url, {}, { preserveState: true })}
                            >
                                Anterior
                            </Button>
                            <span>
                                Página {cargos.current_page} de {cargos.last_page}
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!cargos.next_page_url}
                                onClick={() => cargos.next_page_url && router.get(cargos.next_page_url, {}, { preserveState: true })}
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
                            <DialogTitle>{editing ? 'Editar cargo' : 'Nuevo cargo'}</DialogTitle>
                            <DialogDescription>Un cargo específico puede apuntar a un cargo genérico equivalente.</DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4 py-4">
                            <div className="space-y-1.5">
                                <Label htmlFor="name">Nombre</Label>
                                <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} autoFocus />
                                {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="description">Descripción</Label>
                                <Input
                                    id="description"
                                    value={form.data.description}
                                    onChange={(e) => form.setData('description', e.target.value)}
                                />
                            </div>
                            <SwitchField id="cargos-is_generic" label="Es un cargo genérico (plantilla)" checked={form.data.is_generic} onCheckedChange={(v) => form.setData('is_generic', v)} />

                            {!form.data.is_generic && (
                                <div className="space-y-1.5">
                                    <Label>Cargo genérico equivalente</Label>
                                    <Select
                                        value={form.data.generic_position_id}
                                        onValueChange={(v) => form.setData('generic_position_id', v)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Sin equivalente" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NONE}>Sin equivalente</SelectItem>
                                            {genericos.map((g) => (
                                                <SelectItem key={g.id} value={String(g.id)}>
                                                    {g.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {form.errors.generic_position_id && (
                                        <p className="text-sm text-destructive">{form.errors.generic_position_id}</p>
                                    )}
                                </div>
                            )}

                            <SwitchField id="cargos-is_active" label="Activo" checked={form.data.is_active} onCheckedChange={(v) => form.setData('is_active', v)} />
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
                        <AlertDialogTitle>¿Eliminar el cargo "{deleting?.name}"?</AlertDialogTitle>
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
