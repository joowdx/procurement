<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Workspace>
 */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->company();

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(),
            'settings' => null,
            'active' => true,
            'updated_by' => User::factory(),
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Workspace $workspace) {
            // Create owner membership only if it doesn't exist
            $exists = \App\Models\Membership::where('workspace_id', $workspace->id)
                ->where('user_id', $workspace->user_id)
                ->exists();

            if (! $exists) {
                \App\Models\Membership::create([
                    'workspace_id' => $workspace->id,
                    'user_id' => $workspace->user_id,
                    'role' => 'owner',
                    'permissions' => [
                        'users' => true,
                        'files' => true,
                        'folders' => true,
                        'settings' => true,
                    ],
                    'joined_at' => now(),
                ]);
            }
        });
    }

    /**
     * Indicate that the workspace is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
