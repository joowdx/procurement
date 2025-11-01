<?php

use App\Actions\Files\UpdateFile;
use App\Models\File;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->file = File::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->action = new UpdateFile;
});

it('updates file with valid data', function () {
    $data = [
        'name' => 'Updated File Name',
        'description' => 'Updated Description',
    ];

    $updatedFile = $this->action->handle($this->file, $data, $this->user);

    expect($updatedFile->name)->toBe('Updated File Name');
    expect($updatedFile->description)->toBe('Updated Description');
});

it('updates only provided fields', function () {
    $originalDescription = $this->file->description;
    $data = [
        'name' => 'Updated File Name',
    ];

    $updatedFile = $this->action->handle($this->file, $data, $this->user);

    expect($updatedFile->name)->toBe('Updated File Name');
    expect($updatedFile->description)->toBe($originalDescription);
});

it('handles empty description', function () {
    $data = [
        'name' => 'Updated File Name',
        'description' => '',
    ];

    $updatedFile = $this->action->handle($this->file, $data, $this->user);

    expect($updatedFile->description)->toBeNull();
});
