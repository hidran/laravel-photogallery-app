<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Album;
use App\Models\User;
use Illuminate\Database\Seeder;

final class AlbumSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        $users->each(function (User $user): void {
            Album::factory(fake()->numberBetween(1, 3))->create([
                'user_id' => $user->id,
            ]);
        });
    }
}
