<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            'Matematika', 'Fisika', 'Kimia', 'Biologi', 'Bahasa Indonesia',
            'Bahasa Inggris', 'Ekonomi', 'Sosiologi', 'Geografi', 'Sejarah',
            'TPS UTBK', 'Penalaran Matematika UTBK', 'Bahasa Mandarin', 'Coding & Komputer',
        ];

        foreach ($subjects as $name) {
            Subject::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
        }
    }
}
