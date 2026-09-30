<?php

namespace App\Exports;

use App\Exports\Sheets\TemplateSiswaDataSheet;
use App\Exports\Sheets\TemplateSiswaPetunjukSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TemplateSiswaExport implements WithMultipleSheets
{
    /**
     * @return array<int, mixed>
     */
    public function sheets(): array
    {
        return [
            new TemplateSiswaDataSheet,
            new TemplateSiswaPetunjukSheet,
        ];
    }
}
