import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ChevronRight,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    Trash2,
    UserCheck,
    X,
} from 'lucide-react';
import { FormEvent, Fragment, useEffect, useRef, useState } from 'react';

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
    is_active: boolean;
    steps: Step[];
}
interface Step {
    authorizer_type: 'fixed' | 'supervisor';
    authorizer_id: number | null;
    authorizer: string | null;
}
interface Filters {
    search: string;
    trigger_type: string | null;
    risk_level: string | null;
    application_id: number | null;
    authorizer: string | null;
    status: string | null;
}
interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}
interface StepForm {
    authorizer_type: 'fixed' | 'supervisor';
    authorizer_id: string;
}

type Props = PageProps<{
    reglas: Paginated<Regla>;
    filters: Filters;
    aplicaciones: Option[];
    agentes: Option[];
    can: { create: boolean; update: boolean; delete: boolean };
}>;

const NONE = 'none';
const ALL = 'all';
const emptyStep = (): StepForm => ({
    authorizer_type: 'fixed',
    authorizer_id: '',
});

const triggerLabel: Record<string, string> = {
    sod_risk: 'Criticidad de la matriz (SoD)',
    application: 'Por aplicación',
    cargo_misalignment: 'Desalineación con el cargo',
};

export default function AutorizacionesIndex({
    reglas,
    filters,
    aplicaciones,
    agentes,
    can,
}: Props) {
    const [f, setF] = useState<Filters>(filters);
    const hasFilters = !!(
        f.search ||
        f.trigger_type ||
        f.risk_level ||
        f.application_id ||
        f.authorizer ||
        f.status
    );

    const go = (next: Filters) => {
        const params: Record<string, string> = {};
        Object.entries(next).forEach(([k, v]) => {
            if (v !== null && v !== '') params[k] = String(v);
        });
        router.get(route('autorizaciones.index'), params, {
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
        const empty: Filters = {
            search: '',
            trigger_type: null,
            risk_level: null,
            application_id: null,
            authorizer: null,
            status: null,
        };
        setF(empty);
        go(empty);
    };

    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Regla | null>(null);
    const [deleting, setDeleting] = useState<Regla | null>(null);

    const form = useForm<{
        name: string;
        trigger_type: 'sod_risk' | 'application' | 'cargo_misalignment';
        risk_level: string;
        application_id: string;
        steps: StepForm[];
        is_active: boolean;
    }>({
        name: '',
        trigger_type: 'sod_risk',
        risk_level: 'alto',
        application_id: NONE,
        steps: [emptyStep()],
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
            steps: r.steps.length
                ? r.steps.map((st) => ({
                      authorizer_type: st.authorizer_type,
                      authorizer_id: st.authorizer_id
                          ? String(st.authorizer_id)
                          : '',
                  }))
                : [emptyStep()],
            is_active: r.is_active,
        });
        form.clearErrors();
        setFormOpen(true);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            risk_level:
                data.trigger_type === 'sod_risk' ? data.risk_level : null,
            application_id:
                data.application_id === NONE ? '' : data.application_id,
            steps: data.steps.map((st) => ({
                authorizer_type: st.authorizer_type,
                authorizer_id:
                    st.authorizer_type === 'supervisor'
                        ? null
                        : st.authorizer_id,
            })),
        }));
        const opts = {
            preserveScroll: true,
            onSuccess: () => setFormOpen(false),
        };
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

    const setStep = (i: number, patch: Partial<StepForm>) =>
        form.setData(
            'steps',
            form.data.steps.map((st, j) =>
                j === i ? { ...st, ...patch } : st,
            ),
        );
    const addStep = () =>
        form.setData('steps', [...form.data.steps, emptyStep()]);
    const removeStep = (i: number) =>
        form.setData(
            'steps',
            form.data.steps.filter((_, j) => j !== i),
        );
    const moveStep = (i: number, dir: -1 | 1) => {
        const steps = [...form.data.steps];
        [steps[i], steps[i + dir]] = [steps[i + dir], steps[i]];
        form.setData('steps', steps);
    };
    const stepError = (i: number) =>
        (form.errors as Record<string, string>)[`steps.${i}.authorizer_id`];

    return (
        <AppLayout title="Matriz de autorizaciones">
            <Head title="Autorizadores" />

            <Card className="w-full">
                <CardHeader className="flex flex-row items-center justify-between gap-4 space-y-0">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            <UserCheck className="size-5" /> Matriz de
                            autorizaciones
                        </CardTitle>
                        <CardDescription>
                            Define quién autoriza según el disparador de la
                            solicitud. Una regla puede exigir varias
                            autorizaciones en secuencia; un mismo autorizador
                            que cubra varios motivos aprueba una sola vez.
                        </CardDescription>
                    </div>
                    {can.create && (
                        <Button onClick={openCreate}>
                            <Plus className="size-4" /> Nueva regla
                        </Button>
                    )}
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid grid-cols-1 gap-3 rounded-lg border bg-muted/20 p-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="space-y-1.5">
                            <Label htmlFor="aut-search" className="text-xs">
                                Buscar por nombre
                            </Label>
                            <div className="relative">
                                <Search className="pointer-events-none absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                                <Input
                                    id="aut-search"
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
                            <Label className="text-xs">Disparador</Label>
                            <Select
                                value={f.trigger_type ?? ALL}
                                onValueChange={(v) =>
                                    apply({
                                        trigger_type: v === ALL ? null : v,
                                        risk_level:
                                            v === 'sod_risk'
                                                ? f.risk_level
                                                : null,
                                    })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>Todos</SelectItem>
                                    {Object.entries(triggerLabel).map(
                                        ([k, l]) => (
                                            <SelectItem key={k} value={k}>
                                                {l}
                                            </SelectItem>
                                        ),
                                    )}
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
                                    <SelectItem value="alto">Alto</SelectItem>
                                    <SelectItem value="medio">Medio</SelectItem>
                                    <SelectItem value="bajo">Bajo</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label className="text-xs">Aplicación</Label>
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
                        <div className="space-y-1.5 sm:col-span-2">
                            <Label htmlFor="aut-authorizer" className="text-xs">
                                Autorizador (en cualquier paso)
                            </Label>
                            <Combobox
                                id="aut-authorizer"
                                value={f.authorizer ?? ''}
                                onChange={(v) =>
                                    apply({ authorizer: v || null })
                                }
                                placeholder="Todos"
                                clearLabel="Todos"
                                searchPlaceholder="Buscar agente…"
                                options={[
                                    {
                                        value: 'supervisor',
                                        label: 'Su supervisor (jefe)',
                                    },
                                    ...agentes.map((a) => ({
                                        value: String(a.id),
                                        label: a.name,
                                    })),
                                ]}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label className="text-xs">Estado</Label>
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
                                    <SelectItem value={ALL}>Todos</SelectItem>
                                    <SelectItem value="activa">
                                        Activa
                                    </SelectItem>
                                    <SelectItem value="inactiva">
                                        Inactiva
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="flex items-end">
                            <Button
                                variant="outline"
                                onClick={reset}
                                disabled={!hasFilters}
                            >
                                <RotateCcw className="size-4" /> Limpiar filtros
                            </Button>
                        </div>
                    </div>

                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nombre</TableHead>
                                <TableHead>Disparador</TableHead>
                                <TableHead>Detalle</TableHead>
                                <TableHead>Aplicación</TableHead>
                                <TableHead>Autorizaciones (en orden)</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead className="w-24 text-right">
                                    Acciones
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {reglas.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={7}
                                        className="py-8 text-center text-muted-foreground"
                                    >
                                        {hasFilters
                                            ? 'Ninguna regla coincide con los filtros.'
                                            : 'No hay reglas. Si no defines ninguna, se usa el flujo por defecto (owner de aplicación y/o seguridad).'}
                                    </TableCell>
                                </TableRow>
                            )}
                            {reglas.data.map((r) => (
                                <TableRow key={r.id}>
                                    <TableCell className="font-medium">
                                        {r.name}
                                    </TableCell>
                                    <TableCell>
                                        {triggerLabel[r.trigger_type]}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {r.trigger_type === 'sod_risk' &&
                                        r.risk_level ? (
                                            <Badge
                                                variant={
                                                    r.risk_level === 'alto'
                                                        ? 'destructive'
                                                        : r.risk_level ===
                                                            'medio'
                                                          ? 'warning'
                                                          : 'success'
                                                }
                                            >
                                                riesgo {r.risk_level}
                                            </Badge>
                                        ) : (
                                            '—'
                                        )}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {r.application ?? 'Todas'}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-wrap items-center gap-1">
                                            {r.steps.map((st, i) => (
                                                <Fragment key={i}>
                                                    {i > 0 && (
                                                        <ChevronRight
                                                            className="size-3.5 text-muted-foreground"
                                                            aria-label="luego"
                                                        />
                                                    )}
                                                    <Badge
                                                        variant={
                                                            st.authorizer_type ===
                                                            'supervisor'
                                                                ? 'info'
                                                                : st.authorizer
                                                                  ? 'secondary'
                                                                  : 'warning'
                                                        }
                                                    >
                                                        {r.steps.length > 1 &&
                                                            `${i + 1}. `}
                                                        {st.authorizer_type ===
                                                        'supervisor'
                                                            ? 'Su supervisor'
                                                            : (st.authorizer ??
                                                              'Sin asignar')}
                                                    </Badge>
                                                </Fragment>
                                            ))}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                r.is_active
                                                    ? 'success'
                                                    : 'secondary'
                                            }
                                        >
                                            {r.is_active
                                                ? 'Activa'
                                                : 'Inactiva'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-1">
                                            {can.update && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => openEdit(r)}
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
                                                        setDeleting(r)
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
                        <span>{reglas.total} regla(s)</span>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!reglas.prev_page_url}
                                onClick={() =>
                                    reglas.prev_page_url &&
                                    router.get(
                                        reglas.prev_page_url,
                                        {},
                                        { preserveState: true },
                                    )
                                }
                            >
                                Anterior
                            </Button>
                            <span>
                                Página {reglas.current_page} de{' '}
                                {reglas.last_page}
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!reglas.next_page_url}
                                onClick={() =>
                                    reglas.next_page_url &&
                                    router.get(
                                        reglas.next_page_url,
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
                                    ? 'Editar regla'
                                    : 'Nueva regla de autorización'}
                            </DialogTitle>
                            <DialogDescription>
                                Disparador y autorizador que aplican a una
                                solicitud.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid grid-cols-1 gap-4 py-4 sm:grid-cols-2">
                            <div className="space-y-1.5 sm:col-span-2">
                                <Label htmlFor="name">Nombre</Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    autoFocus
                                />
                                {form.errors.name && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.name}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-1.5">
                                <Label>Disparador</Label>
                                <Select
                                    value={form.data.trigger_type}
                                    onValueChange={(v) =>
                                        form.setData(
                                            'trigger_type',
                                            v as Regla['trigger_type'],
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="sod_risk">
                                            Criticidad de la matriz (SoD)
                                        </SelectItem>
                                        <SelectItem value="application">
                                            Por aplicación
                                        </SelectItem>
                                        <SelectItem value="cargo_misalignment">
                                            Desalineación con el cargo
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                {form.errors.trigger_type && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.trigger_type}
                                    </p>
                                )}
                            </div>

                            {form.data.trigger_type === 'sod_risk' && (
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
                                            <SelectItem value="alto">
                                                Alto
                                            </SelectItem>
                                            <SelectItem value="medio">
                                                Medio
                                            </SelectItem>
                                            <SelectItem value="bajo">
                                                Bajo
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {form.errors.risk_level && (
                                        <p className="text-sm text-destructive">
                                            {form.errors.risk_level}
                                        </p>
                                    )}
                                </div>
                            )}

                            <div className="space-y-1.5 sm:col-span-2">
                                <Label>
                                    Aplicación{' '}
                                    {form.data.trigger_type === 'application'
                                        ? '(requerida)'
                                        : '(opcional — limita el alcance)'}
                                </Label>
                                <Select
                                    value={form.data.application_id}
                                    onValueChange={(v) =>
                                        form.setData('application_id', v)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Todas las aplicaciones" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {form.data.trigger_type !==
                                            'application' && (
                                            <SelectItem value={NONE}>
                                                Todas las aplicaciones
                                            </SelectItem>
                                        )}
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
                                {form.errors.application_id && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.application_id}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2 sm:col-span-2">
                                <div className="flex items-center justify-between">
                                    <Label>Autorizaciones en secuencia</Label>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={addStep}
                                        disabled={form.data.steps.length >= 5}
                                    >
                                        <Plus className="size-4" /> Agregar paso
                                    </Button>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Cada paso se activa cuando el anterior fue
                                    aprobado. Si alguien rechaza, la solicitud
                                    termina.
                                </p>
                                {form.data.steps.map((st, i) => (
                                    <div
                                        key={i}
                                        className="rounded-lg border bg-muted/20 p-3"
                                    >
                                        <div className="flex items-center gap-2">
                                            <Badge
                                                variant="outline"
                                                className="shrink-0"
                                            >
                                                Paso {i + 1}
                                            </Badge>
                                            <Select
                                                value={st.authorizer_type}
                                                onValueChange={(v) =>
                                                    setStep(i, {
                                                        authorizer_type:
                                                            v as StepForm['authorizer_type'],
                                                    })
                                                }
                                            >
                                                <SelectTrigger
                                                    aria-label={`Tipo de autorizador del paso ${i + 1}`}
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="fixed">
                                                        Persona fija
                                                    </SelectItem>
                                                    <SelectItem value="supervisor">
                                                        Su supervisor (jefe)
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                disabled={i === 0}
                                                onClick={() => moveStep(i, -1)}
                                                aria-label="Subir paso"
                                            >
                                                <ArrowUp className="size-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                disabled={
                                                    i ===
                                                    form.data.steps.length - 1
                                                }
                                                onClick={() => moveStep(i, 1)}
                                                aria-label="Bajar paso"
                                            >
                                                <ArrowDown className="size-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                disabled={
                                                    form.data.steps.length === 1
                                                }
                                                onClick={() => removeStep(i)}
                                                aria-label="Quitar paso"
                                            >
                                                <X className="size-4" />
                                            </Button>
                                        </div>
                                        {st.authorizer_type === 'fixed' && (
                                            <div className="mt-2">
                                                <Combobox
                                                    id={`autorizaciones-authorizer-${i}`}
                                                    value={st.authorizer_id}
                                                    onChange={(v) =>
                                                        setStep(i, {
                                                            authorizer_id: v,
                                                        })
                                                    }
                                                    placeholder="Selecciona el autorizador…"
                                                    searchPlaceholder="Buscar agente…"
                                                    options={agentes.map(
                                                        (a) => ({
                                                            value: String(a.id),
                                                            label: a.name,
                                                        }),
                                                    )}
                                                />
                                            </div>
                                        )}
                                        {stepError(i) && (
                                            <p className="mt-1 text-sm text-destructive">
                                                {stepError(i)}
                                            </p>
                                        )}
                                    </div>
                                ))}
                                {form.errors.steps && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.steps}
                                    </p>
                                )}
                            </div>

                            <SwitchField
                                id="autorizaciones-is_active"
                                label="Activa"
                                className="sm:col-span-2"
                                checked={form.data.is_active}
                                onCheckedChange={(v) =>
                                    form.setData('is_active', v)
                                }
                            />
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
                                {editing ? 'Guardar cambios' : 'Crear'}
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
                            ¿Eliminar la regla "{deleting?.name}"?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Dejará de aplicarse a nuevas solicitudes.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction onClick={confirmDelete}>
                            Eliminar
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
