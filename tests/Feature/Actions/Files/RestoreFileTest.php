<?php

use App\Actions\Files\RestoreFile;
use App\Models\File;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->action = new RestoreFile;
});

it('restores soft deleted file', function () {
    $file = File::factory()->create(['workspace_id' => $this->workspace->id]);
    $file->delete(); // Soft delete

    $restoredFile = $this->action->handle($file->id);

    expect($restoredFile)->toBeInstanceOf(File::class);
    expect($restoredFile->id)->toBe($file->id);
    $this->assertDatabaseHas('files', [
        'id' => $file->id,
        'deleted_at' => null,
    ]);
});

it('returns null for non-existent file', function () {
    $result = $this->action->handle('non-existent-id');

    expect($result)->toBeNull();
});
