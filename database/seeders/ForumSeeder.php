<?php

namespace Database\Seeders;

use App\Models\ForumCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ForumSeeder extends Seeder
{
    public function run(): void
    {
        $categories = ['Matematika', 'IPA', 'IPS', 'Bahasa Inggris', 'UTBK', 'SBMPTN'];

        foreach ($categories as $name) {
            ForumCategory::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
        }
    }
}
