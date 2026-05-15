<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\StudentList;
use Illuminate\Database\Seeder;

class StudentListSeeder extends Seeder
{
    public function run(): void
    {
        // Demo-Listen, damit das Listen-Feature und die Mehrfach-Teilnahme
        // direkt nach migrate:fresh --seed sichtbar sind.
        $klavier = StudentList::create([
            'name' => 'Klavierschüler',
            'description' => 'Demo-Liste mit fünf Schülern für die Klavierklasse.',
        ]);

        $geige = StudentList::create([
            'name' => 'Geigenschüler',
            'description' => 'Demo-Liste mit fünf Schülern für die Geigenklasse.',
        ]);

        // Erste 5 Schüler in Klavierschüler, naechste 5 in Geigenschueler.
        // Ein bewusster Ueberlapp im Geigen-Set zeigt im Demo-Datenbestand,
        // dass ein Schüler auch in mehreren Listen sein kann.
        $students = Student::orderBy('id')->take(10)->get();

        $klavier->allMembers()->attach($students->take(5)->pluck('id'));
        $geige->allMembers()->attach($students->slice(3, 5)->pluck('id'));
    }
}
