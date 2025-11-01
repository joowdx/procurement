<?php

use App\Actions\Files\DeleteFile;
use App\Models\File;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->file = File::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->action = new DeleteFile;
    $this->actingAs($this->user);
});

it('soft deletes file by default', function () {
    $result = $this->action->handle($this->file, false);

    expect($result)->toBeTrue();
    $this->assertSoftDeleted('files', ['id' => $this->file->id]);
});

it('permanently deletes file when forced', function () {
    $result = $this->action->handle($this->file, true);

    expect($result)->toBeTrue();
    $this->assertDatabaseMissing('files', ['id' => $this->file->id]);
});

it('sets deleted_by when soft deleting', function () {
    $this->action->handle($this->file, false);

    $this->assertDatabaseHas('files', [
        'id' => $this->file->id,
        'deleted_by' => $this->user->id,
    ]);
});
