<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Version>
 */
class VersionFactory extends Factory
{
    protected $model = \App\Models\Version::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hash = $this->faker->sha256();
        $path = substr($hash, 0, 2).'/'.substr($hash, 2, 2).'/'.$hash.'/file.pdf';

        return [
            'file_id' => File::factory(),
            'number' => 1,
            'hash' => $hash,
            'disk' => 'local',
            'path' => $path,
            'size' => $this->faker->numberBetween(1000, 1000000),
            'metadata' => null,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (\App\Models\Version $version) {
            // Create actual file in storage for testing
            if ($version->disk === 'local' && \Illuminate\Support\Facades\Storage::disk('local')->missing($version->path)) {
                \Illuminate\Support\Facades\Storage::disk('local')->put($version->path, 'test file content');
            }
        });
    }
}
