import { FormEvent, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, RotateCcw, Search, Trash2 } from 'lucide-react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
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
import { useListFilters } from '@/hooks/useListFilters';
import { PageProps } from '@/types';

interface Agent {
    id: number;
    name: string;
    email: string;
    roles: string[];
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
    role: string | null;
    two_factor: string | null;
    [key: string]: string | number | null;
}

const ALL = 'all';
const EMPTY: Filters = { search: '', role: null, two_factor: null };

type Props = PageProps<{
    agentes: Paginated<Agent>;
    roles: string[];
    filters: Filters;
    currentUserId: number;
    can: { create: boolean; update: boolean; delete: boolean };
}>;

export default function AgentesIndex({ agentes, roles, filters, currentUserId, can }: Props) {
    const { f, apply, setSearch, reset, hasFilters } = useListFilters('agentes.index', filters, EMPTY);
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Agent | null>(null);
    const [deleting, setDeleting] = useState<Agent | null>(null);

    const form = useForm<{ name: string; email: string; password: string; roles: string[] }>({
        name: '', email: '', password: '', roles: [],
    });

    const openCreate = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
        setFormOpen(true);
    };
    const openEdit = (a: Agent) => {
        setEditing(a);
        form.setData({ name: a.name, email: a.email, password: '', roles: a.roles });
        form.clearErrors();
        setFormOpen(true);
    };
    const toggleRole = (role: string) => {
        form.setData('roles', form.data.roles.includes(role) ? form.data.roles.filter((r) => r !== role) : [...form.data.roles, role]);
    };
    const submit = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => setFormOpen(false) };
        if (editing) form.put(route('agentes.update', editing.id), opts);
        else form.post(route('agentes.store'), opts);
    };

    return (
        <AppLayout title="Agentes">
            <Head title="Agentes" />

            <Card className="w-full">
                <CardHeader className="flex flex-row items-center justify-between gap-4 space-y-0">
                    <div>
                        <CardTitle>Agentes / Operadores</CardTitle>
                        <CardDescription>Usuarios con acceso al sistema y sus roles RBAC.</CardDescription>
                    </div>
                    {can.create && (
                        <Button onClick={openCreate}>
                            <Plus className="size-4" /> Nuevo agente
                        </Button>
                    )}
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid grid-cols-1 gap-3 rounded-lg border bg-muted/20 p-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="agentes-search" className="text-xs">Buscar (nombre o correo)</Label>
                            <div className="relative">
                                <Search className="pointer-events-none absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                                <Input id="agentes-search" className="pl-8" placeholder="Escribe para filtrar…" value={f.search} onChange={(e) => setSearch(e.target.value)} />
                            </div>
                        </div>
                        <div className="space-y-1.5">
                            <Label className="text-xs">Rol de plataforma</Label>
                            <Select value={f.role ?? ALL} onValueChange={(v) => apply({ role: v === ALL ? null : v })}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>Todos</SelectItem>
                                    {roles.map((r) => <SelectItem key={r} value={r}>{r}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label className="text-xs">Verificación en dos pasos</Label>
                            <Select value={f.two_factor ?? ALL} onValueChange={(v) => apply({ two_factor: v === ALL ? null : v })}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>Todos</SelectItem>
                                    <SelectItem value="activo">Activada</SelectItem>
                                    <SelectItem value="inactivo">Sin activar</SelectItem>
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
                                <TableHead>Nombre</TableHead>
                                <TableHead>Correo</TableHead>
                                <TableHead>Roles</TableHead>
                                <TableHead className="w-24 text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {agentes.data.map((a) => (
                                <TableRow key={a.id}>
                                    <TableCell className="font-medium">{a.name}</TableCell>
                                    <TableCell className="text-muted-foreground">{a.email}</TableCell>
                                    <TableCell>
                                        <div className="flex flex-wrap gap-1">
                                            {a.roles.map((r) => (
                                                <Badge key={r} variant="secondary">{r}</Badge>
                                            ))}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-1">
                                            {can.update && (
                                                <Button variant="ghost" size="icon" onClick={() => openEdit(a)} aria-label="Editar">
                                                    <Pencil className="size-4" />
                                                </Button>
                                            )}
                                            {can.delete && a.id !== currentUserId && (
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
                        <span>{agentes.total} registro(s)</span>
                        <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" disabled={!agentes.prev_page_url} onClick={() => agentes.prev_page_url && router.get(agentes.prev_page_url, {}, { preserveState: true })}>Anterior</Button>
                            <span>Página {agentes.current_page} de {agentes.last_page}</span>
                            <Button variant="outline" size="sm" disabled={!agentes.next_page_url} onClick={() => agentes.next_page_url && router.get(agentes.next_page_url, {}, { preserveState: true })}>Siguiente</Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent>
                    <form onSubmit={submit}>
                        <DialogHeader>
                            <DialogTitle>{editing ? 'Editar agente' : 'Nuevo agente'}</DialogTitle>
                            <DialogDescription>Datos de acceso y roles de plataforma.</DialogDescription>
                        </DialogHeader>
                        <div className="space-y-4 py-4">
                            <div className="space-y-1.5">
                                <Label htmlFor="name">Nombre</Label>
                                <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} autoFocus />
                                {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="email">Correo</Label>
                                <Input id="email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                                {form.errors.email && <p className="text-sm text-destructive">{form.errors.email}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="password">Contraseña {editing && <span className="text-xs text-muted-foreground">(dejar en blanco para no cambiar)</span>}</Label>
                                <Input id="password" type="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} />
                                {form.errors.password && <p className="text-sm text-destructive">{form.errors.password}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label>Roles</Label>
                                <div className="grid grid-cols-2 gap-2">
                                    {roles.map((r) => (
                                        <div key={r} className="flex items-center gap-2 text-sm">
                                            <Checkbox id={`agente-rol-${r}`} checked={form.data.roles.includes(r)} onCheckedChange={() => toggleRole(r)} />
                                            <Label htmlFor={`agente-rol-${r}`} className="font-normal">{r}</Label>
                                        </div>
                                    ))}
                                </div>
                            </div>
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
                        <AlertDialogTitle>¿Eliminar al agente "{deleting?.name}"?</AlertDialogTitle>
                        <AlertDialogDescription>Perderá el acceso al sistema.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction onClick={() => deleting && router.delete(route('agentes.destroy', deleting.id), { preserveScroll: true, onFinish: () => setDeleting(null) })}>
                            Eliminar
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
