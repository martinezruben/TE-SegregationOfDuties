<?php

namespace App\Http\Controllers\Concerns;

use Symfony\Component\HttpFoundation\StreamedResponse;

trait StreamsCsv
{
    /**
     * CSV con BOM (acentos en Excel) y neutralización de fórmulas (=, +, -, @).
     *
     * @param  list<string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    protected function csv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $safe = fn ($v) => is_string($v) && $v !== '' && str_contains('=+-@', $v[0]) ? "'".$v : $v;

        return response()->streamDownload(function () use ($headers, $rows, $safe) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, array_map($safe, $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
