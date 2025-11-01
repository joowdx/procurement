<?php

use App\Actions\Folders\DeleteFolder;
use App\Models\Folder;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->folder = Folder::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->action = new DeleteFolder;
    $this->actingAs($this->user);
});

it('soft deletes folder by default', function () {
    $result = $this->action->handle($this->folder, false);

    expect($result)->toBeTrue();
    $this->assertSoftDeleted('folders', ['id' => $this->folder->id]);
});

it('permanently deletes folder when forced', function () {
    $result = $this->action->handle($this->folder, true);

    expect($result)->toBeTrue();
    $this->assertDatabaseMissing('folders', ['id' => $this->folder->id]);
});

it('sets deleted_by when soft deleting', function () {
    $this->action->handle($this->folder, false);

    $this->assertDatabaseHas('folders', [
        'id' => $this->folder->id,
        'deleted_by' => $this->user->id,
    ]);
});
