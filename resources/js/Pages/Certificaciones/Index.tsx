import { FormEvent, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { BadgeCheck, Plus } from 'lucide-react';

import AppLayout from '@/Layouts/AppLayout';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

interface Campana {
    id: number;
    name: string;
    status: 'abierta' | 'cerrada';
    due_date: string;
    overdue: boolean;
    total: number;
    pendientes: number;
    mantenidos: number;
    revocados: number;
    mis_pendientes: number;
}
interface Option {
    id: number;
    name: string;
}

const ALL = 'all';

export default function Index({
    campanas,
    can,
    opciones,
}: {
    campanas: Campana[];
    can: { create: boolean };
    opciones: { aplicaciones: Option[]; areas: Option[] } | null;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ name: string; due_date: string; application_id: string; area_id: string; only_conflicts: boolean }>({
        name: '',
        due_date: '',
        application_id: ALL,
        area_id: ALL,
        only_conflicts: false,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({
            ...d,
            application_id: d.application_id === ALL ? null : d.application_id,
            area_id: d.area_id === ALL ? null : d.area_id,
        }));
        form.post(route('certificaciones.store'), { onSuccess: () => setOpen(false) });
    };

    return (
        <AppLayout title="Recertificación de accesos">
            <Head title="Recertificación de accesos" />

            <Card>
                <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            <BadgeCheck className="size-5" /> Recertificación de accesos
                        </CardTitle>
                        <CardDescription>
                            Campañas periódicas en las que cada responsable confirma o revoca los accesos vigentes. Es la
                            evidencia que piden las auditorías (SOX, ISO 27001).
                        </CardDescription>
                    </div>
                    {can.create && (
                        <Button onClick={() => setOpen(true)}>
                            <Plus className="size-4" /> Nueva campaña
                        </Button>
                    )}
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Campaña</TableHead>
                                <TableHead>Vence</TableHead>
                                <TableHead>Avance</TableHead>
                                <TableHead>Resultado</TableHead>
                                <TableHead>Estado</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {campanas.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={5} className="py-8 text-center text-muted-foreground">
                                        No hay campañas.
                                    </TableCell>
                                </TableRow>
                            )}
                            {campanas.map((c) => {
                                const done = c.total - c.pendientes;
                                const pct = c.total ? Math.round((done / c.total) * 100) : 0;
                                return (
                                    <TableRow key={c.id}>
                                        <TableCell>
                                            <Link className="font-medium underline" href={route('certificaciones.show', c.id)}>
                                                {c.name}
                                            </Link>
                                            {c.mis_pendientes > 0 && c.status === 'abierta' && (
                                                <div className="mt-1">
                                                    <Badge variant="warning">{c.mis_pendientes} pendiente(s) para ti</Badge>
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className={c.overdue ? 'font-medium text-destructive' : ''}>
                                            {c.due_date}
                                            {c.overdue && <div className="text-xs">Vencida</div>}
                                        </TableCell>
                                        <TableCell className="min-w-40">
                                            <div className="h-2 w-full overflow-hidden rounded bg-muted" aria-hidden>
                                                <div className="h-full bg-primary" style={{ width: `${pct}%` }} />
                                            </div>
                                            <div className="mt-1 text-xs text-muted-foreground">
                                                {done}/{c.total} ({pct}%)
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-xs text-muted-foreground">
                                            {c.mantenidos} mantenidos · {c.revocados} revocados · {c.pendientes} pendientes
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={c.status === 'abierta' ? 'info' : 'secondary'}>
                                                {c.status === 'abierta' ? 'Abierta' : 'Cerrada'}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            {can.create && opciones && (
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Nueva campaña de recertificación</DialogTitle>
                            <DialogDescription>
                                Se toma una foto de los accesos vigentes y se asigna un revisor por acceso: el owner de la
                                aplicación, o el supervisor del usuario, o Seguridad si no hay ninguno.
                            </DialogDescription>
                        </DialogHeader>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-1.5">
                                <Label htmlFor="name">Nombre</Label>
                                <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="Recertificación Q4 2026" />
                                {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="due_date">Fecha límite</Label>
                                <Input id="due_date" type="date" value={form.data.due_date} onChange={(e) => form.setData('due_date', e.target.value)} />
                                {form.errors.due_date && <p className="text-sm text-destructive">{form.errors.due_date}</p>}
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1.5">
                                    <Label>Aplicación</Label>
                                    <Select value={form.data.application_id} onValueChange={(v) => form.setData('application_id', v)}>
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={ALL}>Todas</SelectItem>
                                            {opciones.aplicaciones.map((a) => <SelectItem key={a.id} value={String(a.id)}>{a.name}</SelectItem>)}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Área</Label>
                                    <Select value={form.data.area_id} onValueChange={(v) => form.setData('area_id', v)}>
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={ALL}>Todas</SelectItem>
                                            {opciones.areas.map((a) => <SelectItem key={a.id} value={String(a.id)}>{a.name}</SelectItem>)}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <Checkbox id="only_conflicts" checked={form.data.only_conflicts} onCheckedChange={(v) => form.setData('only_conflicts', v === true)} />
                                <Label htmlFor="only_conflicts" className="font-normal">Solo accesos que participan en conflictos SoD</Label>
                            </div>
                            <div className="flex justify-end gap-2">
                                <Button type="button" variant="outline" onClick={() => setOpen(false)}>Cancelar</Button>
                                <Button type="submit" disabled={form.processing}>Crear y notificar revisores</Button>
                            </div>
                        </form>
                    </DialogContent>
                </Dialog>
            )}
        </AppLayout>
    );
}
