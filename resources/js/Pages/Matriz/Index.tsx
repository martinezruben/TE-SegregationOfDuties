import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, RotateCcw, Search, Trash2 } from 'lucide-react';
import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';

import { Combobox } from '@/Components/app/Combobox';
import { SwitchField } from '@/Components/app/SwitchField';
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
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps } from '@/types';

interface RoleRef {
    id: number;
    name: string;
    application_id: number;
    application?: { id: number; name: string } | null;
}

interface AppWithRoles {
    id: number;
    name: string;
    roles: { id: number; application_id: number; name: string }[];
}

interface Conflict {
    id: number;
    role_a_id: number;
    role_b_id: number;
    risk_level: 'alto' | 'medio' | 'bajo';
    description: string | null;
    is_active: boolean;
    role_a: RoleRef;
    role_b: RoleRef;
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
    application_id: number | null;
    risk_level: string | null;
    status: string | null;
}

type Props = PageProps<{
    conflictos: Paginated<Conflict>;
    filters: Filters;
    aplicaciones: AppWithRoles[];
    riesgos: { value: string; label: string }[];
    can: { create: boolean; update: boolean; delete: boolean };
}>;

const ALL = 'all';
const riskVariant = (r: string) =>
    r === 'alto' ? 'destructive' : r === 'medio' ? 'warning' : 'success';

export default function MatrizIndex({
    conflictos,
    filters,
    aplicaciones,
    riesgos,
    can,
}: Props) {
    const [f, setF] = useState<Filters>(filters);
    const hasFilters = !!(
        f.search ||
        f.application_id ||
        f.risk_level ||
        f.status
    );

    const go = (next: Filters) => {
        const params: Record<string, string> = {};
        Object.entries(next).forEach(([k, v]) => {
            if (v !== null && v !== '') params[k] = String(v);
        });
        router.get(route('matriz.index'), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };
    const apply = (over: Partial<Filters>) => {
        const next = { ...f, ...over };
        setF(next);
        go(next);
    };
    // La búsqueda de texto se envía sola tras una pausa al escribir.
    const first = useRef(true);
    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const t = setTimeout(() => go(f), 350);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [f.search]);
    const reset = () => {
        const empty = {
            search: '',
            application_id: null,
            risk_level: null,
            status: null,
        };
        setF(empty);
        go(empty);
    };

    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Conflict | null>(null);
    const [deleting, setDeleting] = useState<Conflict | null>(null);

    const [appA, setAppA] = useState<string>('');
    const [appB, setAppB] = useState<string>('');

    const rolesA = useMemo(
        () => aplicaciones.find((a) => String(a.id) === appA)?.roles ?? [],
        [appA, aplicaciones],
    );
    const rolesB = useMemo(
        () => aplicaciones.find((a) => String(a.id) === appB)?.roles ?? [],
        [appB, aplicaciones],
    );

    const form = useForm<{
        role_a_id: string;
        role_b_id: string;
        risk_level: string;
        description: string;
        is_active: boolean;
    }>({
        role_a_id: '',
        role_b_id: '',
        risk_level: 'alto',
        description: '',
        is_active: true,
    });

    const openCreate = () => {
        setEditing(null);
        setAppA('');
        setAppB('');
        form.reset();
        form.clearErrors();
        setFormOpen(true);
    };

    const openEdit = (c: Conflict) => {
        setEditing(c);
        setAppA(String(c.role_a.application_id));
        setAppB(String(c.role_b.application_id));
        form.setData({
            role_a_id: String(c.role_a_id),
            role_b_id: String(c.role_b_id),
            risk_level: c.risk_level,
            description: c.description ?? '',
            is_active: c.is_active,
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
        if (editing) {
            form.put(route('matriz.update', editing.id), opts);
        } else {
            form.post(route('matriz.store'), opts);
        }
    };

    return (
        <AppLayout title="Matriz de conflictos SoD">
            <Head title="Matriz SoD" />

            <Card className="w-full">
                <CardHeader className="flex flex-row items-center justify-between gap-4 space-y-0">
                    <div>
                        <CardTitle>Matriz de conflictos SoD</CardTitle>
                        <CardDescription>
                            Pares de roles que no deben coexistir en un mismo
                            usuario.
                        </CardDescription>
                    </div>
                    {can.create && (
                        <Button onClick={openCreate}>
                            <Plus className="size-4" /> Nuevo conflicto
                        </Button>
                    )}
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid grid-cols-1 gap-3 rounded-lg border bg-muted/20 p-3 sm:grid-cols-2 lg:grid-cols-5">
                        <div className="space-y-1.5 lg:col-span-2">
                            <Label htmlFor="matriz-search" className="text-xs">
                                Buscar (rol, aplicación o razón)
                            </Label>
                            <div className="relative">
                                <Search className="pointer-events-none absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                                <Input
                                    id="matriz-search"
                                    className="pl-8"
                                    value={f.search}
                                    onChange={(e) =>
                                        setF((p) => ({
                                            ...p,
                                            search: e.target.value,
                                        }))
                                    }
                                    placeholder="Escribe para filtrar…"
                                />
                            </div>
                        </div>
                        <div className="space-y-1.5">
                            <Label className="text-xs">
                                Aplicación (cualquiera de los dos roles)
                            </Label>
                            <Select
                                value={
                                    f.application_id
                                        ? String(f.application_id)
                                        : ALL
                                }
                                onValueChange={(v) =>
                                    apply({
                                        application_id:
                                            v === ALL ? null : Number(v),
                                    })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>Todas</SelectItem>
                                    {aplicaciones.map((a) => (
                                        <SelectItem
                                            key={a.id}
                                            value={String(a.id)}
                                        >
                                            {a.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label className="text-xs">Riesgo</Label>
                            <Select
                                value={f.risk_level ?? ALL}
                                onValueChange={(v) =>
                                    apply({ risk_level: v === ALL ? null : v })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>Todos</SelectItem>
                                    {riesgos.map((r) => (
                                        <SelectItem
                                            key={r.value}
                                            value={r.value}
                                        >
                                            {r.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label className="text-xs">Estado</Label>
                            <div className="flex gap-2">
                                <Select
                                    value={f.status ?? ALL}
                                    onValueChange={(v) =>
                                        apply({ status: v === ALL ? null : v })
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ALL}>
                                            Todos
                                        </SelectItem>
                                        <SelectItem value="activo">
                                            Activo
                                        </SelectItem>
                                        <SelectItem value="inactivo">
                                            Inactivo
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                {hasFilters && (
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        onClick={reset}
                                        aria-label="Limpiar filtros"
                                        title="Limpiar filtros"
                                    >
                                        <RotateCcw className="size-4" />
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>

                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Rol A</TableHead>
                                <TableHead>Rol B</TableHead>
                                <TableHead>Riesgo</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead className="w-24 text-right">
                                    Acciones
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {conflictos.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="py-8 text-center text-muted-foreground"
                                    >
                                        {hasFilters
                                            ? 'Ningún conflicto coincide con los filtros.'
                                            : 'Aún no hay conflictos definidos.'}
                                    </TableCell>
                                </TableRow>
                            )}
                            {conflictos.data.map((c) => (
                                <TableRow key={c.id}>
                                    <TableCell>
                                        <div className="font-medium">
                                            {c.role_a.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {c.role_a.application?.name}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div className="font-medium">
                                            {c.role_b.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {c.role_b.application?.name}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={riskVariant(c.risk_level)}
                                        >
                                            {c.risk_level}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                c.is_active
                                                    ? 'success'
                                                    : 'secondary'
                                            }
                                        >
                                            {c.is_active
                                                ? 'Activo'
                                                : 'Inactivo'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-1">
                                            {can.update && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => openEdit(c)}
                                                    aria-label="Editar"
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                            )}
                                            {can.delete && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        setDeleting(c)
                                                    }
                                                    aria-label="Eliminar"
                                                >
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
                        <span>{conflictos.total} registro(s)</span>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!conflictos.prev_page_url}
                                onClick={() =>
                                    conflictos.prev_page_url &&
                                    router.get(
                                        conflictos.prev_page_url,
                                        {},
                                        { preserveState: true },
                                    )
                                }
                            >
                                Anterior
                            </Button>
                            <span>
                                Página {conflictos.current_page} de{' '}
                                {conflictos.last_page}
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!conflictos.next_page_url}
                                onClick={() =>
                                    conflictos.next_page_url &&
                                    router.get(
                                        conflictos.next_page_url,
                                        {},
                                        { preserveState: true },
                                    )
                                }
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
                            <DialogTitle>
                                {editing
                                    ? 'Editar conflicto'
                                    : 'Nuevo conflicto'}
                            </DialogTitle>
                            <DialogDescription>
                                Selecciona los dos roles incompatibles y el
                                nivel de riesgo.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4 py-4">
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1.5">
                                    <Label htmlFor="matriz-app-a">
                                        Aplicación A
                                    </Label>
                                    <Select
                                        value={appA}
                                        onValueChange={(v) => {
                                            setAppA(v);
                                            form.setData('role_a_id', '');
                                        }}
                                    >
                                        <SelectTrigger id="matriz-app-a">
                                            <SelectValue placeholder="Aplicación…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {aplicaciones.map((a) => (
                                                <SelectItem
                                                    key={a.id}
                                                    value={String(a.id)}
                                                >
                                                    {a.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="matriz-role-a">Rol A</Label>
                                    <Combobox
                                        id="matriz-role-a"
                                        value={form.data.role_a_id}
                                        onChange={(v) =>
                                            form.setData('role_a_id', v)
                                        }
                                        disabled={!appA}
                                        placeholder={
                                            appA
                                                ? 'Selecciona un rol…'
                                                : 'Elige la aplicación'
                                        }
                                        searchPlaceholder="Buscar rol…"
                                        options={rolesA.map((r) => ({
                                            value: String(r.id),
                                            label: r.name,
                                        }))}
                                    />
                                </div>
                            </div>
                            {form.errors.role_a_id && (
                                <p className="text-sm text-destructive">
                                    {form.errors.role_a_id}
                                </p>
                            )}

                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1.5">
                                    <Label htmlFor="matriz-app-b">
                                        Aplicación B
                                    </Label>
                                    <Select
                                        value={appB}
                                        onValueChange={(v) => {
                                            setAppB(v);
                                            form.setData('role_b_id', '');
                                        }}
                                    >
                                        <SelectTrigger id="matriz-app-b">
                                            <SelectValue placeholder="Aplicación…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {aplicaciones.map((a) => (
                                                <SelectItem
                                                    key={a.id}
                                                    value={String(a.id)}
                                                >
                                                    {a.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="matriz-role-b">Rol B</Label>
                                    <Combobox
                                        id="matriz-role-b"
                                        value={form.data.role_b_id}
                                        onChange={(v) =>
                                            form.setData('role_b_id', v)
                                        }
                                        disabled={!appB}
                                        placeholder={
                                            appB
                                                ? 'Selecciona un rol…'
                                                : 'Elige la aplicación'
                                        }
                                        searchPlaceholder="Buscar rol…"
                                        options={rolesB.map((r) => ({
                                            value: String(r.id),
                                            label: r.name,
                                        }))}
                                    />
                                </div>
                            </div>
                            {form.errors.role_b_id && (
                                <p className="text-sm text-destructive">
                                    {form.errors.role_b_id}
                                </p>
                            )}

                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1.5">
                                    <Label>Nivel de riesgo</Label>
                                    <Select
                                        value={form.data.risk_level}
                                        onValueChange={(v) =>
                                            form.setData('risk_level', v)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {riesgos.map((r) => (
                                                <SelectItem
                                                    key={r.value}
                                                    value={r.value}
                                                >
                                                    {r.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <SwitchField
                                    id="matriz-active"
                                    label="Activo"
                                    className="mt-6"
                                    checked={form.data.is_active}
                                    onCheckedChange={(v) =>
                                        form.setData('is_active', v)
                                    }
                                />
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="desc">
                                    Razón del conflicto
                                </Label>
                                <Input
                                    id="desc"
                                    value={form.data.description}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setFormOpen(false)}
                            >
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {editing ? 'Guardar' : 'Crear'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <AlertDialog
                open={!!deleting}
                onOpenChange={(o) => !o && setDeleting(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            ¿Eliminar este conflicto?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {deleting?.role_a.name} ⇄ {deleting?.role_b.name}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() =>
                                deleting &&
                                router.delete(
                                    route('matriz.destroy', deleting.id),
                                    {
                                        preserveScroll: true,
                                        onFinish: () => setDeleting(null),
                                    },
                                )
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
