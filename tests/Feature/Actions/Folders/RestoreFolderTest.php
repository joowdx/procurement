<?php

use App\Actions\Folders\RestoreFolder;
use App\Models\Folder;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->action = new RestoreFolder;
});

it('restores soft deleted folder', function () {
    $folder = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
    $folder->delete(); // Soft delete

    $restoredFolder = $this->action->handle($folder->id);

    expect($restoredFolder)->toBeInstanceOf(Folder::class);
    expect($restoredFolder->id)->toBe($folder->id);
    $this->assertDatabaseHas('folders', [
        'id' => $folder->id,
        'deleted_at' => null,
    ]);
});

it('returns null for non-existent folder', function () {
    $result = $this->action->handle('non-existent-id');

    expect($result)->toBeNull();
});
