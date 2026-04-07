<?php

namespace App\Imports;

use App\Models\Student;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class StudentsImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows, SkipsOnFailure, WithCustomCsvSettings
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

        // Whitespace, BOM, Non-Breaking-Space entfernen
        foreach (['kassenzeichen', 'name', 'email', 'email_2'] as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $data[$key] = $this->cleanString($data[$key]);
            }
        }

        if ($data['email_2'] === '') {
            $data['email_2'] = null;
        }

        return $data;
    }

    /**
     * CSV-Reader-Settings: Excel-Exports sind meist Windows-1252 / ISO-8859-1.
     * PhpSpreadsheet versucht UTF-8/CP1252 automatisch zu erkennen.
     */
    public function getCsvSettings(): array
    {
        return [
            'input_encoding' => 'Guess',
            'delimiter' => null,
        ];
    }

    private function cleanString(string $value): string
    {
        // BOM, Non-Breaking-Space (U+00A0), Zero-Width-Space, normale Whitespaces
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
        $value = str_replace(["\xC2\xA0", "\xE2\x80\x8B"], ' ', $value);

        return trim($value);
    }
}
