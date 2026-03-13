<?php

namespace App\Imports;

use App\Models\Student;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\Importable;

class StudentsImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows, SkipsOnFailure
{
    use SkipsFailures, Importable;

    public function model(array $row): ?Student
    {
        $customerNumber = $row['kassenzeichen'] ?? $row['kundennummer'] ?? null;
        $email2 = $row['email_2'] ?? $row['e_mail_2'] ?? $row['email2'] ?? null;

        return Student::updateOrCreate(
            ['customer_number' => $customerNumber],
            [
                'name' => $row['name'],
                'email' => $row['email'] ?? $row['e_mail'] ?? $row['e-mail'] ?? null,
                'email_2' => $email2,
            ]
        );
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string',
        ];
    }

    public function prepareForValidation(array $data): array
    {
        if (empty($data['kassenzeichen'] ?? null) && empty($data['kundennummer'] ?? null)) {
            $data['kassenzeichen'] = null;
        }

        return $data;
    }
}
