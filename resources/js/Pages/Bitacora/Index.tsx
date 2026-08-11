import { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';

import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { PageProps } from '@/types';

interface AuditRow {
    id: number;
    event: string;
    entity: string;
    entity_id: number | null;
    user: string;
    ip: string | null;
    changed: string;
    created_at: string;
}
interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

type Props = PageProps<{ audits: Paginated<AuditRow>; filters: { search: string } }>;

const eventVariant = (e: string) => (e === 'created' ? 'success' : e === 'deleted' ? 'destructive' : e === 'updated' ? 'info' : 'secondary');

export default function BitacoraIndex({ audits, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    useEffect(() => {
        const t = setTimeout(() => {
            if (search === (filters.search ?? '')) return;
            router.get(route('bitacora.index'), { search }, { preserveState: true, replace: true });
        }, 300);
        return () => clearTimeout(t);
    }, [search]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <AppLayout title="Bitácora">
            <Head title="Bitácora" />

            <Card className="w-full">
                <CardHeader>
                    <CardTitle>Bitácora de auditoría</CardTitle>
                    <CardDescription>Registro inmutable de cambios en el sistema.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="relative max-w-sm">
                        <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                        <Input placeholder="Buscar por evento o entidad…" className="pl-8" value={search} onChange={(e) => setSearch(e.target.value)} />
                    </div>

                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Fecha</TableHead>
                                <TableHead>Usuario</TableHead>
                                <TableHead>Evento</TableHead>
                                <TableHead>Entidad</TableHead>
                                <TableHead>Campos</TableHead>
                                <TableHead>IP</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {audits.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={6} className="py-8 text-center text-muted-foreground">Sin registros.</TableCell>
                                </TableRow>
                            )}
                            {audits.data.map((a) => (
                                <TableRow key={a.id}>
                                    <TableCell className="whitespace-nowrap text-xs text-muted-foreground">{a.created_at}</TableCell>
                                    <TableCell>{a.user}</TableCell>
                                    <TableCell><Badge variant={eventVariant(a.event) as never}>{a.event}</Badge></TableCell>
                                    <TableCell>
                                        {a.entity} <span className="text-xs text-muted-foreground">#{a.entity_id}</span>
                                    </TableCell>
                                    <TableCell className="max-w-xs truncate text-xs text-muted-foreground">{a.changed}</TableCell>
                                    <TableCell className="text-xs text-muted-foreground">{a.ip}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>

                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>{audits.total} registro(s)</span>
                        <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" disabled={!audits.prev_page_url} onClick={() => audits.prev_page_url && router.get(audits.prev_page_url, {}, { preserveState: true })}>Anterior</Button>
                            <span>Página {audits.current_page} de {audits.last_page}</span>
                            <Button variant="outline" size="sm" disabled={!audits.next_page_url} onClick={() => audits.next_page_url && router.get(audits.next_page_url, {}, { preserveState: true })}>Siguiente</Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
