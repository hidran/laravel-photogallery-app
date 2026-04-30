<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Album;
use App\Models\Photo;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

final class PhotoSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $tags = Tag::all();
        $albums = Album::all();

        $users->each(function (User $user) use ($tags, $albums): void {
            $userAlbums = $albums->where('user_id', $user->id);

            // Create photos with albums
            $userAlbums->each(function (Album $album) use ($user, $tags): void {
                $photos = Photo::factory(fake()->numberBetween(3, 8))
                    ->processed()
                    ->create([
                        'user_id' => $user->id,
                        'album_id' => $album->id,
                    ]);

                // Attach random tags to each photo
                $photos->each(function (Photo $photo) use ($tags): void {
                    $photo->tags()->attach(
                        $tags->random(fake()->numberBetween(1, 4))->pluck('id')
                    );
                });

                // Set first photo as album cover
                $album->update(['cover_photo_id' => $photos->first()->id]);
            });

            // Create some photos without album
            $unalbumed = Photo::factory(fake()->numberBetween(2, 5))
                ->processed()
                ->create([
                    'user_id' => $user->id,
                ]);

            $unalbumed->each(function (Photo $photo) use ($tags): void {
                $photo->tags()->attach(
                    $tags->random(fake()->numberBetween(1, 3))->pluck('id')
                );
            });
        });
    }
}
