import { Head, router } from '@inertiajs/react';

import AppLayout from '@/Layouts/AppLayout';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { PageProps } from '@/types';

interface Solicitud {
    id: number;
    user: string;
    igg: string;
    role: string;
    application: string;
    type: string;
    status: string;
    status_label: string;
    status_variant: string;
    has_sod_conflict: boolean;
    requester: string;
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

type Props = PageProps<{ solicitudes: Paginated<Solicitud> }>;

export default function SolicitudesIndex({ solicitudes }: Props) {
    return (
        <AppLayout title="Solicitudes">
            <Head title="Solicitudes" />

            <Card className="mx-auto max-w-6xl">
                <CardHeader>
                    <CardTitle>Solicitudes de acceso</CardTitle>
                    <CardDescription>Historial y estado de las solicitudes de asignación de roles.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>#</TableHead>
                                <TableHead>Usuario</TableHead>
                                <TableHead>Rol</TableHead>
                                <TableHead>Aplicación</TableHead>
                                <TableHead>SoD</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead>Solicitante</TableHead>
                                <TableHead>Fecha</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {solicitudes.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={8} className="py-8 text-center text-muted-foreground">
                                        Aún no hay solicitudes.
                                    </TableCell>
                                </TableRow>
                            )}
                            {solicitudes.data.map((s) => (
                                <TableRow key={s.id}>
                                    <TableCell className="text-muted-foreground">{s.id}</TableCell>
                                    <TableCell>
                                        <div className="font-medium">{s.user}</div>
                                        <div className="font-mono text-xs text-muted-foreground">{s.igg}</div>
                                    </TableCell>
                                    <TableCell>{s.role}</TableCell>
                                    <TableCell className="text-muted-foreground">{s.application}</TableCell>
                                    <TableCell>
                                        {s.has_sod_conflict ? (
                                            <Badge variant="destructive">Conflicto</Badge>
                                        ) : (
                                            <span className="text-muted-foreground">—</span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={s.status_variant as never}>{s.status_label}</Badge>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">{s.requester}</TableCell>
                                    <TableCell className="text-xs text-muted-foreground">{s.created_at}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>

                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>{solicitudes.total} registro(s)</span>
                        <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" disabled={!solicitudes.prev_page_url} onClick={() => solicitudes.prev_page_url && router.get(solicitudes.prev_page_url, {}, { preserveState: true })}>
                                Anterior
                            </Button>
                            <span>Página {solicitudes.current_page} de {solicitudes.last_page}</span>
                            <Button variant="outline" size="sm" disabled={!solicitudes.next_page_url} onClick={() => solicitudes.next_page_url && router.get(solicitudes.next_page_url, {}, { preserveState: true })}>
                                Siguiente
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
