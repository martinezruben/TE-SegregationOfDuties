import { FormEvent, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2, UserCheck } from 'lucide-react';

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
interface Regla {
    id: number;
    name: string;
    trigger_type: 'sod_risk' | 'application' | 'cargo_misalignment';
    risk_level: 'alto' | 'medio' | 'bajo' | null;
    application_id: number | null;
    application: string | null;
    authorizer_type: 'fixed' | 'supervisor';
    authorizer_id: number | null;
    authorizer: string | null;
    is_active: boolean;
}

type Props = PageProps<{
    reglas: Regla[];
    aplicaciones: Option[];
    agentes: Option[];
    can: { create: boolean; update: boolean; delete: boolean };
}>;

const NONE = 'none';

const triggerLabel: Record<string, string> = {
    sod_risk: 'Criticidad de la matriz (SoD)',
    application: 'Por aplicación',
    cargo_misalignment: 'Desalineación con el cargo',
};

export default function AutorizacionesIndex({ reglas, aplicaciones, agentes, can }: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Regla | null>(null);
    const [deleting, setDeleting] = useState<Regla | null>(null);

    const form = useForm<{
        name: string;
        trigger_type: 'sod_risk' | 'application' | 'cargo_misalignment';
        risk_level: string;
        application_id: string;
        authorizer_type: 'fixed' | 'supervisor';
        authorizer_id: string;
        is_active: boolean;
    }>({
        name: '',
        trigger_type: 'sod_risk',
        risk_level: 'alto',
        application_id: NONE,
        authorizer_type: 'fixed',
        authorizer_id: '',
        is_active: true,
    });

    const openCreate = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
        setFormOpen(true);
    };

    const openEdit = (r: Regla) => {
        setEditing(r);
        form.setData({
            name: r.name,
            trigger_type: r.trigger_type,
            risk_level: r.risk_level ?? 'alto',
            application_id: r.application_id ? String(r.application_id) : NONE,
            authorizer_type: r.authorizer_type,
            authorizer_id: r.authorizer_id ? String(r.authorizer_id) : '',
            is_active: r.is_active,
        });
        form.clearErrors();
        setFormOpen(true);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            risk_level: data.trigger_type === 'sod_risk' ? data.risk_level : null,
            application_id: data.application_id === NONE ? '' : data.application_id,
            authorizer_id: data.authorizer_type === 'supervisor' ? '' : data.authorizer_id,
        }));
        const opts = { preserveScroll: true, onSuccess: () => setFormOpen(false) };
        if (editing) {
            form.put(route('autorizaciones.update', editing.id), opts);
        } else {
            form.post(route('autorizaciones.store'), opts);
        }
    };

    const confirmDelete = () => {
        if (!deleting) return;
        router.delete(route('autorizaciones.destroy', deleting.id), {
            preserveScroll: true,
            onFinish: () => setDeleting(null),
        });
    };

    const describeAuthorizer = (r: Regla) =>
        r.authorizer_type === 'supervisor' ? 'Su supervisor (jefe)' : r.authorizer ?? 'Sin asignar';

    return (
        <AppLayout title="Matriz de autorizaciones">
            <Head title="Autorizadores" />

            <Card className="w-full">
                <CardHeader className="flex flex-row items-center justify-between gap-4 space-y-0">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            <UserCheck className="size-5" /> Matriz de autorizaciones
                        </CardTitle>
                        <CardDescription>
                            Define quién autoriza según el disparador de la solicitud. Un mismo autorizador que cubra
                            varios motivos aprueba una sola vez.
                        </CardDescription>
                    </div>
                    {can.create && (
                        <Button onClick={openCreate}>
                            <Plus className="size-4" /> Nueva regla
                        </Button>
                    )}
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nombre</TableHead>
                                <TableHead>Disparador</TableHead>
                                <TableHead>Detalle</TableHead>
                                <TableHead>Aplicación</TableHead>
                                <TableHead>Autorizador</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead className="w-24 text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {reglas.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={7} className="py-8 text-center text-muted-foreground">
                                        No hay reglas. Si no defines ninguna, se usa el flujo por defecto (owner de
                                        aplicación y/o seguridad).
                                    </TableCell>
                                </TableRow>
                            )}
                            {reglas.map((r) => (
                                <TableRow key={r.id}>
                                    <TableCell className="font-medium">{r.name}</TableCell>
                                    <TableCell>{triggerLabel[r.trigger_type]}</TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {r.trigger_type === 'sod_risk' && r.risk_level ? (
                                            <Badge variant={r.risk_level === 'alto' ? 'destructive' : r.risk_level === 'medio' ? 'warning' : 'success'}>
                                                riesgo {r.risk_level}
                                            </Badge>
                                        ) : (
                                            '—'
                                        )}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">{r.application ?? 'Todas'}</TableCell>
                                    <TableCell>
                                        <div className="flex items-center gap-2">
                                            <Badge variant={r.authorizer_type === 'supervisor' ? 'info' : 'secondary'}>
                                                {r.authorizer_type === 'supervisor' ? 'Supervisor' : 'Fija'}
                                            </Badge>
                                            <span className={r.authorizer_type === 'fixed' && !r.authorizer ? 'text-amber-600' : ''}>
                                                {describeAuthorizer(r)}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={r.is_active ? 'success' : 'secondary'}>
                                            {r.is_active ? 'Activa' : 'Inactiva'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-1">
                                            {can.update && (
                                                <Button variant="ghost" size="icon" onClick={() => openEdit(r)} aria-label="Editar">
                                                    <Pencil className="size-4" />
                                                </Button>
                                            )}
                                            {can.delete && (
                                                <Button variant="ghost" size="icon" onClick={() => setDeleting(r)} aria-label="Eliminar">
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
                            <DialogTitle>{editing ? 'Editar regla' : 'Nueva regla de autorización'}</DialogTitle>
                            <DialogDescription>Disparador y autorizador que aplican a una solicitud.</DialogDescription>
                        </DialogHeader>

                        <div className="grid grid-cols-1 gap-4 py-4 sm:grid-cols-2">
                            <div className="space-y-1.5 sm:col-span-2">
                                <Label htmlFor="name">Nombre</Label>
                                <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} autoFocus />
                                {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                            </div>

                            <div className="space-y-1.5">
                                <Label>Disparador</Label>
                                <Select value={form.data.trigger_type} onValueChange={(v) => form.setData('trigger_type', v as Regla['trigger_type'])}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="sod_risk">Criticidad de la matriz (SoD)</SelectItem>
                                        <SelectItem value="application">Por aplicación</SelectItem>
                                        <SelectItem value="cargo_misalignment">Desalineación con el cargo</SelectItem>
                                    </SelectContent>
                                </Select>
                                {form.errors.trigger_type && <p className="text-sm text-destructive">{form.errors.trigger_type}</p>}
                            </div>

                            {form.data.trigger_type === 'sod_risk' && (
                                <div className="space-y-1.5">
                                    <Label>Nivel de riesgo</Label>
                                    <Select value={form.data.risk_level} onValueChange={(v) => form.setData('risk_level', v)}>
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="alto">Alto</SelectItem>
                                            <SelectItem value="medio">Medio</SelectItem>
                                            <SelectItem value="bajo">Bajo</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {form.errors.risk_level && <p className="text-sm text-destructive">{form.errors.risk_level}</p>}
                                </div>
                            )}

                            <div className="space-y-1.5 sm:col-span-2">
                                <Label>
                                    Aplicación {form.data.trigger_type === 'application' ? '(requerida)' : '(opcional — limita el alcance)'}
                                </Label>
                                <Select value={form.data.application_id} onValueChange={(v) => form.setData('application_id', v)}>
                                    <SelectTrigger><SelectValue placeholder="Todas las aplicaciones" /></SelectTrigger>
                                    <SelectContent>
                                        {form.data.trigger_type !== 'application' && <SelectItem value={NONE}>Todas las aplicaciones</SelectItem>}
                                        {aplicaciones.map((a) => (
                                            <SelectItem key={a.id} value={String(a.id)}>{a.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {form.errors.application_id && <p className="text-sm text-destructive">{form.errors.application_id}</p>}
                            </div>

                            <div className="space-y-1.5">
                                <Label>Tipo de autorizador</Label>
                                <Select value={form.data.authorizer_type} onValueChange={(v) => form.setData('authorizer_type', v as Regla['authorizer_type'])}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="fixed">Persona fija</SelectItem>
                                        <SelectItem value="supervisor">Su supervisor (jefe)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            {form.data.authorizer_type === 'fixed' && (
                                <div className="space-y-1.5">
                                    <Label htmlFor="autorizaciones-authorizer">Autorizador</Label>
                                    <Combobox
                                        id="autorizaciones-authorizer"
                                        value={form.data.authorizer_id}
                                        onChange={(v) => form.setData('authorizer_id', v)}
                                        searchPlaceholder="Buscar agente…"
                                        options={agentes.map((a) => ({ value: String(a.id), label: a.name }))}
                                    />
                                    {form.errors.authorizer_id && <p className="text-sm text-destructive">{form.errors.authorizer_id}</p>}
                                </div>
                            )}

                            <SwitchField id="autorizaciones-is_active" label="Activa" className="sm:col-span-2" checked={form.data.is_active} onCheckedChange={(v) => form.setData('is_active', v)} />
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
                        <AlertDialogTitle>¿Eliminar la regla "{deleting?.name}"?</AlertDialogTitle>
                        <AlertDialogDescription>Dejará de aplicarse a nuevas solicitudes.</AlertDialogDescription>
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
