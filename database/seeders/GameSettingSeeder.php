<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GameSetting;

class GameSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $games = [
            [
                'game_slug' => 'wild_west',
                'game_name' => 'Wild West Slot',
                'algorithm_mode' => 'normal',
                'normal_admin_percent' => 60,
                'normal_user_percent' => 40,
            ],
            [
                'game_slug' => 'aviator',
                'game_name' => 'Aviator Crash',
                'algorithm_mode' => 'normal',
                'normal_admin_percent' => 60,
                'normal_user_percent' => 40,
            ],
            [
                'game_slug' => 'ace_super',
                'game_name' => 'Ace Super Cards',
                'algorithm_mode' => 'normal',
                'normal_admin_percent' => 60,
                'normal_user_percent' => 40,
            ],
            [
                'game_slug' => 'slots',
                'game_name' => 'Classic Slots',
                'algorithm_mode' => 'normal',
                'normal_admin_percent' => 60,
                'normal_user_percent' => 40,
            ],
        ];

        foreach ($games as $game) {
            // firstOrCreate ব্যবহার করলে বারবার সিড রান করলেও ডুপ্লিকেট ডাটা ঢুকবে না
            GameSetting::firstOrCreate(
                ['game_slug' => $game['game_slug']],
                $game
            );
        }
    }
}