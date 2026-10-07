<?php

namespace App\Services\Import;

use App\Models\User;

interface Importer
{
    /** Clave del tipo de importación (foto | gestionados). */
    public function kind(): string;

    public function label(): string;

    /** Permiso adicional que exige este tipo (además de importaciones.crear). */
    public function permission(): string;

    /** Encabezados de la plantilla y una fila de ejemplo. */
    public function template(): array;

    /**
     * Analiza las filas SIN escribir en la base de datos (dry-run).
     *
     * @param  list<array{line:int, data:array<string,mixed>}>  $rows
     * @return list<array{line:int, status:string, label:string, message:?string, plan:?array}>
     *                                                                                          status: crear | actualizar | sin_cambios | error
     */
    public function analyze(array $rows): array;

    /**
     * Aplica los resultados válidos de `analyze` (dentro de la transacción del llamador).
     *
     * @param  list<array{line:int, status:string, label:string, message:?string, plan:?array}>  $analysis
     * @return array{creados:int, actualizados:int, sin_cambios:int}
     */
    public function apply(array $analysis, User $by): array;
}
