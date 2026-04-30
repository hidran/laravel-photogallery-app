<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProcessingStatus;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Photo>
 */
final class PhotoFactory extends Factory
{
    protected $model = Photo::class;

    public function definition(): array
    {
        $width = $this->faker->numberBetween(800, 4000);
        $height = $this->faker->numberBetween(600, 3000);
        $name = $this->faker->slug(2);

        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->optional(0.7)->paragraph(),
            'filename' => $name.'.jpg',
            'original_path' => 'originals/'.$name.'.jpg',
            'thumbnail_path' => null,
            'medium_path' => null,
            'large_path' => null,
            'album_id' => null,
            'width' => $width,
            'height' => $height,
            'file_size' => $this->faker->numberBetween(100_000, 5_000_000),
            'mime_type' => 'image/jpeg',
            'exif' => null,
            'processing_status' => $this->faker->randomElement(ProcessingStatus::cases())->value,
            'processing_attempts' => 0,
            'processing_error' => null,
        ];
    }

    /**
     * Photo whose 3 variants are populated and status is `completed`.
     */
    public function processed(): self
    {
        return $this->state(fn (array $attrs): array => [
            'thumbnail_path' => 'thumbnails/'.pathinfo((string) $attrs['filename'], PATHINFO_FILENAME).'.jpg',
            'medium_path' => 'medium/'.pathinfo((string) $attrs['filename'], PATHINFO_FILENAME).'.jpg',
            'large_path' => 'large/'.pathinfo((string) $attrs['filename'], PATHINFO_FILENAME).'.jpg',
            'processing_status' => ProcessingStatus::Completed->value,
        ]);
    }
}
