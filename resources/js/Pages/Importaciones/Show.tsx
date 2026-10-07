import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Download } from 'lucide-react';
import { useState } from 'react';

import AppLayout from '@/Layouts/AppLayout';
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
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

type Status = 'crear' | 'actualizar' | 'sin_cambios' | 'error';
interface Row {
    line: number;
    status: Status;
    label: string;
    message: string | null;
}
interface Lote {
    id: number;
    kind: string;
    label: string;
    filename: string;
    status: 'preview' | 'applied';
    total_rows: number;
    summary: Record<Status, number>;
    preview: Row[];
    errors: Row[];
    result: { creados: number; actualizados: number; sin_cambios: number; errores: number } | null;
    applied_at: string | null;
    user: string | null;
    preview_limit: number;
}

const statusMeta: Record<Status, { label: string; variant: 'success' | 'info' | 'secondary' | 'destructive' }> = {
    crear: { label: 'Crear', variant: 'success' },
    actualizar: { label: 'Actualizar', variant: 'info' },
    sin_cambios: { label: 'Sin cambios', variant: 'secondary' },
    error: { label: 'Error', variant: 'destructive' },
};

export default function Show({ lote, canApply }: { lote: Lote; canApply: boolean }) {
    const [confirm, setConfirm] = useState(false);
    const [busy, setBusy] = useState(false);
    const applied = lote.status === 'applied';
    const changes = lote.summary.crear + lote.summary.actualizar;

    const apply = () => {
        setBusy(true);
        router.post(route('importaciones.apply', lote.id), {}, {
            onFinish: () => {
                setBusy(false);
                setConfirm(false);
            },
        });
    };

    return (
        <AppLayout title={`Importación #${lote.id}`}>
            <Head title={`Importación #${lote.id}`} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link href={route('importaciones.index')} className="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                        <ArrowLeft className="size-4" /> Volver
                    </Link>
                    <div className="text-sm text-muted-foreground">
                        {lote.label} · {lote.filename} · {lote.total_rows} fila(s) · {lote.user}
                    </div>
                </div>

                {applied ? (
                    <Alert>
                        <CheckCircle2 className="size-4" />
                        <AlertTitle>Importación aplicada el {lote.applied_at}</AlertTitle>
                        <AlertDescription>
                            {lote.result?.creados ?? 0} creados · {lote.result?.actualizados ?? 0} actualizados ·{' '}
                            {lote.result?.sin_cambios ?? 0} sin cambios · {lote.result?.errores ?? 0} con error (omitidos).
                        </AlertDescription>
                    </Alert>
                ) : (
                    <Alert>
                        <AlertTitle>Vista previa: todavía no se guardó nada</AlertTitle>
                        <AlertDescription>
                            Revisa el resultado. Al confirmar se aplicarán solo las filas válidas; las filas con error se omiten.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    {(Object.keys(statusMeta) as Status[]).map((s) => (
                        <Card key={s}>
                            <CardHeader className="pb-2">
                                <CardDescription>{statusMeta[s].label}</CardDescription>
                                <CardTitle className="text-3xl">{lote.summary[s]}</CardTitle>
                            </CardHeader>
                        </Card>
                    ))}
                </div>

                <Tabs defaultValue={lote.errors.length > 0 ? 'errores' : 'detalle'}>
                    <TabsList>
                        <TabsTrigger value="detalle">Detalle ({lote.total_rows})</TabsTrigger>
                        <TabsTrigger value="errores">Errores ({lote.summary.error})</TabsTrigger>
                    </TabsList>

                    <TabsContent value="errores">
                        <Card>
                            <CardHeader className="flex-row items-center justify-between space-y-0">
                                <div>
                                    <CardTitle>Errores ({lote.summary.error})</CardTitle>
                                    <CardDescription>Corrige estas filas en el archivo y vuelve a subirlo.</CardDescription>
                                </div>
                                {lote.errors.length > 0 && (
                                    <Button variant="outline" size="sm" asChild>
                                        <a href={route('importaciones.errors', lote.id)}>
                                            <Download className="size-4" /> Descargar CSV
                                        </a>
                                    </Button>
                                )}
                            </CardHeader>
                            <CardContent>
                                {lote.errors.length === 0 ? (
                                    <p className="py-6 text-center text-sm text-muted-foreground">No hay filas con error. 🎉</p>
                                ) : (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead className="w-20">Fila</TableHead>
                                                <TableHead>Registro</TableHead>
                                                <TableHead>Error</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {lote.errors.map((e) => (
                                                <TableRow key={e.line}>
                                                    <TableCell>{e.line}</TableCell>
                                                    <TableCell>{e.label}</TableCell>
                                                    <TableCell className="text-destructive">{e.message}</TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="detalle">
                        <Card>
                            <CardHeader>
                                <CardTitle>Detalle</CardTitle>
                                <CardDescription>
                                    {lote.total_rows > lote.preview_limit
                                        ? `Primeras ${lote.preview_limit} de ${lote.total_rows} filas.`
                                        : `${lote.total_rows} fila(s).`}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-20">Fila</TableHead>
                                            <TableHead>Registro</TableHead>
                                            <TableHead>Acción</TableHead>
                                            <TableHead>Nota</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {lote.preview.map((r) => (
                                            <TableRow key={r.line}>
                                                <TableCell>{r.line}</TableCell>
                                                <TableCell>{r.label}</TableCell>
                                                <TableCell>
                                                    <Badge variant={statusMeta[r.status].variant}>{statusMeta[r.status].label}</Badge>
                                                </TableCell>
                                                <TableCell className="text-xs text-muted-foreground">{r.message}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>

                {canApply && (
                    <div className="flex justify-end">
                        <Button disabled={changes === 0} onClick={() => setConfirm(true)}>
                            Confirmar y aplicar ({changes})
                        </Button>
                    </div>
                )}
            </div>

            <AlertDialog open={confirm} onOpenChange={setConfirm}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>¿Aplicar la importación?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Se crearán {lote.summary.crear} y se actualizarán {lote.summary.actualizar} registro(s).
                            {lote.summary.error > 0 && ` ${lote.summary.error} fila(s) con error se omitirán.`} Esta acción
                            queda registrada en la bitácora.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={busy}>Cancelar</AlertDialogCancel>
                        <AlertDialogAction disabled={busy} onClick={(e) => { e.preventDefault(); apply(); }}>
                            {busy ? 'Aplicando…' : 'Aplicar'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
