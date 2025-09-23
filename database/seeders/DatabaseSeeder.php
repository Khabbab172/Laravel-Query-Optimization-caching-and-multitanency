<?php

namespace Database\Seeders;

use App\Models\FormData;
use App\Models\FormOption;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create 10 form options
        $options = FormOption::factory()->count(10)->create();

        // Create 50 users
        User::factory()->count(50)->create()->each(function ($user) use ($options) {
            // Assign 3 random options to each user
            $randomOptions = $options->random(3);
            foreach ($randomOptions as $option) {
                FormData::factory()->create([
                    'user_id' => $user->id,
                    'option_id' => $option->id,
                    'value' => 'Sample value for ' . $option->label,
                ]);
            }
        });

    }
}
