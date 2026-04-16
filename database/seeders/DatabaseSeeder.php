<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Competence;
use App\Models\Universite;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Universites
        $universites = [
            ['nom' => "Université Hassan II de Casablanca", 'abbreviation' => "UH2C", 'ville' => "Casablanca", 'pays' => "Maroc"],
            ['nom' => "Université Mohammed V de Rabat", 'abbreviation' => "UM5R", 'ville' => "Rabat", 'pays' => "Maroc"],
            ['nom' => "Université Cadi Ayyad de Marrakech", 'abbreviation' => "UCAM", 'ville' => "Marrakech", 'pays' => "Maroc"],
            ['nom' => "Université Sidi Mohamed Ben Abdellah de Fès", 'abbreviation' => "USMBA", 'ville' => "Fès", 'pays' => "Maroc"],
        ];

        foreach ($universites as $u) {
            Universite::updateOrCreate(['abbreviation' => $u['abbreviation']], $u);
        }

        // Competences
        $competences = [
            ['nom' => 'PHP', 'type' => 'programming'],
            ['nom' => 'JavaScript', 'type' => 'programming'],
            ['nom' => 'Python', 'type' => 'programming'],
            ['nom' => 'Java', 'type' => 'programming'],
            ['nom' => 'Laravel', 'type' => 'framework'],
            ['nom' => 'React', 'type' => 'framework'],
            ['nom' => 'Vue.js', 'type' => 'framework'],
            ['nom' => 'Angular', 'type' => 'framework'],
            ['nom' => 'Node.js', 'type' => 'tool'],
            ['nom' => 'Docker', 'type' => 'tool'],
            ['nom' => 'Git', 'type' => 'tool'],
            ['nom' => 'Leadership', 'type' => 'soft skills'],
        ];

        foreach ($competences as $c) {
            Competence::updateOrCreate(['nom' => $c['nom']], $c);
        }

        // Test Users
        User::updateOrCreate(
            ['email' => 'admin@talentlink.com'],
            [
                'nom' => 'Admin User',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );
    }
}
