<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

final class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            'landscape', 'portrait', 'nature', 'architecture', 'street',
            'wildlife', 'macro', 'black-and-white', 'sunset', 'travel',
            'food', 'abstract', 'night', 'urban', 'beach',
        ];

        foreach ($tags as $tag) {
            Tag::factory()->create([
                'name' => $tag,
                'slug' => $tag,
            ]);
        }
    }
}
