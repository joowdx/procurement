<?php

namespace Database\Factories;

use App\Models\Folder;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Folder>
 */
class FolderFactory extends Factory
{
    protected $model = Folder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'parent_id' => null,
            'name' => $this->faker->word(),
            'description' => $this->faker->sentence(),
            'level' => 0,
            'order' => 1,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    /**
     * Create a subfolder.
     */
    public function child(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => Folder::factory(),
            'level' => 1,
        ]);
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Folder $folder) {
            // If folder has a parent, recalculate its level
            if ($folder->parent_id) {
                $parent = Folder::find($folder->parent_id);
                if ($parent) {
                    $folder->update(['level' => $parent->level + 1]);
                }
            }
        });
    }
}
