import { FormEvent, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Pencil, Plus, Trash2 } from 'lucide-react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Separator } from '@/Components/ui/separator';
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
import { PageProps } from '@/types';

interface Option {
    id: number;
    name: string;
    action_key: string;
    role_id: number;
}

interface Role {
    id: number;
    name: string;
    description: string | null;
    options: Option[];
}

type Props = PageProps<{
    aplicacion: { id: number; name: string; criticality_level: string };
    roles: Role[];
    can: { create: boolean; update: boolean; delete: boolean };
}>;

export default function ApplicationRoles({ aplicacion, roles, can }: Props) {
    const [roleOpen, setRoleOpen] = useState(false);
    const [editingRole, setEditingRole] = useState<Role | null>(null);
    const [deletingRole, setDeletingRole] = useState<Role | null>(null);

    const [optionOpen, setOptionOpen] = useState(false);
    const [optionRoleId, setOptionRoleId] = useState<number | null>(null);
    const [editingOption, setEditingOption] = useState<Option | null>(null);
    const [deletingOption, setDeletingOption] = useState<Option | null>(null);

    const roleForm = useForm({ name: '', description: '' });
    const optionForm = useForm({ name: '', action_key: '' });

    // --- Roles ---
    const openCreateRole = () => {
        setEditingRole(null);
        roleForm.reset();
        roleForm.clearErrors();
        setRoleOpen(true);
    };
    const openEditRole = (r: Role) => {
        setEditingRole(r);
        roleForm.setData({ name: r.name, description: r.description ?? '' });
        roleForm.clearErrors();
        setRoleOpen(true);
    };
    const submitRole = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => setRoleOpen(false) };
        if (editingRole) {
            roleForm.put(route('roles.update', editingRole.id), opts);
        } else {
            roleForm.post(route('roles.store', aplicacion.id), opts);
        }
    };

    // --- Opciones ---
    const openCreateOption = (roleId: number) => {
        setEditingOption(null);
        setOptionRoleId(roleId);
        optionForm.reset();
        optionForm.clearErrors();
        setOptionOpen(true);
    };
    const openEditOption = (o: Option) => {
        setEditingOption(o);
        setOptionRoleId(o.role_id);
        optionForm.setData({ name: o.name, action_key: o.action_key });
        optionForm.clearErrors();
        setOptionOpen(true);
    };
    const submitOption = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => setOptionOpen(false) };
        if (editingOption) {
            optionForm.put(route('opciones.update', editingOption.id), opts);
        } else if (optionRoleId) {
            optionForm.post(route('opciones.store', optionRoleId), opts);
        }
    };

    return (
        <AppLayout title={`Roles · ${aplicacion.name}`}>
            <Head title={`Roles · ${aplicacion.name}`} />

            <div className="mx-auto max-w-4xl space-y-4">
                <div className="flex items-center justify-between">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={route('aplicaciones.index')}>
                            <ArrowLeft className="size-4" /> Aplicaciones
                        </Link>
                    </Button>
                    {can.create && (
                        <Button onClick={openCreateRole}>
                            <Plus className="size-4" /> Nuevo rol
                        </Button>
                    )}
                </div>

                {roles.length === 0 && (
                    <Card>
                        <CardContent className="py-10 text-center text-muted-foreground">
                            Esta aplicación aún no tiene roles.
                        </CardContent>
                    </Card>
                )}

                {roles.map((role) => (
                    <Card key={role.id}>
                        <CardHeader className="flex flex-row items-start justify-between space-y-0">
                            <div>
                                <CardTitle className="text-base">{role.name}</CardTitle>
                                {role.description && (
                                    <p className="text-sm text-muted-foreground">{role.description}</p>
                                )}
                            </div>
                            <div className="flex gap-1">
                                {can.update && (
                                    <Button variant="ghost" size="icon" onClick={() => openEditRole(role)} aria-label="Editar rol">
                                        <Pencil className="size-4" />
                                    </Button>
                                )}
                                {can.delete && (
                                    <Button variant="ghost" size="icon" onClick={() => setDeletingRole(role)} aria-label="Eliminar rol">
                                        <Trash2 className="size-4 text-destructive" />
                                    </Button>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent>
                            <Separator className="mb-3" />
                            <div className="mb-2 flex items-center justify-between">
                                <span className="text-sm font-medium text-muted-foreground">Opciones</span>
                                {can.create && (
                                    <Button variant="outline" size="sm" onClick={() => openCreateOption(role.id)}>
                                        <Plus className="size-3.5" /> Opción
                                    </Button>
                                )}
                            </div>
                            {role.options.length === 0 ? (
                                <p className="text-sm text-muted-foreground">Sin opciones.</p>
                            ) : (
                                <ul className="space-y-1">
                                    {role.options.map((o) => (
                                        <li key={o.id} className="flex items-center justify-between rounded-md border px-3 py-1.5 text-sm">
                                            <span className="flex items-center gap-2">
                                                {o.name}
                                                <Badge variant="secondary" className="font-mono text-xs">{o.action_key}</Badge>
                                            </span>
                                            <span className="flex gap-1">
                                                {can.update && (
                                                    <Button variant="ghost" size="icon" className="size-7" onClick={() => openEditOption(o)} aria-label="Editar opción">
                                                        <Pencil className="size-3.5" />
                                                    </Button>
                                                )}
                                                {can.delete && (
                                                    <Button variant="ghost" size="icon" className="size-7" onClick={() => setDeletingOption(o)} aria-label="Eliminar opción">
                                                        <Trash2 className="size-3.5 text-destructive" />
                                                    </Button>
                                                )}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                ))}
            </div>

            {/* Rol: crear/editar */}
            <Dialog open={roleOpen} onOpenChange={setRoleOpen}>
                <DialogContent>
                    <form onSubmit={submitRole}>
                        <DialogHeader>
                            <DialogTitle>{editingRole ? 'Editar rol' : 'Nuevo rol'}</DialogTitle>
                            <DialogDescription>Rol de negocio dentro de {aplicacion.name}.</DialogDescription>
                        </DialogHeader>
                        <div className="space-y-4 py-4">
                            <div className="space-y-1.5">
                                <Label htmlFor="role_name">Nombre</Label>
                                <Input id="role_name" value={roleForm.data.name} onChange={(e) => roleForm.setData('name', e.target.value)} autoFocus />
                                {roleForm.errors.name && <p className="text-sm text-destructive">{roleForm.errors.name}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="role_desc">Descripción</Label>
                                <Input id="role_desc" value={roleForm.data.description} onChange={(e) => roleForm.setData('description', e.target.value)} />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setRoleOpen(false)}>Cancelar</Button>
                            <Button type="submit" disabled={roleForm.processing}>{editingRole ? 'Guardar' : 'Crear'}</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Opción: crear/editar */}
            <Dialog open={optionOpen} onOpenChange={setOptionOpen}>
                <DialogContent>
                    <form onSubmit={submitOption}>
                        <DialogHeader>
                            <DialogTitle>{editingOption ? 'Editar opción' : 'Nueva opción'}</DialogTitle>
                            <DialogDescription>Acción atómica dentro del rol.</DialogDescription>
                        </DialogHeader>
                        <div className="space-y-4 py-4">
                            <div className="space-y-1.5">
                                <Label htmlFor="opt_name">Nombre</Label>
                                <Input id="opt_name" value={optionForm.data.name} onChange={(e) => optionForm.setData('name', e.target.value)} autoFocus />
                                {optionForm.errors.name && <p className="text-sm text-destructive">{optionForm.errors.name}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="opt_key">Clave de acción</Label>
                                <Input id="opt_key" value={optionForm.data.action_key} onChange={(e) => optionForm.setData('action_key', e.target.value)} placeholder="ej. proveedor.crear" />
                                {optionForm.errors.action_key && <p className="text-sm text-destructive">{optionForm.errors.action_key}</p>}
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setOptionOpen(false)}>Cancelar</Button>
                            <Button type="submit" disabled={optionForm.processing}>{editingOption ? 'Guardar' : 'Crear'}</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Eliminar rol */}
            <AlertDialog open={!!deletingRole} onOpenChange={(o) => !o && setDeletingRole(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>¿Eliminar el rol "{deletingRole?.name}"?</AlertDialogTitle>
                        <AlertDialogDescription>Se eliminarán también sus opciones. Acción lógica y reversible.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() =>
                                deletingRole &&
                                router.delete(route('roles.destroy', deletingRole.id), {
                                    preserveScroll: true,
                                    onFinish: () => setDeletingRole(null),
                                })
                            }
                        >
                            Eliminar
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Eliminar opción */}
            <AlertDialog open={!!deletingOption} onOpenChange={(o) => !o && setDeletingOption(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>¿Eliminar la opción "{deletingOption?.name}"?</AlertDialogTitle>
                        <AlertDialogDescription>Esta acción no se puede deshacer.</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() =>
                                deletingOption &&
                                router.delete(route('opciones.destroy', deletingOption.id), {
                                    preserveScroll: true,
                                    onFinish: () => setDeletingOption(null),
                                })
                            }
                        >
                            Eliminar
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
