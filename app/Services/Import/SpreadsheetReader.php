<?php

namespace App\Services\Import;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Lee la primera hoja de un CSV/XLSX con fila de encabezados y devuelve filas
 * normalizadas (claves en snake_case sin acentos, valores recortados).
 */
class SpreadsheetReader
{
    public const MAX_ROWS = 5000;

    /**
     * @return list<array{line:int, data:array<string, mixed>}> (line = número de fila en el archivo)
     */
    public function read(UploadedFile|string $file): array
    {
        $import = new class implements ToArray, WithHeadingRow
        {
            public function array(array $array): void {}
        };

        $sheets = Excel::toArray($import, $file);
        $rows = $sheets[0] ?? [];

        $out = [];
        foreach ($rows as $i => $row) {
            $data = [];
            foreach ($row as $key => $value) {
                $data[(string) $key] = is_string($value) ? trim($value) : $value;
            }
            // Ignora filas totalmente vacías.
            if (collect($data)->every(fn ($v) => $v === null || $v === '')) {
                continue;
            }
            $out[] = ['line' => $i + 2, 'data' => $data]; // +1 encabezado, +1 base 1
        }

        return $out;
    }

    /** Primer valor no vacío entre varios alias de columna. */
    public static function pick(array $data, string ...$aliases): mixed
    {
        foreach ($aliases as $alias) {
            $v = $data[$alias] ?? null;
            if ($v !== null && $v !== '') {
                return $v;
            }
        }

        return null;
    }

    /** Fecha desde serial de Excel o texto (Y-m-d, d/m/Y). null si vacía; false si inválida. */
    public static function date(mixed $value): Carbon|null|false
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::instance(Date::excelToDateTimeObject((float) $value))->startOfDay();
            }
            $value = (string) $value;
            foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
                $d = Carbon::createFromFormat('!'.$format, $value);
                if ($d !== false && $d->format($format) === $value) {
                    return $d;
                }
            }
        } catch (\Throwable) {
            // cae al false
        }

        return false;
    }

    public static function bool(mixed $value, bool $default = true): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(Str::lower(trim((string) $value)), ['1', 'si', 'sí', 's', 'true', 'activo', 'yes', 'y'], true);
    }
}
