import { FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Download, Upload } from 'lucide-react';

import AppLayout from '@/Layouts/AppLayout';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

interface Tipo {
    kind: string;
    label: string;
    columns: string[];
    allowed: boolean;
}
interface Lote {
    id: number;
    kind: string;
    filename: string;
    status: 'preview' | 'applied';
    total_rows: number;
    user: string | null;
    created_at: string;
}

export default function Index({ tipos, lotes }: { tipos: Tipo[]; lotes: Lote[] }) {
    const allowed = tipos.filter((t) => t.allowed);
    const form = useForm<{ kind: string; file: File | null }>({ kind: allowed[0]?.kind ?? '', file: null });
    const tipo = tipos.find((t) => t.kind === form.data.kind);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('importaciones.preview'), { forceFormData: true });
    };

    return (
        <AppLayout title="Importar datos">
            <Head title="Importar datos" />

            <div className="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Nueva importación</CardTitle>
                        <CardDescription>
                            Sube un CSV o XLSX. Primero se analiza <strong>sin guardar nada</strong>; podrás revisar los
                            resultados y confirmar.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {allowed.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No tienes permiso para importar datos.</p>
                        ) : (
                            <form onSubmit={submit} className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="kind">Tipo de datos</Label>
                                    <Select value={form.data.kind} onValueChange={(v) => form.setData('kind', v)}>
                                        <SelectTrigger id="kind">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {allowed.map((t) => (
                                                <SelectItem key={t.kind} value={t.kind}>
                                                    {t.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {tipo && (
                                        <p className="text-xs text-muted-foreground">
                                            Columnas: <span className="font-mono">{tipo.columns.join(', ')}</span>{' '}
                                            <a
                                                className="inline-flex items-center gap-1 underline"
                                                href={route('importaciones.template', tipo.kind)}
                                            >
                                                <Download className="size-3" /> plantilla
                                            </a>
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="file">Archivo (CSV o XLSX, máx. 5 MB y 5000 filas)</Label>
                                    <input
                                        id="file"
                                        type="file"
                                        accept=".csv,.txt,.xlsx"
                                        onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)}
                                        className="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-2 file:text-sm"
                                    />
                                    {form.errors.file && <p className="text-sm text-destructive">{form.errors.file}</p>}
                                    {form.errors.kind && <p className="text-sm text-destructive">{form.errors.kind}</p>}
                                </div>

                                <Button type="submit" disabled={form.processing || !form.data.file}>
                                    <Upload className="size-4" /> {form.processing ? 'Analizando…' : 'Analizar archivo'}
                                </Button>
                            </form>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Últimas importaciones</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Lote</TableHead>
                                    <TableHead>Archivo</TableHead>
                                    <TableHead>Filas</TableHead>
                                    <TableHead>Estado</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {lotes.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={4} className="py-8 text-center text-muted-foreground">
                                            Aún no hay importaciones.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {lotes.map((l) => (
                                    <TableRow key={l.id}>
                                        <TableCell>
                                            <Link className="font-medium underline" href={route('importaciones.show', l.id)}>
                                                #{l.id}
                                            </Link>
                                            <div className="text-xs text-muted-foreground">{l.created_at}</div>
                                        </TableCell>
                                        <TableCell className="max-w-48 truncate">
                                            {l.filename}
                                            <div className="text-xs text-muted-foreground">
                                                {l.kind} · {l.user}
                                            </div>
                                        </TableCell>
                                        <TableCell>{l.total_rows}</TableCell>
                                        <TableCell>
                                            <Badge variant={l.status === 'applied' ? 'success' : 'secondary'}>
                                                {l.status === 'applied' ? 'Aplicado' : 'Pendiente de confirmar'}
                                            </Badge>
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
