<?php

namespace App\Exports;

use App\Models\Student;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StudentsExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    protected $query;

    public function __construct($query = null)
    {
        $this->query = $query;
    }

    public function query()
    {
        return $this->query ?? Student::query()->orderBy('name');
    }

    public function headings(): array
    {
        return [
            'Name',
            'E-Mail',
            'E-Mail 2',
            'Kassenzeichen',
            'Status',
            'Reaktion am',
            'Bestätigt über',
            'IP-Adresse',
        ];
    }

    public function map($student): array
    {
        $latest = $student->latestResponse();

        $status = match ($latest?->status) {
            'accepted' => 'Angenommen',
            'declined' => 'Gekündigt',
            default => 'Ausstehend',
        };

        return [
            $student->name,
            $student->email,
            $student->email_2 ?? '',
            $student->customer_number,
            $status,
            $latest?->responded_at?->format('d.m.Y') ?? '',
            $latest?->responded_via_email ?? '',
            $latest?->ip_address ?? '',
        ];
    }
}
