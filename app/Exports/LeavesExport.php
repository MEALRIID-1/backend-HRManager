<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class LeavesExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    protected Collection $data;

    public function __construct(Collection $data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        // Convert models/objects to arrays
        return $this->data->map(function ($row) {
            if (is_array($row)) return collect($row);
            return collect($row)->map(function ($v) {
                if (is_scalar($v)) return $v;
                return is_null($v) ? '' : json_encode($v, JSON_UNESCAPED_UNICODE);
            });
        })->map(fn($c) => $c->toArray());
    }

    public function headings(): array
    {
        if ($this->data->isEmpty()) return [];
        $first = (array) $this->data->first();
        return array_keys($first);
    }
}
