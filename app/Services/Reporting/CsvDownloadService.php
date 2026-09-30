<?php

namespace App\Services\Reporting;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvDownloadService
{
    /**
     * Stream a query as a UTF-8 CSV download without loading all records in memory.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $headings
     * @param  Closure(TModel): list<float|int|string|null>  $row
     */
    public function download(Builder $query, string $filename, array $headings, Closure $row): StreamedResponse
    {
        return response()->streamDownload(function () use ($query, $headings, $row): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                throw new RuntimeException('Unable to open the CSV output stream.');
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $this->sanitizeRow($headings));

            foreach ($query->lazy() as $record) {
                fputcsv($stream, $this->sanitizeRow($row($record)));
            }

            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Prevent text cells from being interpreted as spreadsheet formulas.
     *
     * @param  list<float|int|string|null>  $values
     * @return list<float|int|string>
     */
    private function sanitizeRow(array $values): array
    {
        return array_map(function (float|int|string|null $value): float|int|string {
            if (! is_string($value)) {
                return $value ?? '';
            }

            $trimmedValue = ltrim($value);

            if (str_starts_with($trimmedValue, '=')
                || str_starts_with($trimmedValue, '+')
                || str_starts_with($trimmedValue, '-')
                || str_starts_with($trimmedValue, '@')) {
                return "'{$value}";
            }

            return $value;
        }, $values);
    }
}
