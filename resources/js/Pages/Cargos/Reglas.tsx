import { FormEvent, useMemo, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Label } from '@/Components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Combobox } from '@/Components/app/Combobox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { PageProps } from '@/types';

interface Rule {
    role_id: number;
    role_name: string;
    application: string | null;
    rule_type: 'permitido' | 'no_permitido';
}
interface AppWithRoles {
    id: number;
    name: string;
    roles: { id: number; application_id: number; name: string }[];
}

type Props = PageProps<{
    cargo: { id: number; name: string; is_generic: boolean; generic: string | null };
    reglas: Rule[];
    aplicaciones: AppWithRoles[];
    tipos: { value: string; label: string }[];
}>;

export default function CargoReglas({ cargo, reglas, aplicaciones, tipos }: Props) {
    const [appId, setAppId] = useState('');
    const roles = useMemo(() => aplicaciones.find((a) => String(a.id) === appId)?.roles ?? [], [appId, aplicaciones]);

    const form = useForm<{ role_id: string; rule_type: string }>({ role_id: '', rule_type: 'permitido' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('reglas.store', cargo.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.setData('role_id', '');
                setAppId('');
            },
        });
    };

    return (
        <AppLayout title={`Reglas · ${cargo.name}`}>
            <Head title={`Reglas · ${cargo.name}`} />

            <div className="mx-auto max-w-3xl space-y-4">
                <Button variant="ghost" size="sm" asChild>
                    <Link href={route('cargos.index')}>
                        <ArrowLeft className="size-4" /> Cargos
                    </Link>
                </Button>

                <Card>
                    <CardHeader>
                        <CardTitle>Reglas de rol para "{cargo.name}"</CardTitle>
                        <CardDescription>
                            Define qué roles son permitidos o no para este cargo.
                            {cargo.generic && ` Hereda además del genérico: ${cargo.generic}.`}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        {/* Alta de regla */}
                        <form onSubmit={submit} className="grid grid-cols-1 items-end gap-3 sm:grid-cols-4">
                            <div className="space-y-1.5">
                                <Label>Aplicación</Label>
                                <Select value={appId} onValueChange={(v) => { setAppId(v); form.setData('role_id', ''); }}>
                                    <SelectTrigger><SelectValue placeholder="App…" /></SelectTrigger>
                                    <SelectContent>
                                        {aplicaciones.map((a) => (
                                            <SelectItem key={a.id} value={String(a.id)}>{a.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="regla-role">Rol</Label>
                                <Combobox
                                    id="regla-role"
                                    value={form.data.role_id}
                                    onChange={(v) => form.setData('role_id', v)}
                                    disabled={!appId}
                                    placeholder={appId ? 'Selecciona un rol…' : 'Elige la aplicación'}
                                    searchPlaceholder="Buscar rol…"
                                    options={roles.map((r) => ({ value: String(r.id), label: r.name }))}
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label>Tipo</Label>
                                <Select value={form.data.rule_type} onValueChange={(v) => form.setData('rule_type', v)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        {tipos.map((t) => (
                                            <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button type="submit" disabled={!form.data.role_id || form.processing}>
                                <Plus className="size-4" /> Agregar
                            </Button>
                        </form>
                        {form.errors.role_id && <p className="text-sm text-destructive">{form.errors.role_id}</p>}

                        {/* Tabla de reglas */}
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Rol</TableHead>
                                    <TableHead>Aplicación</TableHead>
                                    <TableHead>Tipo</TableHead>
                                    <TableHead className="w-16 text-right">—</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {reglas.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={4} className="py-8 text-center text-muted-foreground">
                                            Sin reglas definidas.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {reglas.map((r) => (
                                    <TableRow key={r.role_id}>
                                        <TableCell className="font-medium">{r.role_name}</TableCell>
                                        <TableCell className="text-muted-foreground">{r.application}</TableCell>
                                        <TableCell>
                                            <Badge variant={r.rule_type === 'permitido' ? 'success' : 'destructive'}>
                                                {r.rule_type === 'permitido' ? 'Permitido' : 'No permitido'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label="Eliminar regla"
                                                onClick={() =>
                                                    router.delete(route('reglas.destroy', [cargo.id, r.role_id]), { preserveScroll: true })
                                                }
                                            >
                                                <Trash2 className="size-4 text-destructive" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
