<?php

namespace Database\Seeders;

use App\Models\Student;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $students = [
            ['customer_number' => 'MS-1001', 'name' => 'Lukas Müller',         'email' => 'lukas.mueller@example.de'     ],
            ['customer_number' => 'MS-1002', 'name' => 'Sophie Schmidt',       'email' => 'sophie.schmidt@example.de'    ],
            ['customer_number' => 'MS-1003', 'name' => 'Jonas Fischer',        'email' => 'jonas.fischer@example.de',        'email_2' => 'petra.fischer@example.de'  ],
            ['customer_number' => 'MS-1004', 'name' => 'Emma Weber',           'email' => 'emma.weber@example.de'        ],
            ['customer_number' => 'MS-1005', 'name' => 'Leon Wagner',          'email' => 'leon.wagner@example.de',          'email_2' => 'maria.wagner@example.de'   ],
            ['customer_number' => 'MS-1006', 'name' => 'Mia Becker',          'email' => 'mia.becker@example.de'        ],
            ['customer_number' => 'MS-1007', 'name' => 'Felix Hoffmann',      'email' => 'felix.hoffmann@example.de'    ],
            ['customer_number' => 'MS-1008', 'name' => 'Hannah Schäfer',      'email' => 'hannah.schaefer@example.de',      'email_2' => 'thomas.schaefer@example.de'],
            ['customer_number' => 'MS-1009', 'name' => 'Paul Koch',           'email' => 'paul.koch@example.de'         ],
            ['customer_number' => 'MS-1010', 'name' => 'Lena Richter',        'email' => 'lena.richter@example.de'      ],
            ['customer_number' => 'MS-1011', 'name' => 'Maximilian Klein',    'email' => 'max.klein@example.de'         ],
            ['customer_number' => 'MS-1012', 'name' => 'Laura Wolf',          'email' => 'laura.wolf@example.de'        ],
            ['customer_number' => 'MS-1013', 'name' => 'Alex Schröder',       'email' => 'alex.schroeder@example.de'    ],
            ['customer_number' => 'MS-1014', 'name' => 'Tim Neumann',         'email' => 'tim.neumann@example.de',          'email_2' => 'sabine.neumann@example.de' ],
            ['customer_number' => 'MS-1015', 'name' => 'Anna Schwarz',        'email' => 'anna.schwarz@example.de'      ],
            ['customer_number' => 'MS-1016', 'name' => 'Niklas Zimmermann',   'email' => 'niklas.zimmermann@example.de' ],
            ['customer_number' => 'MS-1017', 'name' => 'Marie Braun',         'email' => 'marie.braun@example.de'       ],
            ['customer_number' => 'MS-1018', 'name' => 'David Hartmann',      'email' => 'david.hartmann@example.de'    ],
            ['customer_number' => 'MS-1019', 'name' => 'Julia Krüger',        'email' => 'julia.krueger@example.de',        'email_2' => 'markus.krueger@example.de' ],
            ['customer_number' => 'MS-1020', 'name' => 'Ben Lang',            'email' => 'ben.lang@example.de'          ],
        ];

        foreach ($students as $data) {
            Student::create(array_merge($data, [
                'phone' => fake('de_DE')->phoneNumber(),
                'active' => true,
            ]));
        }
    }
}
