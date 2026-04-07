<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BeispielImportExport implements FromArray, WithHeadings, WithStyles
{
    public function headings(): array
    {
        return ['kassenzeichen', 'name', 'email', 'email_2'];
    }

    public function array(): array
    {
        return [
            ['MS-1001', 'Max Mustermann', 'max@example.com', 'max.privat@example.com'],
            ['MS-1002', 'Erika Musterfrau', 'erika@example.com', ''],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
