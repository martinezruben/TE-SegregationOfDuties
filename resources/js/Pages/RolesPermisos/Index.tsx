import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Lock, Save } from 'lucide-react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Separator } from '@/Components/ui/separator';
import { cn } from '@/lib/utils';
import { PageProps } from '@/types';

interface Role {
    id: number;
    name: string;
    protected: boolean;
    permissions: string[];
}
interface Group {
    resource: string;
    permissions: { name: string; action: string }[];
}

type Props = PageProps<{
    roles: Role[];
    groups: Group[];
    canEdit: boolean;
}>;

export default function RolesPermisosIndex({ roles, groups, canEdit }: Props) {
    const [selectedId, setSelectedId] = useState<number>(roles[0]?.id ?? 0);
    const selected = roles.find((r) => r.id === selectedId) ?? roles[0];
    const [checked, setChecked] = useState<Set<string>>(new Set(selected?.permissions ?? []));
    const [saving, setSaving] = useState(false);

    const selectRole = (r: Role) => {
        setSelectedId(r.id);
        setChecked(new Set(r.permissions));
    };

    const editable = canEdit && selected && !selected.protected;

    const toggle = (name: string) => {
        if (!editable) return;
        const next = new Set(checked);
        next.has(name) ? next.delete(name) : next.add(name);
        setChecked(next);
    };

    const save = () => {
        if (!selected) return;
        setSaving(true);
        router.put(route('roles-permisos.update', selected.id), { permissions: Array.from(checked) }, {
            preserveScroll: true,
            onFinish: () => setSaving(false),
        });
    };

    return (
        <AppLayout title="Roles y permisos">
            <Head title="Roles y permisos" />

            <div className="grid w-full gap-4 lg:grid-cols-[240px_1fr]">
                {/* Lista de roles */}
                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle className="text-base">Roles</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-1">
                        {roles.map((r) => (
                            <button
                                key={r.id}
                                onClick={() => selectRole(r)}
                                className={cn(
                                    'flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm transition-colors',
                                    r.id === selectedId ? 'bg-primary text-primary-foreground' : 'hover:bg-accent',
                                )}
                            >
                                <span>{r.name}</span>
                                {r.protected && <Lock className="size-3.5 opacity-70" />}
                            </button>
                        ))}
                    </CardContent>
                </Card>

                {/* Matriz de permisos */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0">
                        <div>
                            <CardTitle>Permisos de "{selected?.name}"</CardTitle>
                            <CardDescription>
                                {selected?.protected
                                    ? 'El rol Administrador tiene todos los permisos y no es editable.'
                                    : 'Marca los permisos que otorga este rol.'}
                            </CardDescription>
                        </div>
                        {editable && (
                            <Button onClick={save} disabled={saving}>
                                <Save className="size-4" /> Guardar
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {groups.map((g) => (
                            <div key={g.resource}>
                                <div className="mb-2 flex items-center gap-2">
                                    <span className="text-sm font-semibold capitalize">{g.resource}</span>
                                    <Badge variant="outline">{g.permissions.length}</Badge>
                                </div>
                                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
                                    {g.permissions.map((p) => (
                                        <label key={p.name} className={cn('flex items-center gap-2 text-sm', !editable && 'opacity-70')}>
                                            <input
                                                type="checkbox"
                                                className="rounded border-input"
                                                checked={selected?.protected ? true : checked.has(p.name)}
                                                disabled={!editable}
                                                onChange={() => toggle(p.name)}
                                            />
                                            {p.action}
                                        </label>
                                    ))}
                                </div>
                                <Separator className="mt-3" />
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
