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
        return Student::updateOrCreate(
            ['customer_number' => $row['kassenzeichen']],
            [
                'name' => $row['name'],
                'email' => $row['email'],
                'email_2' => $row['email_2'] ?: null,
            ]
        );
    }

    public function rules(): array
    {
        return [
            'kassenzeichen' => 'required',
            'name' => 'required|string',
            'email' => 'required|email',
            'email_2' => 'nullable|email',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'kassenzeichen.required' => 'Kassenzeichen fehlt.',
            'name.required' => 'Name fehlt.',
            'email.required' => 'E-Mail fehlt.',
            'email.email' => 'E-Mail ist ungültig.',
            'email_2.email' => 'Zweite E-Mail ist ungültig.',
        ];
    }

    public function prepareForValidation(array $data): array
    {
        $data['kassenzeichen'] = $data['kassenzeichen'] ?? $data['kundennummer'] ?? null;
        $data['email'] = $data['email'] ?? $data['e_mail'] ?? $data['e-mail'] ?? null;
        $data['email_2'] = $data['email_2'] ?? $data['e_mail_2'] ?? $data['email2'] ?? null;

        return $data;
    }
}
