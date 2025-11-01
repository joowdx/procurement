<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\User;
use App\Models\Version;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\File>
 */
class FileFactory extends Factory
{
    protected $model = File::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => $this->faker->word().'.pdf',
            'description' => $this->faker->sentence(),
            'type' => 'application/pdf',
            'extension' => 'pdf',
            'metadata' => null,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (File $file) {
            // Create a version for the file
            Version::factory()->create([
                'file_id' => $file->id,
                'number' => 1,
            ]);
        });
    }
}
